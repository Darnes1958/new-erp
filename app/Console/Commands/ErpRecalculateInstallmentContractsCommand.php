<?php

namespace App\Console\Commands;

use App\Services\Installments\InstallmentContractMetricsService;
use Illuminate\Console\Command;

class ErpRecalculateInstallmentContractsCommand extends Command
{
    protected $signature = 'erp:recalculate-installment-contracts
        {contract? : Optional contract id to recalculate only one record}';

    protected $description = 'Recalculate denormalized metrics on installment contracts from source data';

    public function handle(InstallmentContractMetricsService $metrics): int
    {
        $contractId = $this->argument('contract');
        $id = $contractId !== null ? (int) $contractId : null;

        if ($id !== null && $id <= 0) {
            $this->error('Contract id must be a positive integer.');

            return self::FAILURE;
        }

        $count = $metrics->recalculateAll($id);

        $this->info("Recalculated {$count} contract(s).");

        return self::SUCCESS;
    }
}
