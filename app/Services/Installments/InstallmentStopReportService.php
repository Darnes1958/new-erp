<?php

namespace App\Services\Installments;

use App\Models\InstallmentBank;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\PayrollBank;
use Illuminate\Database\Eloquent\Builder;

class InstallmentStopReportService
{
    public function stoppedContractsQuery(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): Builder {
        $columns = [
            'id',
            'customer_id',
            'installment_bank_id',
            'payroll_bank_id',
            'bank_account_number',
            'contract_total',
            'installment_amount',
            'total_paid',
            'balance',
        ];

        $active = $this->constrainByBank(
            InstallmentContract::query()
                ->select($columns)
                ->whereHas('stop', fn (Builder $query) => $this->constrainStopDate($query, $dateFrom, $dateTo)),
            $filterBy,
            $installmentBankId,
            $payrollBankId,
        );

        $archived = $this->constrainByBank(
            InstallmentContractArchive::query()
                ->select($columns)
                ->whereHas('stop', fn (Builder $query) => $this->constrainStopDate($query, $dateFrom, $dateTo))
                ->whereNotIn('id', InstallmentContract::query()->select('id')),
            $filterBy,
            $installmentBankId,
            $payrollBankId,
        );

        return InstallmentContract::query()
            ->fromSub($active->unionAll($archived), 'installment_contracts')
            ->with(['customer', 'stop', 'installmentBank.payrollBank']);
    }

    public function resolvePayrollBank(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
    ): ?PayrollBank {
        if ($filterBy === 2 && $payrollBankId) {
            return PayrollBank::query()->find($payrollBankId);
        }

        if ($filterBy === 1 && $installmentBankId) {
            return InstallmentBank::query()
                ->with('payrollBank')
                ->find($installmentBankId)
                ?->payrollBank;
        }

        return null;
    }

    public function payrollBankForContract(InstallmentContract $contract): ?PayrollBank
    {
        return $contract->installmentBank?->payrollBank
            ?? ($contract->payroll_bank_id ? PayrollBank::query()->find($contract->payroll_bank_id) : null);
    }

    /**
     * @return array<int, string>
     */
    public function filterLines(
        PayrollBank $payrollBank,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $lines = [
            'للمصرف التجميعي : '.$payrollBank->name,
        ];

        if (filled($dateFrom)) {
            $lines[] = 'من تاريخ: '.$dateFrom;
        }

        if (filled($dateTo)) {
            $lines[] = 'إلى تاريخ: '.$dateTo;
        }

        return $lines;
    }

    /**
     * @return array{count: int, contract_total: float}
     */
    public function bankSummary(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $query = $this->stoppedContractsQuery($filterBy, $installmentBankId, $payrollBankId, $dateFrom, $dateTo);

        return [
            'count' => (int) $query->count(),
            'contract_total' => (float) $query->sum('contract_total'),
        ];
    }

    protected function constrainByBank(
        Builder $query,
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
    ): Builder {
        if ($filterBy === 1) {
            if (! $installmentBankId) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('installment_bank_id', $installmentBankId);
        }

        if (! $payrollBankId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $bankQuery) use ($payrollBankId): void {
            $bankQuery
                ->where('payroll_bank_id', $payrollBankId)
                ->orWhereIn(
                    'installment_bank_id',
                    InstallmentBank::query()
                        ->where('payroll_bank_id', $payrollBankId)
                        ->select('id'),
                );
        });
    }

    protected function constrainStopDate(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        return $query
            ->when(
                filled($dateFrom),
                fn (Builder $dateQuery): Builder => $dateQuery->whereDate('stop_date', '>=', $dateFrom),
            )
            ->when(
                filled($dateTo),
                fn (Builder $dateQuery): Builder => $dateQuery->whereDate('stop_date', '<=', $dateTo),
            );
    }
}
