<?php

namespace App\Filament\Ins\Pages\Reports;

use App\Filament\Ins\Resources\InstallmentContractArchives\InstallmentContractArchiveResource;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class InstallmentReportsHub extends Page
{
    protected static ?string $navigationLabel = 'التقارير';

    protected static ?string $title = 'تقارير التقسيط';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'تقارير';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.ins.pages.reports.installment-reports-hub';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('ادخال عقود')
            || $user?->can('تعديل عقود')
            || $user?->can('تقرير عن عقد')
            || $user?->can('تقرير عن مصرف');
    }

    /**
     * تقارير عامة — التقارير التشغيلية (خطأ، فائض، إرجاع، إيقاف...) متاحة من القائمة أعلاه.
     *
     * @return array<int, array{title: string, description: string, url?: string, status: string}>
     */
    public function reports(): array
    {
        return [
            [
                'title' => 'تقرير عن عقد',
                'description' => 'استعلام عن عقد تقسيط واحد مع خصوماته وطباعة نموذج المصرف.',
                'url' => ContractReportPage::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'استعلام عن عقد من الأرشيف',
                'description' => 'بحث برقم العقد في الأرشيف مع الخصومات واسترجاع العقد.',
                'url' => ArchiveContractReportPage::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'تقرير عقود الزبون',
                'description' => 'عرض عقود زبون واحد: قائمة، أرشيف، ملغاة، أو الكل.',
                'url' => CustomerContractsReportPage::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'الأرشيف',
                'description' => 'تصفح العقود المؤرشفة واسترجاعها.',
                'url' => InstallmentContractArchiveResource::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'تقارير عن مصرف',
                'description' => 'كشف بالأسماء، المسددة، المتأخرة، المحصلة، وغيرها.',
                'url' => BankReportsPage::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'إجمالي المصارف',
                'description' => 'ملخص أرقام المصارف مع تصدير Excel.',
                'url' => BankTotalsReportPage::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'عمولة المصارف',
                'description' => 'عمولة المصارف خلال فترة محددة.',
                'url' => BankCommissionReportPage::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'إجمالي الفروع',
                'description' => 'ملخص أرقام الفروع حسب الحسابات التجميعية.',
                'url' => BranchTotalsReportPage::getUrl(),
                'status' => 'ready',
            ],
            [
                'title' => 'عمولة الفروع',
                'description' => 'عمولة المصرف للفروع خلال فترة محددة.',
                'url' => BranchCommissionReportPage::getUrl(),
                'status' => 'ready',
            ],
        ];
    }
}
