<?php

namespace App\Console\Commands;

use App\Services\Installments\InstallmentContractMetricsService;
use Illuminate\Console\Command;

class ErpRefreshInstallmentLateCountsCommand extends Command
{
    protected $signature = 'erp:refresh-installment-late-counts';

    protected $description = 'Refresh late_amount (overdue installment count) on all active contracts';

    public function handle(InstallmentContractMetricsService $metrics): int
    {
        $updated = $metrics->refreshLateCounts();

        $this->info("Updated late_amount on {$updated} contract(s).");

        return self::SUCCESS;
    }
}
