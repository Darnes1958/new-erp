<?php

namespace App\Services\Market;

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

        $row = CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->selectRaw('COALESCE(SUM(mden), 0) AS debit, COALESCE(SUM(daen), 0) AS credit')
            ->first();

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

        $row = CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->whereDate('rep_date', '>=', $dateFrom)
            ->selectRaw('COALESCE(SUM(mden), 0) AS debit, COALESCE(SUM(daen), 0) AS credit')
            ->first();

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

        return (float) CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->whereDate('rep_date', '<', $dateFrom)
            ->selectRaw('COALESCE(SUM(COALESCE(mden, 0) - COALESCE(daen, 0)), 0) AS balance')
            ->value('balance');
    }

    public function movementQuery(?int $customerId, ?string $dateFrom): Builder
    {
        return CustomerLedgerEntry::query()
            ->select('customer_ledger_entries.*')
            ->selectRaw(
                'COALESCE((
                    SELECT SUM(COALESCE(opening.mden, 0) - COALESCE(opening.daen, 0))
                    FROM customer_ledger_entries AS opening
                    WHERE opening.customer_id = ?
                      AND opening.rep_date < ?
                ), 0) + SUM(COALESCE(customer_ledger_entries.mden, 0) - COALESCE(customer_ledger_entries.daen, 0))
                    OVER (
                        ORDER BY customer_ledger_entries.rep_date, customer_ledger_entries.idd
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS running_balance',
                [$customerId, $dateFrom],
            )
            ->when(filled($customerId), fn (Builder $query): Builder => $query->where('customer_id', $customerId))
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
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
}
