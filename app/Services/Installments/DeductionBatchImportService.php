<?php

namespace App\Services\Installments;

use App\Enums\DeductionBatchEntryType;
use App\Imports\Installments\DeductionBatchExcelImport;
use App\Models\BankExcelImportSetting;
use App\Models\DeductionBatch;
use App\Models\DeductionImportDateRange;
use App\Models\DeductionImportStagingLine;
use App\Support\InstallmentBankScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class DeductionBatchImportService
{
    public const SESSION_KEY = 'deduction_import_session';

    public function __construct(
        protected DeductionBatchService $batchService,
    ) {}

    /**
     * @return array{
     *     session_id: string,
     *     mode: string,
     *     payroll_bank_id: int,
     *     installment_bank_id: int|null,
     *     heading_row: int,
     *     bank_excel_import_setting_id: int|null
     * }
     */
    public function beginSession(array $data): array
    {
        $mode = $data['import_mode'] ?? 'fixed';

        if ($mode === 'configured') {
            $setting = BankExcelImportSetting::query()->findOrFail($data['bank_excel_import_setting_id']);
            $banks = InstallmentBankScope::resolveBankIds($setting->payroll_bank_id, null);

            $context = [
                'session_id' => (string) Str::uuid(),
                'mode' => 'configured',
                'payroll_bank_id' => $banks['payroll_bank_id'],
                'installment_bank_id' => $banks['installment_bank_id'],
                'heading_row' => (int) $setting->heading_row,
                'bank_excel_import_setting_id' => $setting->id,
            ];
        } else {
            $banks = InstallmentBankScope::resolveBankIds(
                isset($data['payroll_bank_id']) ? (int) $data['payroll_bank_id'] : null,
                isset($data['installment_bank_id']) ? (int) $data['installment_bank_id'] : null,
            );

            $context = [
                'session_id' => (string) Str::uuid(),
                'mode' => 'fixed',
                'payroll_bank_id' => $banks['payroll_bank_id'],
                'installment_bank_id' => $banks['installment_bank_id'],
                'heading_row' => (int) ($data['heading_row'] ?? 1),
                'bank_excel_import_setting_id' => null,
            ];
        }

        $this->clearStagingForSession($context['session_id']);
        session([self::SESSION_KEY => $context]);

        return $context;
    }

    public function currentSession(): ?array
    {
        $session = session(self::SESSION_KEY);

        return is_array($session) ? $session : null;
    }

    public function importFile(string $absolutePath): int
    {
        $context = $this->currentSession();

        if ($context === null) {
            throw ValidationException::withMessages([
                'file' => 'يجب إعداد الاستيراد أولاً (المصرف وسطر العنوان).',
            ]);
        }

        $this->clearStagingForSession($context['session_id']);

        $columnMap = $this->columnMapForContext($context);

        Excel::import(
            new DeductionBatchExcelImport(
                $context['session_id'],
                $context['payroll_bank_id'],
                $context['installment_bank_id'],
                $context['heading_row'],
                $columnMap,
            ),
            $absolutePath,
        );

        $count = DeductionImportStagingLine::query()
            ->where('import_session_id', $context['session_id'])
            ->count();

        if ($count === 0) {
            throw ValidationException::withMessages([
                'file' => 'لم يتم استيراد أي سطر. تحقق من أسماء الأعمدة ورقم سطر العنوان.',
            ]);
        }

        $this->assertNoDateOverlap(
            (int) $context['payroll_bank_id'],
            $this->sessionDateRange($context['session_id']),
        );

        $this->recordPendingDateRange($context);

        return $count;
    }

    public function transferToBatch(?string $notes = null): DeductionBatch
    {
        $context = $this->currentSession();

        if ($context === null) {
            throw ValidationException::withMessages([
                'batch' => 'لا توجد بيانات مستوردة.',
            ]);
        }

        $lines = DeductionImportStagingLine::query()
            ->where('import_session_id', $context['session_id'])
            ->whereNull('deduction_batch_id')
            ->orderBy('account_number')
            ->orderBy('row_number')
            ->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => 'لا توجد بيانات للترحيل.',
            ]);
        }

        return DB::connection($lines->first()->getConnectionName())->transaction(function () use ($context, $lines, $notes): DeductionBatch {
            $batch = $this->batchService->create([
                'payroll_bank_id' => $context['payroll_bank_id'],
                'installment_bank_id' => $context['installment_bank_id'],
                'batch_date' => now()->toDateString(),
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $this->transferLine($batch, $line);
            }

            DeductionImportStagingLine::query()
                ->where('import_session_id', $context['session_id'])
                ->update(['deduction_batch_id' => $batch->id]);

            $this->finalizeDateRange($context, $batch);

            session()->forget(self::SESSION_KEY);

            return $batch->refresh();
        });
    }

    public function clearCurrentSession(): void
    {
        $context = $this->currentSession();

        if ($context !== null) {
            $this->clearStagingForSession($context['session_id']);
        }

        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array{from_date: string, to_date: string}
     */
    protected function sessionDateRange(string $sessionId): array
    {
        $query = DeductionImportStagingLine::query()->where('import_session_id', $sessionId);

        return [
            'from_date' => (string) $query->min('deduction_date'),
            'to_date' => (string) $query->max('deduction_date'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    protected function columnMapForContext(array $context): array
    {
        if ($context['mode'] === 'configured') {
            $setting = BankExcelImportSetting::query()->findOrFail($context['bank_excel_import_setting_id']);

            return [
                'account_number' => $setting->column_account_number,
                'customer_name' => $setting->column_customer_name,
                'amount' => $setting->column_amount,
                'deduction_date' => $setting->column_deduction_date,
            ];
        }

        return [
            'account_number' => 'acc',
            'customer_name' => 'name',
            'amount' => 'ksm',
            'deduction_date' => 'ksm_date',
        ];
    }

    protected function clearStagingForSession(string $sessionId): void
    {
        DeductionImportStagingLine::query()
            ->where('import_session_id', $sessionId)
            ->whereNull('deduction_batch_id')
            ->delete();
    }

    /**
     * @param  array{from_date: string, to_date: string}  $range
     */
    protected function assertNoDateOverlap(int $payrollBankId, array $range): void
    {
        $fromDate = $range['from_date'];
        $toDate = $range['to_date'];

        $importOverlap = DeductionImportDateRange::query()
            ->where('payroll_bank_id', $payrollBankId)
            ->where(function ($query) use ($fromDate, $toDate): void {
                $query
                    ->whereBetween('from_date', [$fromDate, $toDate])
                    ->orWhereBetween('to_date', [$fromDate, $toDate])
                    ->orWhere(function ($query) use ($fromDate, $toDate): void {
                        $query->where('from_date', '<=', $fromDate)
                            ->where('to_date', '>=', $toDate);
                    });
            })
            ->exists();

        if ($importOverlap) {
            $this->clearCurrentSession();

            throw ValidationException::withMessages([
                'file' => 'يوجد تداخل في تاريخ الحافظة مع حافظة سابقة لنفس المصرف.',
            ]);
        }

        $batchOverlap = DeductionBatch::query()
            ->where('payroll_bank_id', $payrollBankId)
            ->whereNotNull('from_date')
            ->whereNotNull('to_date')
            ->where(function ($query) use ($fromDate, $toDate): void {
                $query
                    ->whereBetween('from_date', [$fromDate, $toDate])
                    ->orWhereBetween('to_date', [$fromDate, $toDate])
                    ->orWhere(function ($query) use ($fromDate, $toDate): void {
                        $query->where('from_date', '<=', $fromDate)
                            ->where('to_date', '>=', $toDate);
                    });
            })
            ->exists();

        if ($batchOverlap) {
            $this->clearCurrentSession();

            throw ValidationException::withMessages([
                'file' => 'يوجد تداخل في تاريخ الحافظة مع حافظة مرحّلة سابقة لنفس المصرف.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function recordPendingDateRange(array $context): void
    {
        $range = $this->sessionDateRange($context['session_id']);

        DeductionImportDateRange::query()
            ->where('payroll_bank_id', $context['payroll_bank_id'])
            ->whereNull('deduction_batch_id')
            ->delete();

        DeductionImportDateRange::query()->create([
            'payroll_bank_id' => $context['payroll_bank_id'],
            'from_date' => $range['from_date'],
            'to_date' => $range['to_date'],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function finalizeDateRange(array $context, DeductionBatch $batch): void
    {
        DeductionImportDateRange::query()
            ->where('payroll_bank_id', $context['payroll_bank_id'])
            ->whereNull('deduction_batch_id')
            ->update(['deduction_batch_id' => $batch->id]);
    }

    protected function transferLine(DeductionBatch $batch, DeductionImportStagingLine $line): void
    {
        $resolved = $this->resolveLine($batch, $line);

        if ($resolved === null) {
            $this->batchService->addWrongLine(
                $batch,
                $line->account_number,
                (string) ($line->customer_name ?? ''),
                (float) $line->amount,
                $line->deduction_date->format('Y-m-d'),
            );

            $line->forceFill(['match_status' => 'wrong'])->saveQuietly();

            return;
        }

        $this->batchService->addLine(
            $batch,
            $resolved['contract'],
            $resolved['entry_type'],
            $line->account_number,
            (float) $line->amount,
            $line->deduction_date->format('Y-m-d'),
            $line->customer_name,
        );

        $line->forceFill(['match_status' => $resolved['entry_type']->name])->saveQuietly();
    }

    /**
     * @return array{contract: Model, entry_type: DeductionBatchEntryType}|null
     */
    protected function resolveLine(DeductionBatch $batch, DeductionImportStagingLine $line): ?array
    {
        try {
            return $this->batchService->resolveContract($batch, $line->account_number);
        } catch (ValidationException $exception) {
            $messages = $exception->errors();

            if (isset($messages['installment_contract_id'])) {
                $contract = $this->matchByAmount(
                    $this->batchService->activeContractsForAccount($batch, $line->account_number),
                    (float) $line->amount,
                ) ?? $this->batchService->activeContractsForAccount($batch, $line->account_number)->first();

                if ($contract) {
                    return [
                        'contract' => $contract,
                        'entry_type' => DeductionBatchEntryType::Active,
                    ];
                }
            }

            if (isset($messages['account_number'])) {
                return $this->resolveArchiveOrCancelled($batch, $line);
            }

            return null;
        }
    }

    /**
     * @return array{contract: Model, entry_type: DeductionBatchEntryType}|null
     */
    protected function resolveArchiveOrCancelled(DeductionBatch $batch, DeductionImportStagingLine $line): ?array
    {
        $archives = $this->batchService->archiveContractsForAccount($batch, $line->account_number);

        if ($archives->count() === 1) {
            return [
                'contract' => $archives->first(),
                'entry_type' => DeductionBatchEntryType::Archive,
            ];
        }

        if ($archives->count() > 1) {
            $matched = $this->matchByAmount($archives, (float) $line->amount) ?? $archives->first();

            return [
                'contract' => $matched,
                'entry_type' => DeductionBatchEntryType::Archive,
            ];
        }

        $cancelled = $this->batchService->cancelledContractsForAccount($batch, $line->account_number);

        if ($cancelled->count() === 1) {
            return [
                'contract' => $cancelled->first(),
                'entry_type' => DeductionBatchEntryType::Cancelled,
            ];
        }

        if ($cancelled->count() > 1) {
            $matched = $this->matchByAmount($cancelled, (float) $line->amount) ?? $cancelled->first();

            return [
                'contract' => $matched,
                'entry_type' => DeductionBatchEntryType::Cancelled,
            ];
        }

        return null;
    }

    protected function matchByAmount(EloquentCollection $contracts, float $amount): ?Model
    {
        return $contracts->first(
            fn (Model $contract): bool => abs((float) $contract->installment_amount - $amount) < 0.001,
        );
    }
}
