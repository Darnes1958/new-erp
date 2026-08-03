<?php

namespace App\Filament\Ins\Support;

use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InstallmentListPrintActions
{
    public static function wrongDeductionsPdf(): Action
    {
        return Action::make('printWrongDeductionsPdf')
            ->label('طباعة PDF')
            ->icon('heroicon-o-printer')
            ->color('info')
            ->action(fn ($livewire) => self::downloadWrongDeductionsPdf($livewire));
    }

    public static function wrongDeductionsExcel(): Action
    {
        return Action::make('exportWrongDeductionsExcel')
            ->label('تصدير Excel')
            ->icon('heroicon-o-table-cells')
            ->color('success')
            ->action(fn ($livewire) => self::downloadWrongDeductionsExcel($livewire));
    }

    public static function stopsWithoutContractPdf(): Action
    {
        return Action::make('printStopsWithoutContractPdf')
            ->label('طباعة PDF')
            ->icon('heroicon-o-printer')
            ->color('info')
            ->action(fn ($livewire) => self::downloadStopsWithoutContractPdf($livewire));
    }

    public static function stopsWithoutContractExcel(): Action
    {
        return Action::make('exportStopsWithoutContractExcel')
            ->label('تصدير Excel')
            ->icon('heroicon-o-table-cells')
            ->color('success')
            ->action(fn ($livewire) => self::downloadStopsWithoutContractExcel($livewire));
    }

    protected static function downloadWrongDeductionsPdf(object $livewire): mixed
    {
        $rows = self::filteredRows($livewire);

        if ($rows === null) {
            return null;
        }

        if ($rows->isEmpty()) {
            self::notifyEmpty();

            return null;
        }

        return PdfDownload::streamed(
            app(\App\Services\Pdf\InstallmentPdfService::class)
                ->wrongDeductionsReport($rows, InstallmentReportFilters::activeFilterLines($livewire))
        );
    }

    protected static function downloadWrongDeductionsExcel(object $livewire): mixed
    {
        $rows = self::filteredRows($livewire);

        if ($rows === null) {
            return null;
        }

        if ($rows->isEmpty()) {
            self::notifyEmpty();

            return null;
        }

        return app(\App\Services\Excel\InstallmentExcelService::class)
            ->wrongDeductionsReport($rows, InstallmentReportFilters::activeFilterLines($livewire));
    }

    protected static function downloadStopsWithoutContractPdf(object $livewire): mixed
    {
        $rows = self::filteredRows($livewire, with: ['payrollBank']);

        if ($rows === null) {
            return null;
        }

        if ($rows->isEmpty()) {
            self::notifyEmpty();

            return null;
        }

        return PdfDownload::streamed(
            app(\App\Services\Pdf\InstallmentPdfService::class)
                ->stopsWithoutContractReport($rows, InstallmentReportFilters::activeFilterLines($livewire))
        );
    }

    protected static function downloadStopsWithoutContractExcel(object $livewire): mixed
    {
        $rows = self::filteredRows($livewire, with: ['payrollBank']);

        if ($rows === null) {
            return null;
        }

        if ($rows->isEmpty()) {
            self::notifyEmpty();

            return null;
        }

        return app(\App\Services\Excel\InstallmentExcelService::class)
            ->stopsWithoutContractReport($rows, InstallmentReportFilters::activeFilterLines($livewire));
    }

    /**
     * @param  array<int, string>  $with
     */
    protected static function filteredRows(object $livewire, array $with = []): ?Collection
    {
        if (! method_exists($livewire, 'getTableQueryForExport')) {
            Notification::make()
                ->title('لا يمكن تصدير هذا الجدول')
                ->danger()
                ->send();

            return null;
        }

        /** @var Builder $query */
        $query = $livewire->getTableQueryForExport();

        if ($with !== []) {
            $query->with($with);
        }

        return $query->get();
    }

    protected static function notifyEmpty(): void
    {
        Notification::make()
            ->title('لا توجد بيانات مطابقة للفلتر')
            ->warning()
            ->send();
    }
}
