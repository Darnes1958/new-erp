<?php

namespace App\Filament\Ins\Support;

use Carbon\Carbon;
use Filament\Tables\Contracts\HasTable;

class InstallmentReturnReportTitle
{
    public static function build(?string $dateFrom, ?string $dateTo): string
    {
        $hasFrom = filled($dateFrom);
        $hasTo = filled($dateTo);

        if ($hasFrom && $hasTo) {
            $untilFormatted = Carbon::parse($dateTo)->format('d-m-Y');

            return "تقرير بالأقساط المرجعة من تاريخ : {$dateFrom} وحتي تاريخ {$untilFormatted}";
        }

        if ($hasFrom) {
            return "تقرير بالأقساط المرجعة من تاريخ : {$dateFrom}";
        }

        if ($hasTo) {
            return "تقرير بالأقساط المرجعة حتي تاريخ : {$dateTo}";
        }

        return 'تقرير بالأقساط المرجعة حتى تاريخ: '.now()->toDateString();
    }

    /**
     * @return array{date_from: ?string, date_to: ?string}
     */
    public static function dateFilterState(object $livewire): array
    {
        if (! $livewire instanceof HasTable) {
            return ['date_from' => null, 'date_to' => null];
        }

        $state = $livewire->getTableFilterState('suspended_date') ?? [];

        return [
            'date_from' => filled($state['date_from'] ?? null) ? (string) $state['date_from'] : null,
            'date_to' => filled($state['date_to'] ?? null) ? (string) $state['date_to'] : null,
        ];
    }

    public static function fromLivewire(object $livewire): string
    {
        $dates = self::dateFilterState($livewire);

        return self::build($dates['date_from'], $dates['date_to']);
    }
}
