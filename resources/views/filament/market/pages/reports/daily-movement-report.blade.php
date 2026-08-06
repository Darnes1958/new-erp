<x-filament-panels::page>
    <div style="direction: rtl; display: flex; flex-direction: column; gap: 2rem;">
        <div>
            {{ $this->filtersForm }}
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <x-filament::button
                :color="$activeTab === 'detail' ? 'primary' : 'gray'"
                wire:click="setActiveTab('detail')"
            >
                تفصيلي
            </x-filament::button>
            <x-filament::button
                :color="$activeTab === 'summary' ? 'primary' : 'gray'"
                wire:click="setActiveTab('summary')"
            >
                خلاصة
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
