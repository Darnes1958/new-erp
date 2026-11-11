<?php

namespace App\Console\Commands;

use App\Services\Conversion\InsArchivedStopRepairService;
use App\Support\Conversion\LegacyConnectionNaming;
use Illuminate\Console\Command;
use RuntimeException;

class InsRepairArchivedStopsCommand extends Command
{
    protected $signature = 'ins:repair-archived-stops
        {legacy : Legacy INS company connection name (e.g. Anwar, BenTaher)}
        {--dry-run : Count rows that would move without changing data}';

    protected $description = 'Move INS stop letters that belong to archived contracts out of stops-without-contract into installment_stops';

    public function handle(InsArchivedStopRepairService $service): int
    {
        $legacy = LegacyConnectionNaming::legacyName((string) $this->argument('legacy'));
        $target = LegacyConnectionNaming::targetName($legacy);
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? 'Dry-run: ' : '')."[{$legacy}] → [{$target}]");

        try {
            $result = $service->repair($legacy, $dryRun);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['نُقلت إلى رسائل الإيقاف', 'كانت مرتبطة مسبقاً فحُذفت من بدون عقد', 'بقيت بدون عقد'],
            [[$result['moved'], $result['already_linked'], $result['skipped']]],
        );

        if ($dryRun) {
            $this->comment('لم يُغيَّر شيء. أعد الأمر بدون --dry-run للتنفيذ.');
        }

        return self::SUCCESS;
    }
}
