<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1rem;">
        {{ $this->headerForm }}

        <x-filament::section>
            <div
                style="display: grid; grid-template-columns: minmax(240px, 5fr) minmax(0, 7fr); gap: 1rem; direction: rtl; width: 100%;"
            >
                <div style="width: 100%; max-width: 420px; min-width: 0;">
                    {{ $this->lineForm }}
                </div>

                <div style="min-width: 0; overflow-x: auto;">
                    {{ $this->table }}
                </div>
            </div>
        </x-filament::section>
    </div>

    @script
    <script>
        $wire.on('focus-field', ({ field }) => {
            setTimeout(() => {
                const element = document.getElementById(field);

                if (! element) {
                    return;
                }

                const input = element.matches('input, textarea, select')
                    ? element
                    : element.querySelector('input:not([type="hidden"]), textarea, select');

                if (! input) {
                    return;
                }

                input.focus();

                if (typeof input.select === 'function') {
                    input.select();
                }
            }, 150);
        });
    </script>
    @endscript
</x-filament-panels::page>
