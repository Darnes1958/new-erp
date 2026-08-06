<x-filament-panels::page>
    <div style="direction: rtl; display: flex; flex-direction: column; gap: 2rem;">
        <div>
            {{ $this->filtersForm }}
        </div>

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
