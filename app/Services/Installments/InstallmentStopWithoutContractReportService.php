<?php

namespace App\Services\Installments;

use App\Models\InstallmentStopWithoutContract;
use App\Models\PayrollBank;
use Illuminate\Database\Eloquent\Builder;

class InstallmentStopWithoutContractReportService
{
    public function reportQuery(
        ?int $payrollBankId,
        ?string $dateFrom,
        ?string $dateTo,
    ): Builder {
        $query = InstallmentStopWithoutContract::query()
            ->with('payrollBank');

        if ($payrollBankId) {
            $query->where('payroll_bank_id', $payrollBankId);
        }

        if (filled($dateFrom)) {
            $query->whereDate('stop_date', '>=', $dateFrom);
        }

        if (filled($dateTo)) {
            $query->whereDate('stop_date', '<=', $dateTo);
        }

        return $query;
    }

    public function resolvePayrollBank(?int $payrollBankId): ?PayrollBank
    {
        if (! $payrollBankId) {
            return null;
        }

        return PayrollBank::query()->find($payrollBankId);
    }

    /**
     * @return array<int, string>
     */
    public function filterLines(
        ?int $payrollBankId,
        ?string $dateFrom,
        ?string $dateTo,
        bool $includeDates = true,
    ): array {
        $lines = [];

        if ($payrollBank = $this->resolvePayrollBank($payrollBankId)) {
            $lines[] = 'المصرف التجميعي: '.$payrollBank->name;
        }

        if ($includeDates) {
            if (filled($dateFrom)) {
                $lines[] = 'من تاريخ: '.$dateFrom;
            }

            if (filled($dateTo)) {
                $lines[] = 'إلى تاريخ: '.$dateTo;
            }
        }

        return $lines;
    }

    /**
     * @return array{count: int}
     */
    public function summary(?int $payrollBankId, ?string $dateFrom, ?string $dateTo): array
    {
        return [
            'count' => (int) $this->reportQuery($payrollBankId, $dateFrom, $dateTo)->count(),
        ];
    }

    public function payrollBankForRecord(InstallmentStopWithoutContract $record): ?PayrollBank
    {
        return $record->payrollBank;
    }
}
