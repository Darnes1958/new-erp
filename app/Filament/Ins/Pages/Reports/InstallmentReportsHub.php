<?php

namespace App\Filament\Ins\Pages\Reports;

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
        $user = Auth::user();

        return $user?->is_prog
            || $user?->can('ادخال عقود')
            || $user?->can('تعديل عقود')
            || $user?->can('تقرير عن عقد')
            || $user?->can('تقرير عن مصرف');
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
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
                'description' => 'طباعة عقد تقسيط واحد مع تفاصيله.',
                'status' => 'soon',
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
                'status' => 'soon',
            ],
        ];
    }
}
