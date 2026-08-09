<x-filament-widgets::widget>
    <div
        class="fi-welcome-dashboard overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
        style="direction: rtl;"
    >
        <div @class([
            'relative px-6 py-8 text-white',
            'bg-gradient-to-l from-amber-600 via-amber-700 to-orange-800' => ($panelLabel ?? '') === 'المبيعات',
            'bg-gradient-to-l from-emerald-600 via-emerald-700 to-teal-800' => ($panelLabel ?? '') === 'التقسيط',
            'bg-gradient-to-l from-violet-600 via-violet-700 to-purple-800' => ($panelLabel ?? '') === 'المالية',
            'bg-gradient-to-l from-slate-600 via-slate-700 to-gray-800' => ($panelLabel ?? '') === 'الإدارة',
            'bg-gradient-to-l from-primary-600 to-primary-800' => ! in_array($panelLabel ?? '', ['المبيعات', 'التقسيط', 'المالية', 'الإدارة'], true),
        ])>
            <div class="flex flex-wrap items-center gap-6">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-white/80">مرحباً بكم</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">{{ $userName }}</h2>

                    @if (filled($companyName))
                        <p class="mt-3 text-xl font-semibold text-white/95">{{ $companyName }}</p>
                    @endif

                    @if (filled($companySuffix))
                        <p class="mt-1 text-base text-white/85">{{ $companySuffix }}</p>
                    @endif

                    <p class="mt-3 text-sm text-white/75">{{ $panelWelcomeLine }}</p>
                    <p class="mt-1 text-xs text-white/60">{{ $todayLabel }}</p>
                </div>

                <div class="flex shrink-0 items-center gap-4">
                    @if (filled($companyLogoUrl))
                        <div class="rounded-xl bg-white/15 p-2 ring-1 ring-white/25 backdrop-blur-sm">
                            <img
                                src="{{ $companyLogoUrl }}"
                                alt="شعار الشركة"
                                class="h-16 w-16 rounded-lg object-contain bg-white"
                            />
                        </div>
                    @endif

                    <div class="text-center">
                        @if (filled($userAvatarUrl))
                            <img
                                src="{{ $userAvatarUrl }}"
                                alt="{{ $userName }}"
                                class="mx-auto h-24 w-24 rounded-full border-4 border-white/30 object-cover shadow-lg"
                            />
                        @else
                            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full border-4 border-white/30 bg-white/20 text-3xl font-bold shadow-lg backdrop-blur-sm">
                                {{ mb_substr($userName, 0, 1) }}
                            </div>
                        @endif
                        <p class="mt-2 max-w-[9rem] truncate text-xs text-white/80">{{ $userEmail }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if (count($roleBadges) > 0)
            <div class="flex flex-wrap gap-2 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                @foreach ($roleBadges as $badge)
                    <x-filament::badge :color="$badge['color']" size="md">
                        {{ $badge['label'] }}
                    </x-filament::badge>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
