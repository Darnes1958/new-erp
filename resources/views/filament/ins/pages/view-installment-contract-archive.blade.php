<x-filament-panels::page>
    <div  style="direction: rtl;">
        <div style="margin-bottom: 1.5rem;">
            {{ $this->infolist }}
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem;">الأقساط المخصومة</h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
