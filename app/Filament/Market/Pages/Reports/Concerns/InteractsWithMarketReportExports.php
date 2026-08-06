<?php

namespace App\Filament\Market\Pages\Reports\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithMarketReportExports
{
    /**
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>|null
     */
    protected function exportRows(string $emptyMessage = 'لا توجد بيانات للتصدير'): ?Collection
    {
        if (! $this->validateReportFilters()) {
            return null;
        }

        $rows = $this->buildExportQuery()->get();

        if ($rows->isEmpty()) {
            Notification::make()
                ->title($emptyMessage)
                ->warning()
                ->send();

            return null;
        }

        return $rows;
    }

    abstract protected function buildReportQuery(): Builder;

    abstract protected function buildExportQuery(): Builder;

    abstract protected function validateReportFilters(): bool;
}
