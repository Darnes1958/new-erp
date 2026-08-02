<?php

namespace App\Console\Commands;

use App\Enums\DeductionBatchStatus;
use App\Models\DeductionBatch;
use App\Models\InstallmentContract;
use App\Support\InstallmentBankScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ErpBackfillDeductionBatchesCommand extends Command
{
    protected $signature = 'erp:backfill-deduction-batches
        {source? : Legacy company DB connection (e.g. Motafoek)}
        {--target= : Target company connection (defaults to all company DBs)}';

    protected $description = 'Backfill deduction batch headers/lines from legacy hafithas or from batch lines';

    public function handle(): int
    {
        $source = $this->argument('source');
        $targets = $this->option('target')
            ? [$this->option('target')]
            : config('erp.company_connections', []);

        $updated = 0;

        foreach ($targets as $target) {
            if (! CompanyConnections::isValid($target)) {
                $this->warn("Skipping invalid connection [{$target}]");

                continue;
            }

            if (! $this->targetHasBatchTables($target)) {
                $this->warn("Skipping [{$target}] — deduction_batches not found");

                continue;
            }

            $updated += $this->backfillConnection($target, $source);
        }

        $this->info("Backfilled {$updated} batch(es).");

        return self::SUCCESS;
    }

    protected function backfillConnection(string $target, ?string $source): int
    {
        $count = 0;

        DeductionBatch::on($target)->orderBy('id')->chunkById(50, function ($batches) use ($target, $source, &$count): void {
            foreach ($batches as $batch) {
                if ($source && $this->legacyHasHafithas($source)) {
                    $this->backfillFromLegacy($batch, $source);
                }

                $this->backfillFromLines($batch, $target);
                $count++;
            }
        });

        return $count;
    }

    protected function backfillFromLegacy(DeductionBatch $batch, string $source): void
    {
        $legacy = DB::connection($source)->table('hafithas')->where('id', $batch->id)->first();

        if (! $legacy) {
            return;
        }

        $batch->forceFill([
            'payroll_bank_id' => $legacy->taj_id ?? $batch->payroll_bank_id,
            'installment_bank_id' => $batch->installment_bank_id
                ?? ($legacy->taj_id ? InstallmentBankScope::branchForPayroll((int) $legacy->taj_id, $batch->getConnectionName())?->id : null),
            'status' => (int) ($legacy->status ?? 0) === 1
                ? DeductionBatchStatus::Posted
                : DeductionBatchStatus::Open,
            'from_date' => $legacy->from_date ?? $batch->from_date,
            'to_date' => $legacy->to_date ?? $batch->to_date,
            'total_amount' => $legacy->tot ?? $batch->total_amount,
            'posted_normal_amount' => $legacy->morahel ?? 0,
            'posted_archive_amount' => $legacy->over_kst_arc ?? 0,
            'posted_surplus_amount' => $legacy->over_kst ?? 0,
            'posted_partial_amount' => $legacy->half ?? 0,
            'wrong_amount' => $legacy->wrong_kst ?? 0,
        ])->saveQuietly();

        $legacyLines = DB::connection($source)
            ->table('hafitha_trans')
            ->where('hafitha_id', $batch->id)
            ->get();

        foreach ($legacyLines as $legacyLine) {
            DB::connection($batch->getConnectionName())
                ->table('deduction_batch_lines')
                ->where('id', $legacyLine->id)
                ->update([
                    'account_number' => $legacyLine->acc,
                    'deduction_date' => $legacyLine->ksm_date,
                    'entry_type' => (int) ($legacyLine->haf_kst_type ?? 1),
                    'posted_type' => (int) ($legacyLine->status ?? $legacyLine->haf_kst_type ?? null),
                    'created_by' => $legacyLine->user_id,
                ]);
        }
    }

    protected function backfillFromLines(DeductionBatch $batch, string $target): void
    {
        if ($batch->payroll_bank_id === null && $batch->installment_bank_id === null) {
            $contractId = DB::connection($target)
                ->table('deduction_batch_lines')
                ->where('deduction_batch_id', $batch->id)
                ->where('contractable_type', 'installment_contract')
                ->value('contractable_id');

            if ($contractId) {
                $contract = InstallmentContract::on($target)->find($contractId);

            if ($contract) {
                $batch->forceFill([
                    'payroll_bank_id' => $contract->payroll_bank_id,
                    'installment_bank_id' => $contract->installment_bank_id
                        ?? InstallmentBankScope::branchForPayroll((int) $contract->payroll_bank_id, $target)?->id,
                ])->saveQuietly();
            }
            }
        }

        if ((float) $batch->total_amount <= 0) {
            $linesSum = (float) DB::connection($target)
                ->table('deduction_batch_lines')
                ->where('deduction_batch_id', $batch->id)
                ->sum('amount');

            if ($linesSum > 0) {
                $batch->forceFill(['total_amount' => $linesSum])->saveQuietly();
            }
        }
    }

    protected function targetHasBatchTables(string $connection): bool
    {
        try {
            return DB::connection($connection)->getSchemaBuilder()->hasTable('deduction_batches');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function legacyHasHafithas(string $source): bool
    {
        try {
            return DB::connection($source)->getSchemaBuilder()->hasTable('hafithas');
        } catch (\Throwable) {
            return false;
        }
    }
}
