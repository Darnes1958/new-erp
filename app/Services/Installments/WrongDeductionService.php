<?php

namespace App\Services\Installments;

use App\Enums\InstallmentDeductionType;
use App\Enums\InstallmentRecordStatus;
use App\Models\InstallmentContract;
use App\Models\WrongDeduction;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WrongDeductionService
{
    public function __construct(
        protected InstallmentReturnService $returns,
        protected InstallmentDeductionService $deductions,
        protected InstallmentContractMetricsService $metrics,
    ) {}

    /**
     * @param  Collection<int, WrongDeduction>  $records
     */
    public function returnMany(Collection $records): int
    {
        $count = 0;

        DB::connection($records->first()?->getConnectionName())->transaction(function () use ($records, &$count): void {
            foreach ($records as $record) {
                if (! $record->status?->isOpen()) {
                    continue;
                }

                $this->returns->returnFromWrong($record);
                $count++;
            }
        });

        return $count;
    }

    public function correctToContract(WrongDeduction $record, InstallmentContract $contract): void
    {
        if (! $record->status?->isOpen()) {
            throw ValidationException::withMessages([
                'wrong' => 'لا يمكن تصحيح هذا السجل.',
            ]);
        }

        DB::connection($record->getConnectionName())->transaction(function () use ($record, $contract): void {
            $siblings = WrongDeduction::query()
                ->where('account_number', $record->account_number)
                ->whereIn('status', [InstallmentRecordStatus::Legacy, InstallmentRecordStatus::Open])
                ->when(
                    $record->payroll_bank_id,
                    fn ($query) => $query->where('payroll_bank_id', $record->payroll_bank_id),
                )
                ->get();

            foreach ($siblings as $wrong) {
                $this->deductions->record(
                    $contract,
                    $wrong->deduction_date ?? now(),
                    (float) $wrong->amount,
                    InstallmentDeductionType::Bank->value,
                    notes: 'تصحيح قسط بالخطأ',
                    batchId: $wrong->batch_id,
                );

                $wrong->forceFill(['status' => InstallmentRecordStatus::Corrected])->save();
            }

            if ($record->account_number) {
                $contract->forceFill(['bank_account_number' => $record->account_number])->saveQuietly();
            }

            $this->metrics->recalculate($contract->refresh());
        });
    }

    /**
     * @param  Collection<int, WrongDeduction>  $records
     */
    public function deleteOpen(Collection $records): int
    {
        $count = 0;

        foreach ($records as $record) {
            if (! $record->status?->isOpen()) {
                continue;
            }

            $recordId = (int) $record->id;
            $record->delete();
            SystemOperationLogger::cancelled(SystemOperationType::WRONG_DEDUCTION, $recordId);
            $count++;
        }

        return $count;
    }

    /**
     * @param  Collection<int, WrongDeduction>  $records
     */
    public function archiveMany(Collection $records): int
    {
        $count = 0;

        foreach ($records as $record) {
            if ($record->status?->isOpen()) {
                continue;
            }

            $record->delete();
            $count++;
        }

        return $count;
    }
}
