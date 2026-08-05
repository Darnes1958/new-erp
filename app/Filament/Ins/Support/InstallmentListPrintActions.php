<?php

namespace App\Filament\Ins\Support;

use App\Support\PdfDownload;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InstallmentListPrintActions
{
    public static function create(): CreateAction
    {
        return CreateAction::make()
            ->label('إضافة')
            ->color('primary');
    }

    public static function wrongDeductionsExports(): ActionGroup
    {
        return self::exportGroup(
            self::wrongDeductionsPdf(),
            self::wrongDeductionsExcel(),
        );
    }

    public static function installmentSurplusesExports(): ActionGroup
    {
        return self::exportGroup(
            self::installmentSurplusesPdf(),
            self::installmentSurplusesExcel(),
        );
    }

    public static function stopsWithoutContractExports(): ActionGroup
    {
        return self::exportGroup(
            self::stopsWithoutContractPdf(),
            self::stopsWithoutContractExcel(),
        );
    }

    public static function installmentReturnsExports(): ActionGroup
    {
        return self::exportGroup(
            self::installmentReturnsPdf(),
            self::installmentReturnsExcel(),
        );
    }

    public static function wrongDeductionsPdf(): Action
    {
        return self::pdfAction(
            'printWrongDeductionsPdf',
            fn ($livewire) => self::downloadWrongDeductionsPdf($livewire),
        );
    }

    public static function wrongDeductionsExcel(): Action
    {
        return self::excelAction(
            'exportWrongDeductionsExcel',
            fn ($livewire) => self::downloadWrongDeductionsExcel($livewire),
        );
    }

    public static function installmentSurplusesPdf(): Action
    {
        return self::pdfAction(
            'printInstallmentSurplusesPdf',
            fn ($livewire) => self::downloadInstallmentSurplusesPdf($livewire),
        );
    }

    public static function installmentSurplusesExcel(): Action
    {
        return self::excelAction(
            'exportInstallmentSurplusesExcel',
            fn ($livewire) => self::downloadInstallmentSurplusesExcel($livewire),
        );
    }

    public static function stopsWithoutContractPdf(): Action
    {
        return self::pdfAction(
            'printStopsWithoutContractPdf',
            fn ($livewire) => self::downloadStopsWithoutContractPdf($livewire),
        );
    }

    public static function stopsWithoutContractExcel(): Action
    {
        return self::excelAction(
            'exportStopsWithoutContractExcel',
            fn ($livewire) => self::downloadStopsWithoutContractExcel($livewire),
        );
    }

    public static function installmentReturnsPdf(): Action
    {
        return self::pdfAction(
            'printInstallmentReturnsPdf',
            fn ($livewire) => self::downloadInstallmentReturnsPdf($livewire),
        );
    }

    public static function installmentReturnsExcel(): Action
    {
        return self::excelAction(
            'exportInstallmentReturnsExcel',
            fn ($livewire) => self::downloadInstallmentReturnsExcel($livewire),
        );
    }

    public static function compactPrint(string $name, callable $action): Action
    {
        return Action::make($name)
            ->icon('heroicon-o-printer')
            ->iconButton()
            ->color('info')
            ->tooltip('طباعة')
            ->size(Size::Small)
            ->action($action);
    }

    public static function compactExcel(string $name, callable $action): Action
    {
        return Action::make($name)
            ->label('EXCL')
            ->color('success')
            ->size(Size::Small)
            ->action($action);
    }

    protected static function pdfAction(string $name, callable $action): Action
    {
        return self::compactPrint($name, $action);
    }

    protected static function excelAction(string $name, callable $action): Action
    {
        return self::compactExcel($name, $action);
    }

    protected static function exportGroup(Action $pdf, Action $excel): ActionGroup
    {
        return ActionGroup::make([$pdf, $excel])
            ->buttonGroup();
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
                ->wrongDeductionsReport(
                    $rows,
                    InstallmentReportFilters::activeFilterLines($livewire),
                    WrongDeductionReportTitle::fromLivewire($livewire),
                )
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
            ->wrongDeductionsReport(
                $rows,
                InstallmentReportFilters::activeFilterLines($livewire),
                WrongDeductionReportTitle::fromLivewire($livewire),
            );
    }

    protected static function downloadInstallmentSurplusesPdf(object $livewire): mixed
    {
        $rows = self::filteredRows($livewire, with: ['contractable.customer']);

        if ($rows === null) {
            return null;
        }

        if ($rows->isEmpty()) {
            self::notifyEmpty();

            return null;
        }

        return PdfDownload::streamed(
            app(\App\Services\Pdf\InstallmentPdfService::class)
                ->installmentSurplusesReport(
                    $rows,
                    InstallmentReportFilters::activeFilterLines($livewire),
                    InstallmentSurplusReportTitle::fromLivewire($livewire),
                )
        );
    }

    protected static function downloadInstallmentSurplusesExcel(object $livewire): mixed
    {
        $rows = self::filteredRows($livewire, with: ['contractable.customer']);

        if ($rows === null) {
            return null;
        }

        if ($rows->isEmpty()) {
            self::notifyEmpty();

            return null;
        }

        return app(\App\Services\Excel\InstallmentExcelService::class)
            ->installmentSurplusesReport(
                $rows,
                InstallmentReportFilters::activeFilterLines($livewire),
                InstallmentSurplusReportTitle::fromLivewire($livewire),
            );
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
            ->stopsWithoutContractReport(
                $rows,
                InstallmentReportFilters::activeFilterLines($livewire),
                InstallmentStopWithoutContractReportTitle::fromLivewire($livewire),
            );
    }

    protected static function downloadInstallmentReturnsPdf(object $livewire): mixed
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
                ->installmentReturnsReport(
                    $rows,
                    InstallmentReportFilters::activeFilterLines($livewire),
                    InstallmentReturnReportTitle::fromLivewire($livewire),
                )
        );
    }

    protected static function downloadInstallmentReturnsExcel(object $livewire): mixed
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
            ->installmentReturnsReport(
                $rows,
                InstallmentReportFilters::activeFilterLines($livewire),
                InstallmentReturnReportTitle::fromLivewire($livewire),
            );
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
