<?php

namespace App\Console\Commands;

use App\Services\Conversion\InsArchivedContractIdRepairService;
use App\Support\Conversion\LegacyConnectionNaming;
use Illuminate\Console\Command;
use RuntimeException;

class InsRepairArchivedContractIdsCommand extends Command
{
    protected $signature = 'ins:repair-archived-contract-ids
        {legacy : Legacy INS company connection name (e.g. Anwar, BenTaher)}
        {--dry-run : Count rows that would be remapped without changing data}';

    protected $description = 'Set archived installment contract ids to legacy MainArc.no (stable contract number) instead of surrogate MainArc.id';

    public function handle(InsArchivedContractIdRepairService $service): int
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
            ['ستُصحَّح أرقام الأرشيف', 'كانت صحيحة', 'أُزيحت لتفادي عقد قائم'],
            [[$result['remap'], $result['unchanged'], $result['offset_for_live']]],
        );

        if ($dryRun) {
            $this->comment('لم يُغيَّر شيء. أعد الأمر بدون --dry-run للتنفيذ.');
        }

        return self::SUCCESS;
    }
}
