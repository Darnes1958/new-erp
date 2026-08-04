<?php

namespace App\Filament\Ins\Support;

use Carbon\Carbon;
use Filament\Tables\Contracts\HasTable;

class InstallmentStopWithoutContractReportTitle
{
    public static function build(?string $dateFrom, ?string $dateTo): string
    {
        $hasFrom = filled($dateFrom);
        $hasTo = filled($dateTo);

        if ($hasFrom && $hasTo) {
            $untilFormatted = Carbon::parse($dateTo)->format('d-m-Y');

            return "كشف إيقاف خصم بدون عقد من تاريخ : {$dateFrom} وحتي تاريخ {$untilFormatted}";
        }

        if ($hasFrom) {
            return "كشف إيقاف خصم بدون عقد من تاريخ : {$dateFrom}";
        }

        if ($hasTo) {
            return "كشف إيقاف خصم بدون عقد حتي تاريخ : {$dateTo}";
        }

        return 'كشف إيقاف خصم بدون عقد حتى تاريخ: '.now()->toDateString();
    }

    /**
     * @return array{date_from: ?string, date_to: ?string}
     */
    public static function dateFilterState(object $livewire): array
    {
        if (property_exists($livewire, 'dateFrom') || property_exists($livewire, 'dateTo')) {
            return [
                'date_from' => filled($livewire->dateFrom ?? null) ? (string) $livewire->dateFrom : null,
                'date_to' => filled($livewire->dateTo ?? null) ? (string) $livewire->dateTo : null,
            ];
        }

        if (! $livewire instanceof HasTable) {
            return ['date_from' => null, 'date_to' => null];
        }

        $state = $livewire->getTableFilterState('stop_date') ?? [];

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
