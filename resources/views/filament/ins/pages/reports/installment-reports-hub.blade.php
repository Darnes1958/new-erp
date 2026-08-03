<x-filament-panels::page>
    <div style="direction: rtl;" class="space-y-4">
        @foreach ($this->reports() as $report)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold">{{ $report['title'] }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $report['description'] }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($report['status'] === 'ready' && ! empty($report['url']))
                            <x-filament::button
                                tag="a"
                                :href="$report['url']"
                                color="primary"
                                size="sm"
                            >
                                فتح الشاشة
                            </x-filament::button>
                        @else
                            <x-filament::badge color="gray">قريباً</x-filament::badge>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
