<?php

namespace App\Filament\Ins\Support;

class InstallmentContractFieldAttributes
{
    /**
     * @return array<string, string>
     */
    public static function installmentCountEnterKey(): array
    {
        return [
            'x-on:keydown.enter.prevent' => <<<'JS'
                (() => {
                    const total = parseFloat(document.getElementById('contract_total')?.value ?? 0);
                    const count = parseInt($event.target.value ?? 0, 10);
                    const amountEl = document.getElementById('installment_amount');
                    const focusTarget = amountEl?.matches('input, textarea, select')
                        ? amountEl
                        : amountEl?.querySelector('input, textarea, select');

                    if (total > 0 && count > 0 && focusTarget) {
                        focusTarget.value = (Math.round((total / count) * 1000) / 1000).toFixed(3);
                        focusTarget.dispatchEvent(new Event('input', { bubbles: true }));
                        focusTarget.focus();
                        focusTarget.select?.();
                    }
                })()
            JS,
        ];
    }
}
