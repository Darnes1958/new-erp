<?php

namespace App\Services\Market;

use App\Database\Views\CustomerLedgerEntriesView;
use App\Enums\ReceiptTransactionKind;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerLedgerReportService
{
    public function transactionKindLabel(int $kind): string
    {
        return match ($kind) {
            7 => 'فاتورة مبيعات',
            15 => 'ترجيع مبيعات',
            18 => 'خصم قسط',
            19 => 'فائض',
            20 => 'ترجيع',
            default => ReceiptTransactionKind::tryFrom($kind)?->getLabel() ?? (string) $kind,
        };
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function lifetimeTotals(?int $customerId): array
    {
        if (! filled($customerId)) {
            return ['debit' => 0, 'credit' => 0, 'balance' => 0];
        }

        [$sql, $bindings] = $this->scopedLinesSql();
        $customerBindings = array_fill(0, $bindings, $customerId);

        $row = DB::connection($this->connectionName())->selectOne(
            "SELECT COALESCE(SUM(mden), 0) AS debit, COALESCE(SUM(daen), 0) AS credit FROM ({$sql}) AS lines",
            $customerBindings,
        );

        $debit = (float) ($row->debit ?? 0);
        $credit = (float) ($row->credit ?? 0);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => round($debit - $credit, 3),
        ];
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function periodTotals(?int $customerId, ?string $dateFrom): array
    {
        if (! filled($customerId) || ! filled($dateFrom)) {
            return ['debit' => 0, 'credit' => 0, 'balance' => 0];
        }

        [$sql, $bindings] = $this->scopedLinesSql();
        $customerBindings = array_fill(0, $bindings, $customerId);

        $row = DB::connection($this->connectionName())->selectOne(
            "SELECT COALESCE(SUM(mden), 0) AS debit, COALESCE(SUM(daen), 0) AS credit
             FROM ({$sql}) AS lines
             WHERE CAST(lines.rep_date AS date) >= ?",
            [...$customerBindings, $dateFrom],
        );

        $debit = (float) ($row->debit ?? 0);
        $credit = (float) ($row->credit ?? 0);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => round($debit - $credit, 3),
        ];
    }

    public function openingBalance(?int $customerId, ?string $dateFrom): float
    {
        if (! filled($customerId) || ! filled($dateFrom)) {
            return 0;
        }

        [$sql, $bindings] = $this->scopedLinesSql();
        $customerBindings = array_fill(0, $bindings, $customerId);

        $row = DB::connection($this->connectionName())->selectOne(
            "SELECT COALESCE(SUM(COALESCE(mden, 0) - COALESCE(daen, 0)), 0) AS balance
             FROM ({$sql}) AS lines
             WHERE CAST(lines.rep_date AS date) < ?",
            [...$customerBindings, $dateFrom],
        );

        return (float) ($row->balance ?? 0);
    }

    public function movementQuery(?int $customerId, ?string $dateFrom): Builder
    {
        if (! filled($customerId)) {
            return CustomerLedgerEntry::query()->whereRaw('1 = 0');
        }

        [$linesSql, $bindingCount] = $this->scopedLinesSql();
        $customerBindings = array_fill(0, $bindingCount, $customerId);

        if (filled($dateFrom)) {
            $openingSql = <<<SQL
COALESCE((
    SELECT SUM(COALESCE(opening.mden, 0) - COALESCE(opening.daen, 0))
    FROM ({$linesSql}) AS opening
    WHERE CAST(opening.rep_date AS date) < ?
), 0)
SQL;
            $datePredicate = 'WHERE CAST(numbered.rep_date AS date) >= ?';
            // Placeholder order in SQL: opening lines → opening date → movement lines → period date
            $bindings = [...$customerBindings, $dateFrom, ...$customerBindings, $dateFrom];
        } else {
            $openingSql = 'CAST(0 AS decimal(18, 3))';
            $datePredicate = '';
            $bindings = $customerBindings;
        }

        $sql = <<<SQL
(
    SELECT
        numbered.customer_id,
        numbered.customer_name,
        numbered.rep_date,
        numbered.id,
        numbered.idd,
        numbered.transaction_kind,
        numbered.payment_method_name,
        numbered.mden,
        numbered.daen,
        numbered.notes,
        {$openingSql}
            + SUM(COALESCE(numbered.mden, 0) - COALESCE(numbered.daen, 0)) OVER (
                ORDER BY numbered.rep_date, numbered.idd
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) AS running_balance
    FROM (
        SELECT
            lines.customer_id,
            lines.customer_name,
            lines.rep_date,
            lines.source_id AS id,
            ROW_NUMBER() OVER (
                ORDER BY lines.rep_date, lines.source_order, lines.sort_id
            ) AS idd,
            lines.transaction_kind,
            lines.payment_method_name,
            lines.mden,
            lines.daen,
            lines.notes
        FROM ({$linesSql}) AS lines
    ) AS numbered
    {$datePredicate}
) AS customer_ledger_entries
SQL;

        return CustomerLedgerEntry::query()
            ->fromRaw($sql, $bindings)
            ->select('customer_ledger_entries.*')
            ->orderBy('customer_ledger_entries.rep_date')
            ->orderBy('customer_ledger_entries.idd');
    }

    public function balancesQuery(?string $dateFrom, ?string $dateTo, bool $includeZero = false): Builder
    {
        return CustomerLedgerEntry::query()
            ->selectRaw('customer_id, MAX(customer_name) AS name, SUM(mden) AS mden, SUM(daen) AS daen, SUM(mden - daen) AS raseed')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('rep_date', '<=', $dateTo))
            ->groupBy('customer_id')
            ->when(! $includeZero, fn (Builder $query): Builder => $query->havingRaw('ABS(SUM(mden - daen)) >= 0.001'));
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function balancesSummary(?string $dateFrom, ?string $dateTo): array
    {
        $row = CustomerLedgerEntry::query()
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('rep_date', '<=', $dateTo))
            ->selectRaw('COALESCE(SUM(mden), 0) AS debit, COALESCE(SUM(daen), 0) AS credit, COALESCE(SUM(mden - daen), 0) AS balance')
            ->first();

        return [
            'debit' => (float) ($row->debit ?? 0),
            'credit' => (float) ($row->credit ?? 0),
            'balance' => (float) ($row->balance ?? 0),
        ];
    }

    public function resolveCustomer(?int $customerId): ?Customer
    {
        if (! filled($customerId)) {
            return null;
        }

        return Customer::query()->find($customerId);
    }

    /**
     * @param  Collection<int, CustomerLedgerEntry>  $rows
     */
    public function attachRunningBalance(Collection $rows, float $openingBalance): Collection
    {
        $running = $openingBalance;

        return $rows->map(function (CustomerLedgerEntry $row) use (&$running): CustomerLedgerEntry {
            $running += (float) $row->mden - (float) $row->daen;
            $row->setAttribute('running_balance', round($running, 3));

            return $row;
        });
    }

    /**
     * @return array{0: string, 1: int}
     */
    protected function scopedLinesSql(): array
    {
        return (new CustomerLedgerEntriesView)->unionLinesSqlWithBindings(
            $this->connectionName(),
            includeReturnedAmountInSalesInvoice: true,
            bindCustomer: true,
        );
    }

    protected function connectionName(): string
    {
        return (new CustomerLedgerEntry)->getConnectionName()
            ?? config('database.default');
    }
}
