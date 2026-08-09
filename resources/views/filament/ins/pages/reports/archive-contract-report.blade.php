<x-filament-panels::page>
    <div style="direction: rtl; display: flex; flex-direction: column; gap: 1.5rem;">
        <div>
            {{ $this->contractSearchForm }}
        </div>

        @if ($archive)
            <div>
                {{ $this->archiveInfolist }}

                @if ($customerOtherActiveContractsCount > 0)
                    <div class="ins-contract-customer-hint ins-contract-customer-hint--active">
                        لدى هذا الزبون عقود قائمة وعددها
                        <button
                            type="button"
                            class="ins-contract-customer-hint__link"
                            wire:click="mountAction('viewActiveContracts')"
                        >
                            <strong>{{ number_format($customerOtherActiveContractsCount) }}</strong>
                        </button>
                    </div>
                @endif

                @if ($customerArchiveContractsCount > 0)
                    <div class="ins-contract-customer-hint ins-contract-customer-hint--archive">
                        لدى هذا الزبون عقود أخرى في الأرشيف وعددها
                        <button
                            type="button"
                            class="ins-contract-customer-hint__link"
                            wire:click="mountAction('viewArchiveContracts')"
                        >
                            <strong>{{ number_format($customerArchiveContractsCount) }}</strong>
                        </button>
                    </div>
                @endif
            </div>

            <div>
                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem;">الأقساط المخصومة</h3>
                {{ $this->table }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
