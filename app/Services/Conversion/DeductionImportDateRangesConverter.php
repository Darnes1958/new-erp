<?php

namespace App\Services\Conversion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DeductionImportDateRangesConverter
{
    public function __construct(
        protected string $source,
        protected string $target,
    ) {}

    public function convert(bool $replace = true): int
    {
        $this->assertConnections();

        if (! Schema::connection($this->target)->hasTable('deduction_import_date_ranges')) {
            throw new RuntimeException("Target table [deduction_import_date_ranges] was not found on [{$this->target}].");
        }

        $sourceTable = $this->legacyTableName();

        if ($sourceTable === null) {
            $this->log("Skipping deduction_import_date_ranges: dateofexcels not found on [{$this->source}].");

            return 0;
        }

        $legacyRows = DB::connection($this->source)
            ->table($sourceTable)
            ->orderBy('id')
            ->get();

        if ($legacyRows->isEmpty()) {
            $this->log('No rows found in ['.$sourceTable.'].');

            return 0;
        }

        $payrollBankIds = DB::connection($this->target)
            ->table('payroll_banks')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $batchIndex = $this->batchIndex();

        $rows = [];

        foreach ($legacyRows as $legacyRow) {
            $payrollBankId = (int) ($legacyRow->taj_id ?? 0);

            if ($payrollBankId === 0 || ! $payrollBankIds->has($payrollBankId)) {
                continue;
            }

            $fromDate = $this->dateString($legacyRow->date_begin ?? null);
            $toDate = $this->dateString($legacyRow->date_end ?? null);

            if ($fromDate === null || $toDate === null) {
                continue;
            }

            $rows[] = [
                'id' => (int) $legacyRow->id,
                'payroll_bank_id' => $payrollBankId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'deduction_batch_id' => $this->resolveBatchId($batchIndex, $payrollBankId, $fromDate, $toDate),
                'created_by' => isset($legacyRow->user_id) ? (int) $legacyRow->user_id : null,
                'created_at' => $legacyRow->created_at ?? now(),
                'updated_at' => $legacyRow->updated_at ?? now(),
            ];
        }

        if ($rows === []) {
            $this->log('No deduction_import_date_ranges rows matched on ['.$this->target.'].');

            return 0;
        }

        if ($replace) {
            DB::connection($this->target)->table('deduction_import_date_ranges')->delete();
        }

        $this->insertWithIdentity('deduction_import_date_ranges', $rows);

        $this->log('Imported '.count($rows).' deduction_import_date_ranges row(s) into ['.$this->target.'].');

        return count($rows);
    }

    protected function legacyTableName(): ?string
    {
        foreach (['dateofexcels', 'date_of_excels'] as $table) {
            if (Schema::connection($this->source)->hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    protected function batchIndex(): array
    {
        if (! Schema::connection($this->target)->hasTable('deduction_batches')) {
            return [];
        }

        $index = [];

        $batches = DB::connection($this->target)
            ->table('deduction_batches')
            ->whereNotNull('from_date')
            ->whereNotNull('to_date')
            ->orderByDesc('id')
            ->get(['id', 'payroll_bank_id', 'from_date', 'to_date']);

        foreach ($batches as $batch) {
            $fromDate = $this->dateString($batch->from_date);
            $toDate = $this->dateString($batch->to_date);

            if ($fromDate === null || $toDate === null) {
                continue;
            }

            $key = (int) $batch->payroll_bank_id.'|'.$fromDate.'|'.$toDate;

            $index[$key] ??= (int) $batch->id;
        }

        return $index;
    }

    /**
     * @param  array<string, int>  $batchIndex
     */
    protected function resolveBatchId(array $batchIndex, int $payrollBankId, string $fromDate, string $toDate): ?int
    {
        $key = $payrollBankId.'|'.$fromDate.'|'.$toDate;

        return $batchIndex[$key] ?? null;
    }

    protected function dateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        return substr($string, 0, 10);
    }

    protected function insertWithIdentity(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::connection($this->target)->transaction(function () use ($table, $chunk): void {
                DB::connection($this->target)->unprepared("SET IDENTITY_INSERT [{$table}] ON");

                foreach ($chunk as $row) {
                    DB::connection($this->target)->table($table)->insert($row);
                }

                DB::connection($this->target)->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            });
        }
    }

    protected function assertConnections(): void
    {
        foreach ([$this->source, $this->target] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
