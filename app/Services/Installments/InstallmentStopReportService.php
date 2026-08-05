<?php

namespace App\Services\Installments;

use App\Models\InstallmentBank;
use App\Models\InstallmentContract;
use App\Models\PayrollBank;
use Illuminate\Database\Eloquent\Builder;

class InstallmentStopReportService
{
    public function stoppedContractsQuery(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
    ): Builder {
        $query = InstallmentContract::query()
            ->with(['customer', 'stop', 'installmentBank.payrollBank'])
            ->whereHas('stop');

        if ($filterBy === 1) {
            if (! $installmentBankId) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('installment_bank_id', $installmentBankId);
        }

        if (! $payrollBankId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'installment_bank_id',
            InstallmentBank::query()
                ->where('payroll_bank_id', $payrollBankId)
                ->select('id'),
        );
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
        return $contract->installmentBank?->payrollBank;
    }

    /**
     * @return array<int, string>
     */
    public function filterLines(PayrollBank $payrollBank): array
    {
        return [
            'للمصرف التجميعي : '.$payrollBank->name,
        ];
    }

    /**
     * @return array{count: int, contract_total: float}
     */
    public function bankSummary(int $filterBy, ?int $installmentBankId, ?int $payrollBankId): array
    {
        $query = InstallmentContract::query();

        if ($filterBy === 1 && $installmentBankId) {
            $query->where('installment_bank_id', $installmentBankId);
        } elseif ($filterBy === 2 && $payrollBankId) {
            $query->whereIn(
                'installment_bank_id',
                InstallmentBank::query()
                    ->where('payroll_bank_id', $payrollBankId)
                    ->select('id'),
            );
        } else {
            return ['count' => 0, 'contract_total' => 0.0];
        }

        return [
            'count' => (int) $query->count(),
            'contract_total' => (float) $query->sum('contract_total'),
        ];
    }
}
