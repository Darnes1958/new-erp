<?php

namespace App\Services\Installments;

use App\Enums\DeductionBatchPostedType;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentCancelledDeduction;
use App\Models\InstallmentContract;
use App\Models\InstallmentDeduction;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentCancelledContractService
{
    public function __construct(
        protected InstallmentContractMetricsService $metrics,
    ) {}

    public function moveFromActive(InstallmentContract $contract): InstallmentCancelledContract
    {
        $connection = $contract->getConnectionName();

        return DB::connection($connection)->transaction(function () use ($contract, $connection): InstallmentCancelledContract {
            if (InstallmentCancelledContract::on($connection)->whereKey($contract->id)->exists()) {
                throw ValidationException::withMessages([
                    'id' => 'العقد موجود مسبقاً في العقود الملغية.',
                ]);
            }

            $attributes = $contract->only([
                'id',
                'customer_id',
                'installment_bank_id',
                'workplace_id',
                'payroll_bank_id',
                'bank_account_number',
                'contract_start',
                'contract_end',
                'contract_total',
                'installment_count',
                'installment_amount',
                'total_paid',
                'balance',
                'sales_invoice_id',
                'cheques_in',
                'cheques_out',
                'last_deduction_month',
                'next_installment_date',
                'late_amount',
                'installments_remaining',
                'surplus_count',
                'surplus_amount',
                'suspended_count',
                'suspended_amount',
                'notes',
                'created_by',
            ]);

            $cancelled = InstallmentCancelledContract::on($connection)->create([
                ...$attributes,
                'cancelled_at' => now()->toDateString(),
            ]);

            $contract->deductions()
                ->orderBy('id')
                ->each(function (InstallmentDeduction $deduction) use ($cancelled, $connection): void {
                    InstallmentCancelledDeduction::on($connection)->create([
                        'installment_contract_id' => $cancelled->id,
                        'sequence' => $deduction->sequence,
                        'deducted_amount' => $deduction->deducted_amount,
                        'deduction_date' => $deduction->deduction_date,
                        'installment_due_date' => $deduction->installment_due_date,
                        'deduction_type_id' => $deduction->deduction_type_id,
                        'notes' => $deduction->notes,
                        'batch_id' => $deduction->batch_id,
                        'surplus_id' => $deduction->surplus_id,
                        'remaining_balance' => $deduction->remaining_balance,
                        'created_by' => $deduction->created_by,
                        'created_at' => $deduction->created_at,
                        'updated_at' => $deduction->updated_at,
                    ]);

                    $deduction->delete();
                });

            InstallmentSurplus::on($connection)
                ->where('contractable_type', $contract->getMorphClass())
                ->where('contractable_id', $contract->id)
                ->update([
                    'contractable_type' => $cancelled->getMorphClass(),
                ]);

            InstallmentSuspended::on($connection)
                ->where('contractable_type', $contract->getMorphClass())
                ->where('contractable_id', $contract->id)
                ->update([
                    'contractable_type' => $cancelled->getMorphClass(),
                ]);

            DB::connection($connection)
                ->table('installment_stops')
                ->where('installment_contract_id', $contract->id)
                ->delete();

            DB::connection($connection)
                ->table('installment_cheques')
                ->where('installment_contract_id', $contract->id)
                ->delete();

            $contract->delete();

            return $cancelled->refresh();
        });
    }

    /**
     * @return array{color: string, message: string, posted_type: DeductionBatchPostedType}
     */
    public function recordDeduction(
        InstallmentCancelledContract $contract,
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

        return DB::connection($contract->getConnectionName())->transaction(function () use ($contract, $deductionDate, $amount, $deductionTypeId, $notes, $batchId): array {
            $contract->refresh();
            $balance = (float) $contract->balance;
            $remainingInstallments = InstallmentContractMetricsService::remainingCancelledInstallmentCount($contract);

            if ($balance <= 0 || $remainingInstallments <= 0) {
                return [
                    'color' => 'warning',
                    'message' => 'تم تسجيل القسط على عقد ملغي بعد التعاقد (لا رصيد متبقٍ)',
                    'posted_type' => DeductionBatchPostedType::Cancelled,
                ];
            }

            if ($amount <= $balance) {
                $this->createDeduction($contract, $deductionDate, $amount, $deductionTypeId, $notes, $batchId);

                return [
                    'color' => 'warning',
                    'message' => 'تم تسجيل القسط على عقد ملغي بعد التعاقد',
                    'posted_type' => DeductionBatchPostedType::Cancelled,
                ];
            }

            $this->createDeduction($contract, $deductionDate, $balance, $deductionTypeId, $notes, $batchId);

            return [
                'color' => 'warning',
                'message' => 'تم تسجيل القسط جزئياً على عقد ملغي بعد التعاقد',
                'posted_type' => DeductionBatchPostedType::Cancelled,
            ];
        });
    }

    protected function createDeduction(
        InstallmentCancelledContract $contract,
        Carbon|string $deductionDate,
        float $amount,
        int $deductionTypeId,
        ?string $notes,
        ?int $batchId,
    ): InstallmentCancelledDeduction {
        if (InstallmentContractMetricsService::remainingCancelledInstallmentCount($contract) <= 0) {
            throw ValidationException::withMessages([
                'deducted_amount' => 'لا توجد أقساط متبقية على العقد الملغي.',
            ]);
        }

        $deduction = $contract->deductions()->create([
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

        $this->metrics->recalculateCancelled($contract->refresh());

        return $deduction;
    }

    protected function nextDueDate(InstallmentCancelledContract $contract): string
    {
        $lastDueDate = $contract->deductions()->max('installment_due_date');

        if ($lastDueDate) {
            return InstallmentContractMetricsService::nextInstallmentDateAfter($lastDueDate);
        }

        return InstallmentContractMetricsService::initialNextInstallmentDate(
            $contract->contract_start ?? now()
        );
    }

    protected function nextSequence(InstallmentCancelledContract $contract): int
    {
        return ((int) $contract->deductions()->max('sequence')) + 1;
    }
}
