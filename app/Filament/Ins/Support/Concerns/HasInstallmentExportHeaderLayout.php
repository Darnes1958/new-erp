<?php

namespace App\Filament\Ins\Support\Concerns;

trait HasInstallmentExportHeaderLayout
{
    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-ins-export-header-page'];
    }
}
