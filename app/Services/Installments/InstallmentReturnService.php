<?php

namespace App\Services\Installments;

use App\Enums\InstallmentDeductionType;
use App\Enums\InstallmentRecordStatus;
use App\Enums\InstallmentReturnType;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentCancelledDeduction;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\InstallmentDeduction;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Models\WrongDeduction;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationContext;
use App\Support\SystemOperationType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentReturnService
{
    public function __construct(
        protected InstallmentDeductionService $deductions,
        protected InstallmentContractMetricsService $metrics,
    ) {}

    public function returnFromSurplus(
        InstallmentSurplus $surplus,
        Carbon|string|null $returnDate = null,
    ): InstallmentSuspended {
        if (! $surplus->status?->isOpen()) {
            throw ValidationException::withMessages([
                'surplus' => 'الفائض مرجّع مسبقاً.',
            ]);
        }

        $returnDate ??= now();
        $contractId = $this->contractIdFromSurplus($surplus);

        return DB::connection($surplus->getConnectionName())->transaction(function () use ($surplus, $returnDate, $contractId): InstallmentSuspended {
            $suspended = InstallmentSuspended::create([
                'contractable_type' => 'installment_surplus',
                'contractable_id' => $surplus->id,
                'installment_contract_id' => $contractId,
                'suspended_date' => $returnDate,
                'amount' => $surplus->amount,
                'status' => InstallmentReturnType::FromSurplus,
                'batch_id' => $surplus->batch_id,
                'created_by' => Auth::id(),
            ]);

            $surplus->forceFill([
                'status' => InstallmentRecordStatus::Returned,
                'suspended_id' => $suspended->id,
            ])->save();

            $this->recalculateContract($contractId, $surplus->getConnectionName());

            return $suspended;
        });
    }

    public function returnFromWrong(
        WrongDeduction $wrong,
        Carbon|string|null $returnDate = null,
    ): InstallmentSuspended {
        if (! $wrong->status?->isOpen()) {
            throw ValidationException::withMessages([
                'wrong' => 'القسط بالخطأ مرجّع أو مصحّح مسبقاً.',
            ]);
        }

        $returnDate ??= now();

        return DB::connection($wrong->getConnectionName())->transaction(function () use ($wrong, $returnDate): InstallmentSuspended {
            $suspended = InstallmentSuspended::create([
                'contractable_type' => 'wrong_deduction',
                'contractable_id' => $wrong->id,
                'installment_contract_id' => null,
                'suspended_date' => $returnDate,
                'amount' => $wrong->amount,
                'status' => InstallmentReturnType::FromWrong,
                'batch_id' => $wrong->batch_id,
                'created_by' => Auth::id(),
            ]);

            $wrong->forceFill([
                'status' => InstallmentRecordStatus::Returned,
                'suspended_id' => $suspended->id,
            ])->save();

            return $suspended;
        });
    }

    public function returnFromCancelled(
        InstallmentCancelledDeduction $deduction,
        Carbon|string|null $returnDate = null,
    ): InstallmentSuspended {
        if ((float) $deduction->remaining_balance > 0) {
            throw ValidationException::withMessages([
                'deduction' => 'لا يمكن ترجيع قسط مرتبط بفائض جزئي.',
            ]);
        }

        $contract = $deduction->installmentContract;

        if (! $contract) {
            throw ValidationException::withMessages([
                'deduction' => 'العقد الملغي غير موجود.',
            ]);
        }

        $returnDate ??= now();

        return DB::connection($contract->getConnectionName())->transaction(function () use ($deduction, $contract, $returnDate): InstallmentSuspended {
            $suspended = InstallmentSuspended::create([
                'contractable_type' => 'installment_cancelled_deduction',
                'contractable_id' => $deduction->id,
                'installment_contract_id' => $contract->id,
                'suspended_date' => $returnDate,
                'amount' => $deduction->deducted_amount,
                'status' => InstallmentReturnType::FromCancelled,
                'batch_id' => $deduction->batch_id,
                'created_by' => Auth::id(),
            ]);

            $deduction->delete();
            $this->reorderCancelledSequences($contract);
            $this->metrics->recalculateCancelled($contract);

            return $suspended;
        });
    }

    public function returnFromDeduction(
        InstallmentDeduction $deduction,
        Carbon|string|null $returnDate = null,
    ): InstallmentSuspended {
        if ((float) $deduction->remaining_balance > 0) {
            throw ValidationException::withMessages([
                'deduction' => 'لا يمكن ترجيع قسط مرتبط بفائض جزئي.',
            ]);
        }

        $contract = $deduction->installmentContract;

        if (! $contract) {
            throw ValidationException::withMessages([
                'deduction' => 'العقد غير موجود.',
            ]);
        }

        $returnDate ??= now();

        return DB::connection($contract->getConnectionName())->transaction(function () use ($deduction, $contract, $returnDate): InstallmentSuspended {
            $suspended = InstallmentSuspended::create([
                'contractable_type' => 'installment_contract',
                'contractable_id' => $contract->id,
                'installment_contract_id' => $contract->id,
                'suspended_date' => $returnDate,
                'amount' => $deduction->deducted_amount,
                'status' => InstallmentReturnType::FromDeduction,
                'batch_id' => $deduction->batch_id,
                'created_by' => Auth::id(),
            ]);

            $deduction->delete();
            $this->deductions->reorderSequences($contract);
            $this->metrics->recalculate($contract);

            return $suspended;
        });
    }

    public function undoReturn(InstallmentSuspended $suspended): void
    {
        $suspendedId = (int) $suspended->id;
        $context = SystemOperationContext::fromSuspended($suspended);

        DB::connection($suspended->getConnectionName())->transaction(function () use ($suspended): void {
            $returnType = $suspended->status;

            match ($returnType) {
                InstallmentReturnType::FromSurplus => $this->undoSurplusReturn($suspended),
                InstallmentReturnType::FromWrong => $this->undoWrongReturn($suspended),
                InstallmentReturnType::FromDeduction => $this->undoDeductionReturn($suspended),
                InstallmentReturnType::FromCancelled => $this->undoCancelledReturn($suspended),
                default => throw ValidationException::withMessages([
                    'return' => 'لا يمكن إلغاء هذا النوع من الترجيع.',
                ]),
            };

            $suspended->delete();

            if ($suspended->installment_contract_id) {
                $this->recalculateContract($suspended->installment_contract_id, $suspended->getConnectionName());
                $this->recalculateCancelledContract($suspended->installment_contract_id, $suspended->getConnectionName());
            }
        });

        SystemOperationLogger::cancelled(SystemOperationType::INSTALLMENT_RETURN, $suspendedId, $context);
    }

    public function resolveContract(InstallmentSuspended $suspended): ?InstallmentContract
    {
        if ($suspended->installment_contract_id) {
            return InstallmentContract::on($suspended->getConnectionName())
                ->find($suspended->installment_contract_id);
        }

        $source = $suspended->contractable;

        if ($source instanceof InstallmentContract) {
            return $source;
        }

        if ($source instanceof InstallmentCancelledContract) {
            return null;
        }

        if ($source instanceof InstallmentSurplus) {
            $contract = $source->contractable;

            return $contract instanceof InstallmentContract ? $contract : null;
        }

        return null;
    }

    protected function undoSurplusReturn(InstallmentSuspended $suspended): void
    {
        $surplus = $suspended->contractable;

        if (! $surplus instanceof InstallmentSurplus) {
            $surplus = InstallmentSurplus::on($suspended->getConnectionName())->find($suspended->contractable_id);
        }

        if (! $surplus instanceof InstallmentSurplus) {
            throw ValidationException::withMessages([
                'return' => 'سجل الفائض غير موجود.',
            ]);
        }

        $surplus->forceFill([
            'status' => InstallmentRecordStatus::Open,
            'suspended_id' => null,
        ])->save();
    }

    protected function undoWrongReturn(InstallmentSuspended $suspended): void
    {
        $wrong = $suspended->contractable;

        if (! $wrong instanceof WrongDeduction) {
            $wrong = WrongDeduction::on($suspended->getConnectionName())->find($suspended->contractable_id);
        }

        if (! $wrong instanceof WrongDeduction) {
            throw ValidationException::withMessages([
                'return' => 'سجل الخطأ غير موجود.',
            ]);
        }

        $wrong->forceFill([
            'status' => InstallmentRecordStatus::Open,
            'suspended_id' => null,
        ])->save();
    }

    protected function undoDeductionReturn(InstallmentSuspended $suspended): void
    {
        $contract = $this->resolveContract($suspended);

        if (! $contract) {
            throw ValidationException::withMessages([
                'return' => 'العقد غير موجود.',
            ]);
        }

        $contract->deductions()->create([
            'sequence' => ((int) $contract->deductions()->max('sequence')) + 1,
            'deducted_amount' => $suspended->amount,
            'deduction_date' => $suspended->suspended_date,
            'installment_due_date' => $this->deductionDueDateForRestore($contract),
            'deduction_type_id' => InstallmentDeductionType::Bank->value,
            'batch_id' => $suspended->batch_id,
            'remaining_balance' => 0,
            'created_by' => Auth::id(),
        ]);

        $this->deductions->reorderSequences($contract);
    }

    protected function undoCancelledReturn(InstallmentSuspended $suspended): void
    {
        $contract = InstallmentCancelledContract::on($suspended->getConnectionName())
            ->find($suspended->installment_contract_id);

        if (! $contract) {
            throw ValidationException::withMessages([
                'return' => 'العقد الملغي غير موجود.',
            ]);
        }

        $contract->deductions()->create([
            'sequence' => ((int) $contract->deductions()->max('sequence')) + 1,
            'deducted_amount' => $suspended->amount,
            'deduction_date' => $suspended->suspended_date,
            'installment_due_date' => $this->cancelledDeductionDueDateForRestore($contract),
            'deduction_type_id' => InstallmentDeductionType::Bank->value,
            'batch_id' => $suspended->batch_id,
            'remaining_balance' => 0,
            'created_by' => Auth::id(),
        ]);

        $this->reorderCancelledSequences($contract);
    }

    protected function cancelledDeductionDueDateForRestore(InstallmentCancelledContract $contract): string
    {
        $lastDueDate = $contract->deductions()->max('installment_due_date');

        if ($lastDueDate) {
            return InstallmentContractMetricsService::nextInstallmentDateAfter($lastDueDate);
        }

        return InstallmentContractMetricsService::initialNextInstallmentDate(
            $contract->contract_start ?? now()
        );
    }

    protected function reorderCancelledSequences(InstallmentCancelledContract $contract): void
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

    protected function deductionDueDateForRestore(InstallmentContract $contract): string
    {
        $lastDueDate = $contract->deductions()->max('installment_due_date');

        if ($lastDueDate) {
            return InstallmentContractMetricsService::nextInstallmentDateAfter($lastDueDate);
        }

        return InstallmentContractMetricsService::initialNextInstallmentDate(
            $contract->contract_start ?? now()
        );
    }

    protected function contractIdFromSurplus(InstallmentSurplus $surplus): ?int
    {
        $contract = $surplus->contractable;

        if ($contract instanceof InstallmentContract || $contract instanceof InstallmentContractArchive) {
            return (int) $contract->id;
        }

        return null;
    }

    protected function recalculateContract(?int $contractId, string $connection): void
    {
        if (! $contractId) {
            return;
        }

        $contract = InstallmentContract::on($connection)->find($contractId);

        if ($contract) {
            $this->metrics->recalculate($contract);
        }
    }

    protected function recalculateCancelledContract(?int $contractId, string $connection): void
    {
        if (! $contractId) {
            return;
        }

        $contract = InstallmentCancelledContract::on($connection)->find($contractId);

        if ($contract) {
            $this->metrics->recalculateCancelled($contract);
        }
    }
}
