<?php

namespace App\Services\Market;

use App\Enums\ReceiptTransactionKind;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\CashBox;
use App\Models\CashBoxLedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentAccountLedgerReportService
{
    public function transactionKindLabel(int $kind): string
    {
        return match ($kind) {
            30 => 'مصروف',
            31 => 'مرتب',
            32 => 'إيجار',
            33 => 'تحويل صادر',
            34 => 'تحويل وارد',
            default => ReceiptTransactionKind::tryFrom($kind)?->getLabel() ?? (string) $kind,
        };
    }

    public function cashBoxMovementQuery(?int $cashBoxId, ?string $dateFrom, ?string $dateTo = null): Builder
    {
        return CashBoxLedgerEntry::query()
            ->select('cash_box_ledger_entries.*')
            ->selectRaw(
                'COALESCE((
                    SELECT COALESCE(cb.opening_balance, 0) + COALESCE(SUM(COALESCE(opening.daen, 0) - COALESCE(opening.mden, 0)), 0)
                    FROM cash_boxes cb
                    LEFT JOIN cash_box_ledger_entries AS opening
                        ON opening.cash_box_id = cb.id
                       AND opening.rep_date < ?
                    WHERE cb.id = ?
                    GROUP BY cb.opening_balance
                ), 0) + SUM(COALESCE(cash_box_ledger_entries.daen, 0) - COALESCE(cash_box_ledger_entries.mden, 0))
                    OVER (
                        ORDER BY cash_box_ledger_entries.rep_date, cash_box_ledger_entries.idd
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS running_balance',
                [$dateFrom ?? '1900-01-01', $cashBoxId],
            )
            ->when(filled($cashBoxId), fn (Builder $query): Builder => $query->where('cash_box_id', $cashBoxId))
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('rep_date', '<=', $dateTo))
            ->orderBy('cash_box_ledger_entries.rep_date')
            ->orderBy('cash_box_ledger_entries.idd');
    }

    public function bankAccountMovementQuery(?int $bankAccountId, ?string $dateFrom, ?string $dateTo = null): Builder
    {
        return BankAccountLedgerEntry::query()
            ->select('bank_account_ledger_entries.*')
            ->selectRaw(
                'COALESCE((
                    SELECT COALESCE(ba.opening_balance, 0) + COALESCE(SUM(COALESCE(opening.daen, 0) - COALESCE(opening.mden, 0)), 0)
                    FROM bank_accounts ba
                    LEFT JOIN bank_account_ledger_entries AS opening
                        ON opening.bank_account_id = ba.id
                       AND opening.rep_date < ?
                    WHERE ba.id = ?
                    GROUP BY ba.opening_balance
                ), 0) + SUM(COALESCE(bank_account_ledger_entries.daen, 0) - COALESCE(bank_account_ledger_entries.mden, 0))
                    OVER (
                        ORDER BY bank_account_ledger_entries.rep_date, bank_account_ledger_entries.idd
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS running_balance',
                [$dateFrom ?? '1900-01-01', $bankAccountId],
            )
            ->when(filled($bankAccountId), fn (Builder $query): Builder => $query->where('bank_account_id', $bankAccountId))
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('rep_date', '<=', $dateTo))
            ->orderBy('bank_account_ledger_entries.rep_date')
            ->orderBy('bank_account_ledger_entries.idd');
    }

    public function cashBoxOpeningBalance(?int $cashBoxId, ?string $dateFrom): float
    {
        if (! filled($cashBoxId)) {
            return 0;
        }

        $openingBalance = (float) CashBox::query()->whereKey($cashBoxId)->value('opening_balance');
        $movementBefore = (float) CashBoxLedgerEntry::query()
            ->where('cash_box_id', $cashBoxId)
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '<', $dateFrom))
            ->selectRaw('COALESCE(SUM(COALESCE(daen, 0) - COALESCE(mden, 0)), 0) AS balance')
            ->value('balance');

        return round($openingBalance + $movementBefore, 3);
    }

    public function bankAccountOpeningBalance(?int $bankAccountId, ?string $dateFrom): float
    {
        if (! filled($bankAccountId)) {
            return 0;
        }

        $openingBalance = (float) BankAccount::query()->whereKey($bankAccountId)->value('opening_balance');
        $movementBefore = (float) BankAccountLedgerEntry::query()
            ->where('bank_account_id', $bankAccountId)
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '<', $dateFrom))
            ->selectRaw('COALESCE(SUM(COALESCE(daen, 0) - COALESCE(mden, 0)), 0) AS balance')
            ->value('balance');

        return round($openingBalance + $movementBefore, 3);
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function cashBoxPeriodTotals(?int $cashBoxId, ?string $dateFrom, ?string $dateTo): array
    {
        if (! filled($cashBoxId)) {
            return ['debit' => 0, 'credit' => 0, 'balance' => 0];
        }

        $row = CashBoxLedgerEntry::query()
            ->where('cash_box_id', $cashBoxId)
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('rep_date', '<=', $dateTo))
            ->selectRaw('COALESCE(SUM(mden), 0) AS debit, COALESCE(SUM(daen), 0) AS credit')
            ->first();

        $debit = (float) ($row->debit ?? 0);
        $credit = (float) ($row->credit ?? 0);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => round($credit - $debit, 3),
        ];
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function bankAccountPeriodTotals(?int $bankAccountId, ?string $dateFrom, ?string $dateTo): array
    {
        if (! filled($bankAccountId)) {
            return ['debit' => 0, 'credit' => 0, 'balance' => 0];
        }

        $row = BankAccountLedgerEntry::query()
            ->where('bank_account_id', $bankAccountId)
            ->when(filled($dateFrom), fn (Builder $query): Builder => $query->whereDate('rep_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn (Builder $query): Builder => $query->whereDate('rep_date', '<=', $dateTo))
            ->selectRaw('COALESCE(SUM(mden), 0) AS debit, COALESCE(SUM(daen), 0) AS credit')
            ->first();

        $debit = (float) ($row->debit ?? 0);
        $credit = (float) ($row->credit ?? 0);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => round($credit - $debit, 3),
        ];
    }

    /**
     * @param  Collection<int, CashBoxLedgerEntry|BankAccountLedgerEntry>  $rows
     * @return Collection<int, CashBoxLedgerEntry|BankAccountLedgerEntry>
     */
    public function attachRunningBalance(Collection $rows, float $openingBalance): Collection
    {
        $running = $openingBalance;

        return $rows->map(function ($row) use (&$running) {
            $running += (float) ($row->daen ?? 0) - (float) ($row->mden ?? 0);
            $row->running_balance = round($running, 3);

            return $row;
        });
    }
}
