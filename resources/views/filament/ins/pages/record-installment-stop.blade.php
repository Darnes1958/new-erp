<x-filament-panels::page>
    <div style="direction: rtl; display: flex; flex-direction: column; gap: 2rem;">
        <div>
            @if ($activeTab !== 'register')
                <x-filament::button
                    color="gray"
                    wire:click="setActiveTab('register')"
                >
                    العودة لشاشة الإيقاف
                </x-filament::button>
            @endif
            @if ($activeTab !== 'report')
                <x-filament::button
                    color="gray"
                    wire:click="setActiveTab('report')"
                >
                    طباعة رسائل المصرف
                </x-filament::button>
            @endif
        </div>

        @if ($activeTab === 'report')
            <div style="display: flex; flex-direction: row; align-items: flex-end; gap: 1.5rem; width: 100%;">
                <div style="flex: 1 1 auto; min-width: 0;">
                    {{ $this->reportForm }}
                </div>
                <div style="flex: 0 0 auto; padding-bottom: 0.35rem;">
                    {{ $this->printAction }}
                </div>
            </div>
        @endif

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
