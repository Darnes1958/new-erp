<?php

namespace App\Services\Market;

use App\Enums\ReceiptTransactionKind;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SupplierLedgerReportService
{
    public function transactionKindLabel(int $kind): string
    {
        return match ($kind) {
            8 => 'فاتورة مشتريات',
            16 => 'ترجيع مشتريات',
            default => ReceiptTransactionKind::tryFrom($kind)?->getLabel() ?? (string) $kind,
        };
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function lifetimeTotals(?int $supplierId): array
    {
        if (! filled($supplierId)) {
            return ['debit' => 0, 'credit' => 0, 'balance' => 0];
        }

        $row = SupplierLedgerEntry::query()
            ->where('supplier_id', $supplierId)
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
    public function periodTotals(?int $supplierId, ?string $dateFrom): array
    {
        if (! filled($supplierId) || ! filled($dateFrom)) {
            return ['debit' => 0, 'credit' => 0, 'balance' => 0];
        }

        $row = SupplierLedgerEntry::query()
            ->where('supplier_id', $supplierId)
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

    public function openingBalance(?int $supplierId, ?string $dateFrom): float
    {
        if (! filled($supplierId) || ! filled($dateFrom)) {
            return 0;
        }

        return (float) SupplierLedgerEntry::query()
            ->where('supplier_id', $supplierId)
            ->whereDate('rep_date', '<', $dateFrom)
            ->selectRaw('COALESCE(SUM(COALESCE(mden, 0) - COALESCE(daen, 0)), 0) AS balance')
            ->value('balance');
    }

    public function movementQuery(?int $supplierId, ?string $dateFrom): Builder
    {
        return SupplierLedgerEntry::query()
            ->select('supplier_ledger_entries.*')
            ->selectRaw(
                'COALESCE((
                    SELECT SUM(COALESCE(opening.mden, 0) - COALESCE(opening.daen, 0))
                    FROM supplier_ledger_entries AS opening
                    WHERE opening.supplier_id = ?
                      AND opening.rep_date < ?
                ), 0) + SUM(COALESCE(supplier_ledger_entries.mden, 0) - COALESCE(supplier_ledger_entries.daen, 0))
                    OVER (
                        ORDER BY supplier_ledger_entries.rep_date, supplier_ledger_entries.idd
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS running_balance',
                [$supplierId, $dateFrom],
            )
            ->when(filled($supplierId), fn (Builder $query): Builder => $query->where('supplier_id', $supplierId))
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->orderBy('supplier_ledger_entries.rep_date')
            ->orderBy('supplier_ledger_entries.idd');
    }

    public function balancesQuery(?string $dateFrom, ?string $dateTo, bool $includeZero = false): Builder
    {
        return SupplierLedgerEntry::query()
            ->selectRaw('supplier_id, MAX(supplier_name) AS name, SUM(mden) AS mden, SUM(daen) AS daen, SUM(mden - daen) AS raseed')
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('rep_date', '<=', $dateTo))
            ->groupBy('supplier_id')
            ->when(! $includeZero, fn (Builder $query): Builder => $query->havingRaw('ABS(SUM(mden - daen)) >= 0.001'));
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function balancesSummary(?string $dateFrom, ?string $dateTo): array
    {
        $row = SupplierLedgerEntry::query()
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

    public function resolveSupplier(?int $supplierId): ?Supplier
    {
        if (! filled($supplierId)) {
            return null;
        }

        return Supplier::query()->find($supplierId);
    }

    /**
     * @param  Collection<int, SupplierLedgerEntry>  $rows
     */
    public function attachRunningBalance(Collection $rows, float $openingBalance): Collection
    {
        $running = $openingBalance;

        return $rows->map(function (SupplierLedgerEntry $row) use (&$running): SupplierLedgerEntry {
            $running += (float) $row->mden - (float) $row->daen;
            $row->setAttribute('running_balance', round($running, 3));

            return $row;
        });
    }
}
