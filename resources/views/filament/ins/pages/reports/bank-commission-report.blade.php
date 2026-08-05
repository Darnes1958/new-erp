<x-filament-panels::page>
    <div style="direction: rtl; display: flex; flex-direction: column; gap: 2rem;">
        <div class="ins-report-toolbar">
            <div class="ins-report-toolbar__filters">
                {{ $this->filtersForm }}
            </div>
            <div class="ins-report-toolbar__exports">
                {{ $this->printAction }}
                {{ $this->exportExcelAction }}
            </div>
        </div>

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
