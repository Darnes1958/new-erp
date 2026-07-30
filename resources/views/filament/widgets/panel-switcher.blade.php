<x-filament-widgets::widget>
    <x-filament::section heading="الانتقال بين الأقسام">
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($panels as $panel)
                <a
                    href="{{ $panel['url'] }}"
                    @class([
                        'block rounded-xl border p-4 transition',
                        'border-primary-500 bg-primary-50 dark:bg-primary-950/30 ring-2 ring-primary-500' => $panel['active'],
                        'border-gray-200 hover:border-primary-300 dark:border-gray-700 dark:hover:border-primary-600' => ! $panel['active'],
                    ])
                >
                    <div class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ $panel['label'] }}
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $panel['description'] }}
                    </p>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
