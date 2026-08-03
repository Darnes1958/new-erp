<x-filament-panels::page>
    {{ $this->content }}

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
