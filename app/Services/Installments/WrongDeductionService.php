<?php

namespace App\Services\Installments;

use App\Enums\DeductionBatchEntryType;
use App\Enums\DeductionBatchPostedType;
use App\Enums\InstallmentDeductionType;
use App\Enums\InstallmentRecordStatus;
use App\Models\DeductionBatch;
use App\Models\DeductionBatchLine;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\WrongDeduction;
use App\Models\WrongDeductionAccount;
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
                ->orderBy('deduction_date')
                ->orderBy('id')
                ->get();

            $usedLineIds = [];
            $batchIds = [];

            foreach ($siblings as $wrong) {
                $result = $this->deductions->record(
                    $contract,
                    $wrong->deduction_date ?? now(),
                    (float) $wrong->amount,
                    InstallmentDeductionType::Bank->value,
                    notes: 'تصحيح قسط بالخطأ',
                    batchId: $wrong->batch_id,
                );

                $wrong->forceFill(['status' => InstallmentRecordStatus::Corrected])->save();

                $line = $this->matchingWrongBatchLine($wrong, $usedLineIds);

                if ($line) {
                    $usedLineIds[] = (int) $line->id;
                    $this->relinkLine($line, $contract, $result['posted_type']);
                    $batchIds[(int) $line->deduction_batch_id] = true;
                } elseif ($wrong->batch_id) {
                    $batchIds[(int) $wrong->batch_id] = true;
                }
            }

            $this->applyCorrectAccountNumber($contract, (string) $record->account_number, $record->payroll_bank_id);

            foreach (array_keys($batchIds) as $batchId) {
                $batch = DeductionBatch::query()->find($batchId);

                if ($batch) {
                    $this->refreshPostedBatchTotals($batch);
                }
            }

            $this->metrics->recalculate($contract->refresh());
        });
    }

    /**
     * @param  list<int>  $usedLineIds
     */
    protected function matchingWrongBatchLine(WrongDeduction $wrong, array $usedLineIds): ?DeductionBatchLine
    {
        if (! $wrong->batch_id) {
            return null;
        }

        $query = DeductionBatchLine::query()
            ->where('deduction_batch_id', $wrong->batch_id)
            ->whereRaw('LTRIM(RTRIM(account_number)) = ?', [trim((string) $wrong->account_number)])
            ->where(function ($query): void {
                $query->where('entry_type', DeductionBatchEntryType::Wrong)
                    ->orWhere('posted_type', DeductionBatchPostedType::Wrong);
            })
            ->when(
                $usedLineIds !== [],
                fn ($query) => $query->whereNotIn('id', $usedLineIds),
            );

        return $query->orderBy('deduction_date')->orderBy('id')->first();
    }

    public function relinkLine(
        DeductionBatchLine $line,
        InstallmentContract $contract,
        ?DeductionBatchPostedType $postedType = null,
    ): void {
        $line->forceFill([
            'contractable_type' => $contract->getMorphClass(),
            'contractable_id' => $contract->getKey(),
            'entry_type' => DeductionBatchEntryType::Active,
            'posted_type' => $postedType,
        ])->save();
    }

    public function markAccountCorrected(string $accountNumber, ?int $payrollBankId = null): void
    {
        WrongDeduction::query()
            ->whereRaw('LTRIM(RTRIM(account_number)) = ?', [trim($accountNumber)])
            ->whereIn('status', [InstallmentRecordStatus::Legacy, InstallmentRecordStatus::Open])
            ->when(
                $payrollBankId,
                fn ($query) => $query->where('payroll_bank_id', $payrollBankId),
            )
            ->update(['status' => InstallmentRecordStatus::Corrected->value]);
    }

    public function applyCorrectAccountNumber(
        InstallmentContract $contract,
        string $correctAccount,
        ?int $payrollBankId = null,
    ): void {
        $correctAccount = trim($correctAccount);

        if ($correctAccount === '') {
            return;
        }

        $oldAccount = trim((string) $contract->bank_account_number);
        $payrollBankId ??= $contract->payroll_bank_id;
        $connection = $contract->getConnectionName();

        $contract->forceFill(['bank_account_number' => $correctAccount])->saveQuietly();

        InstallmentContractArchive::on($connection)
            ->whereKey($contract->id)
            ->update(['bank_account_number' => $correctAccount]);

        InstallmentCancelledContract::on($connection)
            ->whereKey($contract->id)
            ->update(['bank_account_number' => $correctAccount]);

        if ($oldAccount !== '' && strcasecmp($oldAccount, $correctAccount) !== 0) {
            $related = function ($query) use ($contract, $oldAccount, $payrollBankId) {
                $query->where('bank_account_number', $oldAccount)
                    ->where('customer_id', $contract->customer_id)
                    ->when($payrollBankId, fn ($inner) => $inner->where('payroll_bank_id', $payrollBankId));
            };

            InstallmentContract::on($connection)
                ->whereKeyNot($contract->id)
                ->where($related)
                ->update(['bank_account_number' => $correctAccount]);

            InstallmentContractArchive::on($connection)
                ->where($related)
                ->update(['bank_account_number' => $correctAccount]);

            InstallmentCancelledContract::on($connection)
                ->where($related)
                ->update(['bank_account_number' => $correctAccount]);

            DeductionBatchLine::on($connection)
                ->where('contractable_id', $contract->id)
                ->whereIn('contractable_type', [$contract->getMorphClass(), InstallmentContract::class])
                ->where('account_number', $oldAccount)
                ->update(['account_number' => $correctAccount]);
        }

        if ($payrollBankId) {
            WrongDeductionAccount::on($connection)
                ->where('payroll_bank_id', $payrollBankId)
                ->where('account_number', $correctAccount)
                ->delete();
        }
    }

    public function refreshPostedBatchTotals(DeductionBatch $batch): void
    {
        if ($batch->isOpen()) {
            return;
        }

        $stats = [
            'posted_normal_amount' => 0.0,
            'posted_archive_amount' => 0.0,
            'posted_surplus_amount' => 0.0,
            'posted_partial_amount' => 0.0,
            'wrong_amount' => 0.0,
            'posted_cancelled_amount' => 0.0,
        ];

        foreach ($batch->lines()->get() as $line) {
            $amount = (float) $line->amount;

            match ($line->posted_type) {
                DeductionBatchPostedType::Normal => $stats['posted_normal_amount'] += $amount,
                DeductionBatchPostedType::Archive => $stats['posted_archive_amount'] += $amount,
                DeductionBatchPostedType::Surplus => $stats['posted_surplus_amount'] += $amount,
                DeductionBatchPostedType::Partial => $stats['posted_partial_amount'] += $amount,
                DeductionBatchPostedType::Wrong => $stats['wrong_amount'] += $amount,
                DeductionBatchPostedType::Cancelled => $stats['posted_cancelled_amount'] += $amount,
                default => null,
            };
        }

        $batch->forceFill($stats)->saveQuietly();
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
