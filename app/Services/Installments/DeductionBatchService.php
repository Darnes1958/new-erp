<?php

namespace App\Services\Installments;

use App\Enums\DeductionBatchEntryType;
use App\Enums\DeductionBatchPostedType;
use App\Enums\DeductionBatchStatus;
use App\Enums\InstallmentDeductionType;
use App\Enums\InstallmentRecordStatus;
use App\Models\DeductionBatch;
use App\Models\DeductionBatchLine;
use App\Models\InstallmentCancelledContract;
use App\Models\InstallmentContract;
use App\Models\InstallmentContractArchive;
use App\Models\WrongDeduction;
use App\Services\SystemOperationLogger;
use App\Support\SystemOperationType;
use App\Support\InstallmentBankScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeductionBatchService
{
    public function __construct(
        protected InstallmentDeductionService $deductionService,
        protected InstallmentCancelledContractService $cancelledContractService,
    ) {}

    public function create(array $data): DeductionBatch
    {
        $banks = InstallmentBankScope::resolveBankIds(
            isset($data['payroll_bank_id']) ? (int) $data['payroll_bank_id'] : null,
            isset($data['installment_bank_id']) ? (int) $data['installment_bank_id'] : null,
        );

        return DeductionBatch::query()->create([
            'payroll_bank_id' => $banks['payroll_bank_id'],
            'installment_bank_id' => $banks['installment_bank_id'],
            'status' => DeductionBatchStatus::Open,
            'batch_date' => $data['batch_date'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @return Collection<int, InstallmentContract>
     */
    public function activeContractsForAccount(DeductionBatch $batch, string $accountNumber): Collection
    {
        $connection = $batch->getConnectionName();

        $query = InstallmentContract::on($connection)
            ->with('customer')
            ->where('bank_account_number', $accountNumber);

        $this->applyBatchBankScope($query, $batch);

        return $query->orderBy('id')->get();
    }

    /**
     * @return Collection<int, InstallmentContractArchive>
     */
    public function archiveContractsForAccount(DeductionBatch $batch, string $accountNumber): Collection
    {
        $connection = $batch->getConnectionName();

        $query = InstallmentContractArchive::on($connection)
            ->with('customer')
            ->where('bank_account_number', $accountNumber);

        $this->applyBatchBankScope($query, $batch);

        return $query->orderBy('id')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InstallmentCancelledContract>
     */
    public function cancelledContractsForAccount(DeductionBatch $batch, string $accountNumber): \Illuminate\Database\Eloquent\Collection
    {
        $connection = $batch->getConnectionName();

        $query = InstallmentCancelledContract::on($connection)
            ->with('customer')
            ->where('bank_account_number', $accountNumber);

        $this->applyBatchBankScope($query, $batch);

        return $query->orderBy('id')->get();
    }

    public function resolveContract(
        DeductionBatch $batch,
        string $accountNumber,
        int|string|null $contractId = null,
    ): array {
        $accountNumber = trim($accountNumber);

        if ($accountNumber === '') {
            throw ValidationException::withMessages([
                'account_number' => 'يجب إدخال رقم الحساب.',
            ]);
        }

        $active = $this->activeContractsForAccount($batch, $accountNumber);

        if ($contractId !== null) {
            $contract = $active->firstWhere('id', (int) $contractId);

            if ($contract) {
                return [
                    'contract' => $contract,
                    'entry_type' => DeductionBatchEntryType::Active,
                ];
            }

            $archiveQuery = InstallmentContractArchive::on($batch->getConnectionName())
                ->whereKey($contractId)
                ->where('bank_account_number', $accountNumber);

            InstallmentBankScope::applyContractScope($archiveQuery, $batch);

            $archive = $archiveQuery->first();

            if ($archive) {
                return [
                    'contract' => $archive,
                    'entry_type' => DeductionBatchEntryType::Archive,
                ];
            }

            $cancelledQuery = InstallmentCancelledContract::on($batch->getConnectionName())
                ->whereKey($contractId)
                ->where('bank_account_number', $accountNumber);

            InstallmentBankScope::applyContractScope($cancelledQuery, $batch);

            $cancelled = $cancelledQuery->first();

            if ($cancelled) {
                return [
                    'contract' => $cancelled,
                    'entry_type' => DeductionBatchEntryType::Cancelled,
                ];
            }

            throw ValidationException::withMessages([
                'installment_contract_id' => 'العقد غير موجود أو لا يتبع هذا الحساب.',
            ]);
        }

        if ($active->count() === 1) {
            return [
                'contract' => $active->first(),
                'entry_type' => DeductionBatchEntryType::Active,
            ];
        }

        if ($active->count() > 1) {
            throw ValidationException::withMessages([
                'installment_contract_id' => 'يوجد أكثر من عقد لهذا الحساب .. يجب اختيار رقم العقد.',
            ]);
        }

        $archives = $this->archiveContractsForAccount($batch, $accountNumber);

        if ($archives->count() === 1) {
            return [
                'contract' => $archives->first(),
                'entry_type' => DeductionBatchEntryType::Archive,
            ];
        }

        if ($archives->count() > 1) {
            throw ValidationException::withMessages([
                'installment_contract_id' => 'يوجد أكثر من عقد أرشيف لهذا الحساب .. يجب اختيار رقم العقد.',
            ]);
        }

        $cancelled = $this->cancelledContractsForAccount($batch, $accountNumber);

        if ($cancelled->count() === 1) {
            return [
                'contract' => $cancelled->first(),
                'entry_type' => DeductionBatchEntryType::Cancelled,
            ];
        }

        if ($cancelled->count() > 1) {
            throw ValidationException::withMessages([
                'installment_contract_id' => 'يوجد أكثر من عقد ملغي لهذا الحساب .. يجب اختيار رقم العقد.',
            ]);
        }

        throw ValidationException::withMessages([
            'account_number' => 'لم يتم العثور على عقد لهذا الحساب في هذه الحافظة.',
        ]);
    }

    public function addLine(
        DeductionBatch $batch,
        Model $contract,
        DeductionBatchEntryType $entryType,
        string $accountNumber,
        float $amount,
        string $deductionDate,
        ?string $notes = null,
    ): DeductionBatchLine {
        if (! $batch->isOpen()) {
            throw ValidationException::withMessages([
                'batch' => 'لا يمكن إضافة أقساط لحافظة مرحّلة.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'يجب إدخال قيمة القسط.',
            ]);
        }

        return $batch->lines()->create([
            'contractable_type' => $contract->getMorphClass(),
            'contractable_id' => $contract->getKey(),
            'account_number' => $accountNumber,
            'amount' => $amount,
            'deduction_date' => $deductionDate,
            'notes' => $notes,
            'entry_type' => $entryType,
            'created_by' => Auth::id(),
        ]);
    }

    public function addWrongLine(
        DeductionBatch $batch,
        string $accountNumber,
        string $name,
        float $amount,
        string $deductionDate,
    ): DeductionBatchLine {
        if (! $batch->isOpen()) {
            throw ValidationException::withMessages([
                'batch' => 'لا يمكن إضافة أقساط لحافظة مرحّلة.',
            ]);
        }

        app(WrongDeductionAccountService::class)->remember($batch, $accountNumber, $name);

        return $batch->lines()->create([
            'contractable_type' => 'wrong_deduction',
            'contractable_id' => 0,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'deduction_date' => $deductionDate,
            'notes' => $name,
            'entry_type' => DeductionBatchEntryType::Wrong,
            'created_by' => Auth::id(),
        ]);
    }

    public function post(DeductionBatch $batch): DeductionBatch
    {
        if (! $batch->isOpen()) {
            throw ValidationException::withMessages([
                'batch' => 'الحافظة مرحّلة مسبقاً.',
            ]);
        }

        $lines = $batch->lines()->orderBy('deduction_date')->orderBy('id')->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => 'لا توجد أقساط في الحافظة.',
            ]);
        }

        return DB::connection($batch->getConnectionName())->transaction(function () use ($batch, $lines): DeductionBatch {
            $stats = [
                'posted_normal_amount' => 0.0,
                'posted_archive_amount' => 0.0,
                'posted_surplus_amount' => 0.0,
                'posted_partial_amount' => 0.0,
                'wrong_amount' => 0.0,
                'posted_cancelled_amount' => 0.0,
            ];

            foreach ($lines as $line) {
                if ($line->entry_type === DeductionBatchEntryType::Wrong) {
                    app(WrongDeductionAccountService::class)->remember(
                        $batch,
                        (string) $line->account_number,
                        (string) $line->notes,
                    );

                    WrongDeduction::on($batch->getConnectionName())->create([
                        'payroll_bank_id' => $batch->payroll_bank_id,
                        'account_number' => $line->account_number,
                        'name' => $line->notes,
                        'amount' => $line->amount,
                        'status' => InstallmentRecordStatus::Open->value,
                        'batch_id' => $batch->id,
                        'deduction_date' => $line->deduction_date ?? $batch->batch_date,
                        'created_by' => Auth::id(),
                    ]);

                    $line->forceFill(['posted_type' => DeductionBatchPostedType::Wrong])->saveQuietly();
                    $stats['wrong_amount'] += (float) $line->amount;

                    continue;
                }

                $contract = $line->contractable;

                if ($line->entry_type === DeductionBatchEntryType::Active) {
                    if (! $contract instanceof InstallmentContract) {
                        throw ValidationException::withMessages([
                            'batch' => 'العقد رقم '.$line->contractable_id.' غير موجود في العقود القائمة.',
                        ]);
                    }
                } elseif ($line->entry_type === DeductionBatchEntryType::Cancelled) {
                    if (! $contract instanceof InstallmentCancelledContract) {
                        throw ValidationException::withMessages([
                            'batch' => 'العقد رقم '.$line->contractable_id.' غير موجود في العقود الملغية.',
                        ]);
                    }

                    $result = $this->cancelledContractService->recordDeduction(
                        $contract,
                        $line->deduction_date,
                        (float) $line->amount,
                        InstallmentDeductionType::Bank->value,
                        $line->notes,
                        $batch->id,
                    );

                    $line->forceFill(['posted_type' => $result['posted_type']])->saveQuietly();
                    $stats['posted_cancelled_amount'] += (float) $line->amount;

                    continue;
                } elseif (! $contract instanceof InstallmentContractArchive) {
                    throw ValidationException::withMessages([
                        'batch' => 'العقد رقم '.$line->contractable_id.' غير موجود في الأرشيف.',
                    ]);
                }

                $result = $this->deductionService->record(
                    $contract,
                    $line->deduction_date,
                    (float) $line->amount,
                    InstallmentDeductionType::Bank->value,
                    $line->notes,
                    $batch->id,
                );

                $line->forceFill(['posted_type' => $result['posted_type']])->saveQuietly();

                $amount = (float) $line->amount;
                match ($result['posted_type']) {
                    DeductionBatchPostedType::Normal => $stats['posted_normal_amount'] += $amount,
                    DeductionBatchPostedType::Archive => $stats['posted_archive_amount'] += $amount,
                    DeductionBatchPostedType::Surplus => $stats['posted_surplus_amount'] += $amount,
                    DeductionBatchPostedType::Partial => $stats['posted_partial_amount'] += $amount,
                    DeductionBatchPostedType::Cancelled => $stats['posted_cancelled_amount'] += $amount,
                    default => null,
                };
            }

            $batch->forceFill([
                'status' => DeductionBatchStatus::Posted,
                'from_date' => $lines->min('deduction_date'),
                'to_date' => $lines->max('deduction_date'),
                'total_amount' => $lines->sum('amount'),
                ...$stats,
            ])->saveQuietly();

            return $batch->refresh();
        });
    }

    public function deleteOpenBatch(DeductionBatch $batch): void
    {
        if (! $batch->isOpen()) {
            throw ValidationException::withMessages([
                'batch' => 'لا يمكن حذف حافظة مرحّلة.',
            ]);
        }

        DB::connection($batch->getConnectionName())->transaction(function () use ($batch): void {
            $batchId = (int) $batch->id;
            $batch->lines()->delete();
            $batch->delete();

            SystemOperationLogger::cancelled(SystemOperationType::DEDUCTION_BATCH, $batchId);
        });
    }

    protected function applyBatchBankScope($query, DeductionBatch $batch): void
    {
        InstallmentBankScope::applyContractScope($query, $batch);
    }
}
