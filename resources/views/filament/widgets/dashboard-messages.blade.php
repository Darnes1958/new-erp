<x-filament-widgets::widget>
    <div class="grid gap-4" style="direction: rtl;">
        @if (filled($alertMessage))
            <x-filament::section
                heading="تنبيه مهم"
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
            >
                <div class="prose prose-sm max-w-none text-danger-700 dark:text-danger-400 dark:prose-invert">
                    {!! nl2br(e($alertMessage)) !!}
                </div>
            </x-filament::section>
        @endif

        @if (filled($userMessage))
            <x-filament::section
                heading="رسالة النظام"
                icon="heroicon-o-megaphone"
                icon-color="primary"
            >
                <div class="prose prose-sm max-w-none text-primary-700 dark:text-primary-300 dark:prose-invert">
                    {!! nl2br(e($userMessage)) !!}
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-widgets::widget>
