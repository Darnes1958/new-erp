<?php

namespace App\Services\Installments;

use App\Enums\BankReportType;
use App\Models\InstallmentBank;
use App\Models\InstallmentContract;
use App\Models\InstallmentDeduction;
use App\Models\PayrollBank;
use Illuminate\Database\Eloquent\Builder;

class InstallmentBankReportService
{
    public function scopedContractsQuery(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
    ): Builder {
        $query = InstallmentContract::query()->with('customer');

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

    public function contractsReportQuery(
        BankReportType $type,
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
        float $threshold = 5,
        bool $notPaidOnly = false,
    ): Builder {
        $query = $this->scopedContractsQuery($filterBy, $installmentBankId, $payrollBankId);

        return match ($type) {
            BankReportType::NamesList => $query,
            BankReportType::Paid => $query->where('balance', '<=', $threshold),
            BankReportType::Unpaid => $query->where('total_paid', 0),
            BankReportType::Late => $query
                ->where('late_amount', '>=', $threshold)
                ->when($notPaidOnly, fn (Builder $builder): Builder => $builder->where('total_paid', 0)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function collectedDeductionsQuery(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
        ?string $dateFrom,
        ?string $dateTo,
    ): Builder {
        $contractIds = $this->scopedContractsQuery($filterBy, $installmentBankId, $payrollBankId)->select('id');

        $query = InstallmentDeduction::query()
            ->with(['installmentContract.customer'])
            ->whereIn('installment_contract_id', $contractIds);

        if (filled($dateFrom)) {
            $query->whereDate('deduction_date', '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $query->whereDate('deduction_date', '<=', $dateTo);
        }

        return $query;
    }

    public function uncollectedContractsQuery(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
        ?string $dateFrom,
        ?string $dateTo,
    ): Builder {
        $query = $this->scopedContractsQuery($filterBy, $installmentBankId, $payrollBankId);

        $deductions = InstallmentDeduction::query()
            ->select('installment_contract_id')
            ->whereIn('installment_contract_id', (clone $query)->select('id'));

        if (filled($dateFrom)) {
            $deductions->whereDate('deduction_date', '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $deductions->whereDate('deduction_date', '<=', $dateTo);
        }

        return $query->whereNotIn('id', $deductions);
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

    /**
     * @return array{count: int, contract_total: float, total_paid: float, balance: float}
     */
    public function contractsSummary(
        BankReportType $type,
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
        float $threshold = 5,
        bool $notPaidOnly = false,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $query = match ($type) {
            BankReportType::Collected => $this->scopedContractsQuery($filterBy, $installmentBankId, $payrollBankId)->whereRaw('1 = 0'),
            BankReportType::Uncollected => $this->uncollectedContractsQuery(
                $filterBy,
                $installmentBankId,
                $payrollBankId,
                $dateFrom,
                $dateTo,
            ),
            default => $this->contractsReportQuery(
                $type,
                $filterBy,
                $installmentBankId,
                $payrollBankId,
                $threshold,
                $notPaidOnly,
            ),
        };

        return [
            'count' => (int) $query->count(),
            'contract_total' => (float) $query->sum('contract_total'),
            'total_paid' => (float) $query->sum('total_paid'),
            'balance' => (float) $query->sum('balance'),
        ];
    }

    /**
     * @return array{count: int, deducted_amount: float}
     */
    public function collectedSummary(
        int $filterBy,
        ?int $installmentBankId,
        ?int $payrollBankId,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $query = $this->collectedDeductionsQuery(
            $filterBy,
            $installmentBankId,
            $payrollBankId,
            $dateFrom,
            $dateTo,
        );

        return [
            'count' => (int) $query->count(),
            'deducted_amount' => (float) $query->sum('deducted_amount'),
        ];
    }
}
