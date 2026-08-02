<?php

namespace App\Observers;

use App\Models\InstallmentContract;
use App\Models\InstallmentDeduction;
use App\Services\Installments\InstallmentContractMetricsService;

class InstallmentDeductionObserver
{
    public function __construct(
        protected InstallmentContractMetricsService $metrics,
    ) {}

    public function saved(InstallmentDeduction $deduction): void
    {
        $this->recalculate($deduction->installmentContract);
    }

    public function deleted(InstallmentDeduction $deduction): void
    {
        $contract = InstallmentContract::on($deduction->getConnectionName())
            ->find($deduction->installment_contract_id);

        $this->recalculate($contract);
    }

    protected function recalculate(?InstallmentContract $contract): void
    {
        if ($contract !== null) {
            $this->metrics->recalculate($contract);
        }
    }
}
