<?php

namespace App\Filament\Ins\Support;

use App\Support\Utf8Text;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Indicator;

class InstallmentReportFilters
{
    /**
     * @return array<int, string>
     */
    public static function activeFilterLines(object $livewire): array
    {
        if (! $livewire instanceof HasTable) {
            return [];
        }

        return collect($livewire->getTable()->getFilterIndicators())
            ->map(function (Indicator $indicator): ?string {
                $label = $indicator->getLabel();

                if ($label instanceof \Illuminate\Contracts\Support\Htmlable) {
                    $label = $label->toHtml();
                }

                $clean = Utf8Text::clean(strip_tags((string) $label));

                return filled($clean) ? $clean : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
