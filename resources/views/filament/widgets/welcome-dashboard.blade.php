<x-filament-widgets::widget>
    <div
        class="fi-welcome-dashboard overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
        style="direction: rtl;"
    >
        <div @class([
            'fi-welcome-dashboard-banner relative px-6 py-8 text-white',
            'bg-gradient-to-l from-amber-600 via-amber-700 to-orange-800' => ($panelLabel ?? '') === 'المبيعات',
            'bg-gradient-to-l from-emerald-600 via-emerald-700 to-teal-800' => ($panelLabel ?? '') === 'التقسيط',
            'bg-gradient-to-l from-violet-600 via-violet-700 to-purple-800' => ($panelLabel ?? '') === 'المالية',
            'bg-gradient-to-l from-slate-600 via-slate-700 to-gray-800' => ($panelLabel ?? '') === 'الإدارة',
            'bg-gradient-to-l from-primary-600 to-primary-800' => ! in_array($panelLabel ?? '', ['المبيعات', 'التقسيط', 'المالية', 'الإدارة'], true),
        ])>
            <div class="fi-welcome-dashboard-header">
                <div class="fi-welcome-dashboard-header__profile">
                    @if (filled($userAvatarUrl))
                        <img
                            src="{{ $userAvatarUrl }}"
                            alt="{{ $userName }}"
                            class="fi-welcome-dashboard-user-avatar"
                        />
                    @else
                        <div class="fi-welcome-dashboard-user-avatar fi-welcome-dashboard-user-avatar--placeholder">
                            {{ mb_substr($userName, 0, 1) }}
                        </div>
                    @endif
                    <p class="fi-welcome-dashboard-user-email">{{ $userEmail }}</p>
                </div>

                <div class="fi-welcome-dashboard-header__content">
                    <p class="text-sm font-medium text-white/80">مرحباً بكم</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">{{ $userName }}</h2>

                    @if (filled($companyName) || filled($companyLogoUrl))
                        <div class="fi-welcome-dashboard-company-row">
                            @if (filled($companyLogoUrl))
                                <div class="fi-welcome-dashboard-company-logo-wrap">
                                    <img
                                        src="{{ $companyLogoUrl }}"
                                        alt="شعار الشركة"
                                        class="fi-welcome-dashboard-company-logo"
                                    />
                                </div>
                            @endif

                            <div class="fi-welcome-dashboard-company-text">
                                @if (filled($companyName))
                                    <p class="fi-welcome-dashboard-company-name">{{ $companyName }}</p>
                                @endif

                                @if (filled($companySuffix))
                                    <p class="fi-welcome-dashboard-company-suffix">{{ $companySuffix }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <p class="mt-3 text-sm text-white/75">{{ $panelWelcomeLine }}</p>
                    <p class="mt-1 text-xs text-white/60">{{ $todayLabel }}</p>
                </div>
            </div>
        </div>

        <style>
            .fi-welcome-dashboard-header {
                display: flex;
                align-items: center;
                gap: 1.5rem;
                flex-wrap: wrap;
            }

            .fi-welcome-dashboard-header__profile {
                flex-shrink: 0;
                text-align: start;
            }

            .fi-welcome-dashboard-header__content {
                flex: 1;
                min-width: 12rem;
                text-align: start;
            }

            .fi-welcome-dashboard-company-row {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-top: 0.75rem;
            }

            .fi-welcome-dashboard-company-logo-wrap {
                flex-shrink: 0;
                padding: 0.5rem;
                border-radius: 0.875rem;
                background: rgb(255 255 255 / 0.15);
                box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.25);
                backdrop-filter: blur(4px);
            }

            .fi-welcome-dashboard-company-text {
                min-width: 0;
            }

            .fi-welcome-dashboard-company-name {
                font-size: 1.25rem;
                font-weight: 600;
                color: rgb(255 255 255 / 0.95);
            }

            .fi-welcome-dashboard-company-suffix {
                margin-top: 0.25rem;
                font-size: 1rem;
                color: rgb(255 255 255 / 0.85);
            }

            .fi-welcome-dashboard-user-avatar {
                display: block;
                width: 5.5rem;
                height: 5.5rem;
                border-radius: 9999px;
                border: 2px solid rgb(255 255 255 / 0.3);
                object-fit: cover;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            }

            .fi-welcome-dashboard-user-avatar--placeholder {
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgb(255 255 255 / 0.2);
                font-size: 1.5rem;
                font-weight: 700;
                backdrop-filter: blur(4px);
            }

            .fi-welcome-dashboard-user-email {
                margin-top: 0.5rem;
                max-width: 9rem;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 0.75rem;
                color: rgb(255 255 255 / 0.8);
            }

            .fi-welcome-dashboard-company-logo {
                display: block;
                width: 5rem;
                height: 5rem;
                border-radius: 0.625rem;
                object-fit: contain;
                background: #fff;
            }
        </style>

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
