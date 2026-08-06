<?php

namespace App\Filament\Finance\Pages\Reports\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithFinanceMovementReportExports
{
    /**
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>|null
     */
    protected function exportRows(string $emptyMessage = 'لا توجد بيانات للتصدير'): ?Collection
    {
        if (! $this->validateReportFilters()) {
            return null;
        }

        $rows = $this->buildReportQuery()
            ->reorder()
            ->orderBy($this->exportSortColumn())
            ->orderBy('id')
            ->get();

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

    abstract protected function validateReportFilters(): bool;

    abstract protected function exportSortColumn(): string;
}
