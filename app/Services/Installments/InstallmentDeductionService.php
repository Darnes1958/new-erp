<?php

namespace App\Services\Installments;

use App\Enums\DeductionBatchPostedType;
use App\Enums\InstallmentRecordStatus;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\InstallmentSurplus;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationContext;
use App\Support\SystemOperationType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentDeductionService
{
    public function __construct(
        protected InstallmentContractMetricsService $metrics,
    ) {}

    /**
     * @return array{color: string, message: string, posted_type: DeductionBatchPostedType}
     */
    public function record(
        InstallmentContract|InstallmentContractArchive $contract,
        Carbon|string $deductionDate,
        float $amount,
        int $deductionTypeId,
        ?string $notes = null,
        ?int $batchId = null,
    ): array {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'deducted_amount' => 'يجب إدخال قيمة القسط.',
            ]);
        }

        if ($contract instanceof InstallmentContractArchive) {
            return DB::connection($contract->getConnectionName())->transaction(function () use ($contract, $deductionDate, $amount, $batchId): array {
                $this->createSurplus($contract, $deductionDate, $amount, batchId: $batchId);

                return [
                    'color' => 'info',
                    'message' => 'تم خصم القسط بالفائض من الأرشيف',
                    'posted_type' => DeductionBatchPostedType::Archive,
                ];
            });
        }

        return DB::connection($contract->getConnectionName())->transaction(function () use ($contract, $deductionDate, $amount, $deductionTypeId, $notes, $batchId): array {
            $contract->refresh();
            $balance = (float) $contract->balance;
            $remainingInstallments = InstallmentContractMetricsService::remainingInstallmentCount($contract);

            if ($balance <= 0 || $remainingInstallments <= 0) {
                $this->createSurplus($contract, $deductionDate, $amount, batchId: $batchId);

                return [
                    'color' => 'primary',
                    'message' => 'تم خصم القسط بالفائض',
                    'posted_type' => DeductionBatchPostedType::Surplus,
                ];
            }

            if ($amount <= $balance) {
                $this->createDeduction($contract, $deductionDate, $amount, $deductionTypeId, $notes, $batchId);

                return [
                    'color' => 'success',
                    'message' => 'تم خصم القسط بنجاح',
                    'posted_type' => DeductionBatchPostedType::Normal,
                ];
            }

            $deduction = $this->createDeduction($contract, $deductionDate, $balance, $deductionTypeId, $notes, $batchId);
            $surplus = $this->createSurplus(
                $contract,
                $deductionDate,
                $amount - $balance,
                $deduction->id,
                $batchId,
            );

            $deduction->forceFill([
                'surplus_id' => $surplus->id,
                'remaining_balance' => $surplus->amount,
            ])->saveQuietly();

            return [
                'color' => 'danger',
                'message' => 'تم خصم القسط جزئياً',
                'posted_type' => DeductionBatchPostedType::Partial,
            ];
        });
    }

    public function update(
        InstallmentDeduction $deduction,
        Carbon|string $deductionDate,
        float $amount,
        int $deductionTypeId,
        ?string $notes = null,
    ): void {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'deducted_amount' => 'يجب إدخال قيمة القسط.',
            ]);
        }

        $contract = $deduction->installmentContract;

        if (! $contract) {
            throw ValidationException::withMessages([
                'deducted_amount' => 'العقد غير موجود.',
            ]);
        }

        DB::connection($contract->getConnectionName())->transaction(function () use ($deduction, $contract, $deductionDate, $amount, $deductionTypeId, $notes): void {
            $contract->refresh();

            $availableBalance = (float) $contract->balance + (float) $deduction->deducted_amount;

            if ($deduction->surplus_id) {
                InstallmentSurplus::query()->whereKey($deduction->surplus_id)->delete();
            }

            if ($amount <= $availableBalance) {
                $deduction->update([
                    'deduction_date' => $deductionDate,
                    'deducted_amount' => $amount,
                    'deduction_type_id' => $deductionTypeId,
                    'notes' => $notes,
                    'surplus_id' => null,
                    'remaining_balance' => 0,
                ]);

                return;
            }

            $deduction->update([
                'deduction_date' => $deductionDate,
                'deducted_amount' => $availableBalance,
                'deduction_type_id' => $deductionTypeId,
                'notes' => $notes,
            ]);

            $surplus = $this->createSurplus(
                $contract,
                $deductionDate,
                $amount - $availableBalance,
                $deduction->id,
            );

            $deduction->forceFill([
                'surplus_id' => $surplus->id,
                'remaining_balance' => $surplus->amount,
            ])->saveQuietly();
        });

        SystemOperationLogger::updated(
            SystemOperationType::INSTALLMENT_DEDUCTION,
            $deduction->installment_contract_id,
            SystemOperationContext::customer(
                InstallmentContract::query()
                    ->whereKey($deduction->installment_contract_id)
                    ->value('customer_id'),
            ),
        );
    }

    public function delete(InstallmentDeduction $deduction): void
    {
        if ((float) $deduction->remaining_balance > 0) {
            throw ValidationException::withMessages([
                'deduction' => 'لا يمكن حذف قسط مرتبط بفائض جزئي من هذه الشاشة.',
            ]);
        }

        $contractId = $deduction->installment_contract_id;
        $connection = $deduction->getConnectionName();
        $context = SystemOperationContext::customer(
            InstallmentContract::on($connection)->whereKey($contractId)->value('customer_id'),
        );

        DB::connection($connection)->transaction(function () use ($deduction, $contractId, $connection): void {
            $deduction->delete();

            $contract = InstallmentContract::on($connection)->find($contractId);

            if ($contract) {
                $this->reorderSequences($contract);
            }
        });

        SystemOperationLogger::cancelled(SystemOperationType::INSTALLMENT_DEDUCTION, $contractId, $context);
    }

    public function reorderSequences(InstallmentContract $contract): void
    {
        $deductions = $contract->deductions()
            ->orderBy('installment_due_date')
            ->orderBy('id')
            ->get();

        $sequence = 1;

        foreach ($deductions as $deduction) {
            if ((int) $deduction->sequence !== $sequence) {
                $deduction->forceFill(['sequence' => $sequence])->saveQuietly();
            }

            $sequence++;
        }
    }

    protected function createDeduction(
        InstallmentContract $contract,
        Carbon|string $deductionDate,
        float $amount,
        int $deductionTypeId,
        ?string $notes,
        ?int $batchId,
    ): InstallmentDeduction {
        if (InstallmentContractMetricsService::remainingInstallmentCount($contract) <= 0) {
            throw ValidationException::withMessages([
                'deducted_amount' => 'لا توجد أقساط متبقية على العقد.',
            ]);
        }

        return $contract->deductions()->create([
            'sequence' => $this->nextSequence($contract),
            'deducted_amount' => $amount,
            'deduction_date' => $deductionDate,
            'installment_due_date' => $this->nextDueDate($contract),
            'deduction_type_id' => $deductionTypeId,
            'notes' => $notes,
            'batch_id' => $batchId,
            'remaining_balance' => 0,
            'created_by' => Auth::id(),
        ]);
    }

    protected function createSurplus(
        Model $contract,
        Carbon|string $surplusDate,
        float $amount,
        ?int $deductionId = null,
        ?int $batchId = null,
    ): InstallmentSurplus {
        return $contract->surpluses()->create([
            'surplus_date' => $surplusDate,
            'amount' => $amount,
            'status' => InstallmentRecordStatus::Open->value,
            'batch_id' => $batchId,
            'deduction_id' => $deductionId,
            'created_by' => Auth::id(),
        ]);
    }

    protected function nextDueDate(InstallmentContract $contract): string
    {
        $lastDueDate = $contract->deductions()->max('installment_due_date');

        if ($lastDueDate) {
            return InstallmentContractMetricsService::nextInstallmentDateAfter($lastDueDate);
        }

        return InstallmentContractMetricsService::initialNextInstallmentDate(
            $contract->contract_start ?? now()
        );
    }

    protected function nextSequence(InstallmentContract $contract): int
    {
        return ((int) $contract->deductions()->max('sequence')) + 1;
    }
}
