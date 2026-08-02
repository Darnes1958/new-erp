<?php

namespace App\Support;

use App\Models\DeductionBatch;
use App\Models\InstallmentBank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Company installment bank policy:
 *
 * - Aggregative (installment_by_payroll_bank = true):
 *   Each payroll bank has exactly one branch; batches are opened for the
 *   payroll bank and entry runs through that linked branch.
 *
 * - Branch (installment_by_payroll_bank = false):
 *   Each payroll bank may have many branches; batches and deductions are
 *   scoped branch-by-branch via installment_banks.
 */
class InstallmentBankScope
{
    public static function usesPayrollSelection(): bool
    {
        return CompanySettings::installmentByPayrollBank();
    }

    /**
     * @return array{payroll_bank_id: int|null, installment_bank_id: int|null}
     */
    public static function resolveBankIds(?int $payrollBankId, ?int $installmentBankId): array
    {
        if (self::usesPayrollSelection()) {
            if (! $payrollBankId) {
                throw ValidationException::withMessages([
                    'payroll_bank_id' => 'يجب اختيار المصرف التجميعي.',
                ]);
            }

            $branch = self::branchForPayroll($payrollBankId);

            if (! $branch) {
                throw ValidationException::withMessages([
                    'payroll_bank_id' => 'لا يوجد فرع مصرف مرتبط بهذا الحساب التجميعي.',
                ]);
            }

            return [
                'payroll_bank_id' => $payrollBankId,
                'installment_bank_id' => (int) $branch->id,
            ];
        }

        if (! $installmentBankId) {
            throw ValidationException::withMessages([
                'installment_bank_id' => 'يجب اختيار فرع المصرف.',
            ]);
        }

        $branch = InstallmentBank::query()->find($installmentBankId);

        if (! $branch) {
            throw ValidationException::withMessages([
                'installment_bank_id' => 'فرع المصرف غير موجود.',
            ]);
        }

        return [
            'payroll_bank_id' => $branch->payroll_bank_id,
            'installment_bank_id' => (int) $branch->id,
        ];
    }

    public static function branchForPayroll(int $payrollBankId, ?string $connection = null): ?InstallmentBank
    {
        $query = $connection !== null
            ? InstallmentBank::on($connection)->newQuery()
            : InstallmentBank::query();

        return $query
            ->where('payroll_bank_id', $payrollBankId)
            ->orderBy('id')
            ->first();
    }

    public static function applyScope(Builder $query, ?int $payrollBankId, ?int $installmentBankId): void
    {
        $connection = $query->getModel()->getConnectionName();

        if (self::usesPayrollSelection()) {
            if ($payrollBankId) {
                $query->where('payroll_bank_id', $payrollBankId);

                $branch = $installmentBankId
                    ? ($connection ? InstallmentBank::on($connection)->find($installmentBankId) : InstallmentBank::query()->find($installmentBankId))
                    : self::branchForPayroll($payrollBankId, $connection);

                if ($branch) {
                    $query->where('installment_bank_id', $branch->id);
                }
            }

            return;
        }

        if ($installmentBankId) {
            $query->where('installment_bank_id', $installmentBankId);
        }
    }

    public static function applyContractScope(Builder $query, DeductionBatch $batch): void
    {
        self::applyScope($query, $batch->payroll_bank_id, $batch->installment_bank_id);
    }

    public static function batchBankLabel(DeductionBatch $batch): string
    {
        $branch = $batch->branchDisplayName() ?? '—';

        return "المصرف: {$branch}";
    }
}
