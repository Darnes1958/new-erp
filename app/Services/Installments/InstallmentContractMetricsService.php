<?php

namespace App\Services\Installments;

use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentSuspended;
use App\Support\CompanyConnections;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps denormalized installment_contracts metrics in sync with source tables.
 *
 * Mapping from legacy ERP (mains):
 * - last_deduction_month  ← LastKsm   (max deduction_date)
 * - next_installment_date ← NextKst   (28th of due month; +1 month after each deduction)
 * - late_amount           ← Late      (count of overdue installments, NOT money)
 * - installments_remaining ← kst_baky
 * - surplus_count/amount  ← over_count/over_kst
 * - suspended_count/amount ← tar_count/tar_kst
 */
class InstallmentContractMetricsService
{
    public static function installmentDueDay(): int
    {
        return (int) config('erp.installment_due_day', 28);
    }

    public static function day28OfMonth(Carbon|string $reference): Carbon
    {
        return Carbon::parse($reference)->day(self::installmentDueDay());
    }

    public static function initialNextInstallmentDate(Carbon|string $contractStart): string
    {
        return self::day28OfMonth($contractStart)->toDateString();
    }

    public static function nextInstallmentDateAfter(Carbon|string $previousDueDate): string
    {
        return self::day28OfMonth(
            Carbon::parse($previousDueDate)->addMonth()
        )->toDateString();
    }

    public static function remainingInstallmentCount(InstallmentContract $contract, ?int $deductionCount = null): int
    {
        $deductionCount ??= (int) $contract->deductions()->count();

        return max(0, (int) $contract->installment_count - $deductionCount);
    }

    public function recalculate(InstallmentContract $contract, ?Carbon $asOf = null): InstallmentContract
    {
        $asOf ??= now();

        $deductionCount = (int) $contract->deductions()->count();
        $totalPaid = (float) $contract->deductions()->sum('deducted_amount');
        $lastDeductionDate = $contract->deductions()->max('deduction_date');
        $lastDueDate = $contract->deductions()->max('installment_due_date');

        $nextInstallmentDate = $lastDueDate
            ? self::nextInstallmentDateAfter($lastDueDate)
            : self::initialNextInstallmentDate($contract->contract_start ?? $asOf);

        $surplusCount = (int) $contract->surpluses()->count();
        $surplusAmount = (float) $contract->surpluses()->sum('amount');
        $suspendedCount = (int) InstallmentSuspended::on($contract->getConnectionName())
            ->where('installment_contract_id', $contract->id)
            ->count();
        $suspendedAmount = (float) InstallmentSuspended::on($contract->getConnectionName())
            ->where('installment_contract_id', $contract->id)
            ->sum('amount');

        $installmentsRemaining = self::remainingInstallmentCount($contract, $deductionCount);
        $lateAmount = min(
            $this->calculateLateCount($contract, $nextInstallmentDate, $deductionCount, $asOf),
            $installmentsRemaining,
        );

        $contract->forceFill([
            'total_paid' => $totalPaid,
            'balance' => (float) $contract->contract_total - $totalPaid,
            'last_deduction_month' => $lastDeductionDate,
            'next_installment_date' => $nextInstallmentDate,
            'installments_remaining' => $installmentsRemaining,
            'surplus_count' => $surplusCount,
            'surplus_amount' => $surplusAmount,
            'suspended_count' => $suspendedCount,
            'suspended_amount' => $suspendedAmount,
            'late_amount' => $lateAmount,
        ])->saveQuietly();

        return $contract->refresh();
    }

    /**
     * Count of installments overdue as of $asOf (legacy Late field).
     */
    public function calculateLateCount(
        InstallmentContract $contract,
        ?string $nextInstallmentDate = null,
        ?int $deductionCount = null,
        ?Carbon $asOf = null,
    ): int {
        $asOf ??= now();
        $nextInstallmentDate ??= $contract->next_installment_date?->toDateString()
            ?? self::initialNextInstallmentDate($contract->contract_start ?? $asOf);
        $deductionCount ??= (int) $contract->deductions()->count();

        $dueDate = Carbon::parse($nextInstallmentDate)->startOfDay();
        $today = $asOf->copy()->startOfDay();

        if ($today->lte($dueDate)) {
            return 0;
        }

        $months = (int) $dueDate->diffInMonths($today);

        $remainingCapacity = self::remainingInstallmentCount($contract, $deductionCount);

        return max(0, min($months, $remainingCapacity));
    }

    public function refreshLateCounts(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $updated = 0;

        foreach (config('erp.company_connections', []) as $connection) {
            if (! CompanyConnections::isValid($connection) || ! $this->connectionHasContractsTable($connection)) {
                continue;
            }

            InstallmentContract::on($connection)->chunkById(200, function ($contracts) use ($asOf, &$updated): void {
                foreach ($contracts as $contract) {
                    $remaining = self::remainingInstallmentCount($contract);
                    $late = min(
                        $this->calculateLateCount($contract, asOf: $asOf),
                        $remaining,
                    );

                    if ((int) $contract->late_amount !== $late) {
                        $contract->forceFill(['late_amount' => $late])->saveQuietly();
                        $updated++;
                    }
                }
            });
        }

        return $updated;
    }

    public function recalculateAll(?int $contractId = null, ?string $connection = null): int
    {
        $connections = $connection !== null
            ? [$connection]
            : config('erp.company_connections', []);

        $count = 0;

        foreach ($connections as $companyConnection) {
            if (! CompanyConnections::isValid($companyConnection) || ! $this->connectionHasContractsTable($companyConnection)) {
                continue;
            }

            $query = InstallmentContract::on($companyConnection)->orderBy('id');

            if ($contractId !== null) {
                $query->whereKey($contractId);
            }

            $query->chunkById(100, function ($contracts) use (&$count): void {
                foreach ($contracts as $contract) {
                    $this->recalculate($contract);
                    $count++;
                }
            });
        }

        return $count;
    }

    public static function remainingCancelledInstallmentCount(InstallmentCancelledContract $contract, ?int $deductionCount = null): int
    {
        $deductionCount ??= (int) $contract->deductions()->count();

        return max(0, (int) $contract->installment_count - $deductionCount);
    }

    public function recalculateCancelled(InstallmentCancelledContract $contract, ?Carbon $asOf = null): InstallmentCancelledContract
    {
        $asOf ??= now();

        $deductionCount = (int) $contract->deductions()->count();
        $totalPaid = (float) $contract->deductions()->sum('deducted_amount');
        $lastDeductionDate = $contract->deductions()->max('deduction_date');
        $lastDueDate = $contract->deductions()->max('installment_due_date');

        $nextInstallmentDate = $lastDueDate
            ? self::nextInstallmentDateAfter($lastDueDate)
            : self::initialNextInstallmentDate($contract->contract_start ?? $asOf);

        $surplusCount = (int) $contract->surpluses()->count();
        $surplusAmount = (float) $contract->surpluses()->sum('amount');
        $suspendedCount = (int) $contract->suspendedEntries()->count();
        $suspendedAmount = (float) $contract->suspendedEntries()->sum('amount');
        $installmentsRemaining = self::remainingCancelledInstallmentCount($contract, $deductionCount);

        $contract->forceFill([
            'total_paid' => $totalPaid,
            'balance' => (float) $contract->contract_total - $totalPaid,
            'last_deduction_month' => $lastDeductionDate,
            'next_installment_date' => $nextInstallmentDate,
            'installments_remaining' => $installmentsRemaining,
            'surplus_count' => $surplusCount,
            'surplus_amount' => $surplusAmount,
            'suspended_count' => $suspendedCount,
            'suspended_amount' => $suspendedAmount,
            'late_amount' => 0,
        ])->saveQuietly();

        return $contract->refresh();
    }

    protected function connectionHasContractsTable(string $connection): bool
    {
        try {
            return Schema::connection($connection)->hasTable('installment_contracts');
        } catch (\Throwable) {
            return false;
        }
    }
}
