<x-filament-widgets::widget>
    <x-filament::section heading="الانتقال بين الأقسام" icon="heroicon-o-squares-2x2">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" style="direction: rtl;">
            @foreach ($panels as $panel)
                @php
                    $color = $panel['color'] ?? 'gray';
                    $icon = $panel['icon'] ?? null;
                @endphp

                <a
                    href="{{ $panel['url'] }}"
                    @class([
                        'group block rounded-2xl border p-5 transition duration-200',
                        'border-primary-500 bg-primary-50 ring-2 ring-primary-500 dark:bg-primary-950/30' => $panel['active'],
                        'border-gray-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:hover:border-primary-600' => ! $panel['active'],
                    ])
                >
                    <div class="flex items-start gap-3">
                        <div @class([
                            'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                            'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $color === 'amber',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $color === 'emerald',
                            'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300' => $color === 'violet',
                            'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' => $color === 'slate',
                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' => $color === 'gray',
                        ])>
                            @if ($icon)
                                <x-filament::icon :icon="$icon" class="h-6 w-6" />
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $panel['label'] }}
                            </div>
                            <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                                {{ $panel['description'] }}
                            </p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
