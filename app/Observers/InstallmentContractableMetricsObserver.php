<?php

namespace App\Observers;

use App\Models\InstallmentContract;
use App\Models\InstallmentSurplus;
use App\Models\InstallmentSuspended;
use App\Services\Installments\InstallmentContractMetricsService;

class InstallmentContractableMetricsObserver
{
    public function __construct(
        protected InstallmentContractMetricsService $metrics,
    ) {}

    public function saved(InstallmentSurplus|InstallmentSuspended $entry): void
    {
        $this->recalculate($entry);
    }

    public function deleted(InstallmentSurplus|InstallmentSuspended $entry): void
    {
        $this->recalculate($entry);
    }

    protected function recalculate(InstallmentSurplus|InstallmentSuspended $entry): void
    {
        $contractable = $entry->contractable;

        if ($contractable instanceof InstallmentContract) {
            $this->metrics->recalculate($contractable);
        }
    }
}
