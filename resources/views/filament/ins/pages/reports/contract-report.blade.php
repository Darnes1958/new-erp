<x-filament-panels::page>
    <div style="direction: rtl; display: flex; flex-direction: column; gap: 1.5rem;">
        <div>
            {{ $this->contractSearchForm }}
        </div>

        @if ($contract)
            <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-start;">
                <div style="flex: 1 1 420px; min-width: 0;">
                    {{ $this->contractInfolist }}

                    @if ($customerOtherActiveContractsCount > 0)
                        <div class="ins-contract-customer-hint ins-contract-customer-hint--active">
                            لدى هذا الزبون عقود أخرى قائمة وعددها
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
                            لدى هذا الزبون عقود في الأرشيف وعددها
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

                <div style="flex: 1 1 420px; min-width: 0;">
                    {{ $this->table }}
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
