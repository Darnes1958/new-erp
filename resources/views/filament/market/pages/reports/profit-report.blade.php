<x-filament-panels::page>
    <div style="direction: rtl; display: flex; flex-direction: column; gap: 2rem;">
        <div>
            {{ $this->filtersForm }}
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-start;">
            <div style="flex: 1 1 480px; min-width: 0;">
                {{ $this->table }}
            </div>

            <div style="flex: 1 1 480px; min-width: 0;">
                @livewire(
                    \App\Filament\Market\Widgets\ProfitChartWidget::class,
                    [
                        'year' => $this->year,
                        'warehouseId' => $this->warehouseId ?? null,
                    ],
                    key('profit-chart-'.($this->year ?? 0).'-'.($this->warehouseId ?? 'all'))
                )
            </div>
        </div>
    </div>
</x-filament-panels::page>
