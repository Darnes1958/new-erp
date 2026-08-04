<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Filament\Ins\Pages\RecordInstallmentStop;
use Filament\Pages\Page;

class InstallmentStopReport extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'installment-stop-report';

    protected string $view = 'filament.ins.pages.reports.installment-stop-report-redirect';

    public static function canAccess(): bool
    {
        return RecordInstallmentStop::canAccess();
    }

    public function mount(): void
    {
        $this->redirect(
            RecordInstallmentStop::reportTabUrl(),
            navigate: true,
        );
    }
}
