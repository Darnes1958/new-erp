@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\InstallmentContractArchive> $archives */
    /** @var \App\Models\Customer|null $customer */
    /** @var \App\Models\InstallmentContractArchive|null $selectedArchive */
    $totalValue = (float) $archives->sum('contract_total');
@endphp

<div class="ins-contract-modal ins-contract-archives-modal">
    @include('filament.ins.modals.partials.contract-modal-styles')

    <style>
        .ins-contract-archives-modal__intro {
            margin-bottom: 1rem;
            color: rgb(75 85 99);
            font-size: 0.925rem;
            line-height: 1.6;
        }

        .ins-contract-archives-modal__table tbody tr {
            cursor: pointer;
        }

        .ins-contract-archives-modal__table tbody tr.is-selected {
            background: rgb(219 234 254) !important;
            outline: 2px solid rgb(59 130 246);
            outline-offset: -2px;
        }

        .ins-contract-archives-modal__details {
            margin-top: 1.25rem;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.75rem;
            background: rgb(249 250 251);
            padding: 1rem 1.1rem;
        }

        .ins-contract-archives-modal__details-title {
            margin-bottom: 0.85rem;
            color: rgb(29 78 216);
            font-size: 1rem;
            font-weight: 700;
        }

        .ins-contract-archives-modal__details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem 1rem;
        }

        .ins-contract-archives-modal__detail {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .ins-contract-archives-modal__detail-label {
            color: rgb(107 114 128);
            font-size: 0.82rem;
        }

        .ins-contract-archives-modal__detail-value {
            color: rgb(17 24 39);
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.5;
        }

        .ins-contract-archives-modal__detail-value--amount {
            font-variant-numeric: tabular-nums;
        }

        .ins-contract-archives-modal__details-note {
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px dashed rgb(209 213 219);
            color: rgb(55 65 81);
            font-size: 0.925rem;
            line-height: 1.6;
        }

        .dark .ins-contract-archives-modal__intro {
            color: rgb(156 163 175);
        }

        .dark .ins-contract-archives-modal__table tbody tr.is-selected {
            background: rgb(30 58 95) !important;
            outline-color: rgb(96 165 250);
        }

        .dark .ins-contract-archives-modal__details {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
        }

        .dark .ins-contract-archives-modal__details-title {
            color: rgb(147 197 253);
        }

        .dark .ins-contract-archives-modal__detail-label {
            color: rgb(156 163 175);
        }

        .dark .ins-contract-archives-modal__detail-value {
            color: rgb(243 244 246);
        }

        .dark .ins-contract-archives-modal__details-note {
            border-top-color: rgb(75 85 99);
            color: rgb(209 213 219);
        }
    </style>

    @if ($customer?->name)
        <div class="ins-contract-archives-modal__intro">
            الزبون: <strong>{{ \App\Support\Utf8Text::clean($customer->name) }}</strong>
        </div>
    @endif

    @if ($archives->isEmpty())
        <div class="ins-contract-modal__empty">لا توجد عقود مؤرشفة لهذا الزبون.</div>
    @else
        <div class="ins-contract-modal__summary">
            <div class="ins-contract-modal__summary-card">
                <span class="ins-contract-modal__summary-label">عدد العقود</span>
                <strong class="ins-contract-modal__summary-value">{{ $archives->count() }}</strong>
            </div>
            <div class="ins-contract-modal__summary-card">
                <span class="ins-contract-modal__summary-label">إجمالي القيم</span>
                <strong class="ins-contract-modal__summary-value ins-contract-modal__amount">{{ number_format($totalValue, 3, '.', ',') }}</strong>
            </div>
        </div>

        <div class="ins-contract-modal__table-wrap">
            <table class="ins-contract-modal__table ins-contract-archives-modal__table">
                <thead>
                <tr>
                    <th style="width: 12%;">م</th>
                    <th style="width: 28%;">رقم العقد</th>
                    <th style="width: 30%;">تاريخ الأرشفة</th>
                    <th style="width: 30%;">قيمة العقد</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($archives as $index => $archive)
                    <tr
                        wire:key="archive-contract-{{ $archive->id }}"
                        wire:click="selectArchiveContract({{ $archive->id }})"
                        class="@if ((int) $selectedArchiveContractId === (int) $archive->id) is-selected @endif"
                    >
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $archive->id }}</td>
                        <td>{{ $archive->archived_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="ins-contract-modal__amount">{{ number_format((float) $archive->contract_total, 3, '.', ',') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($selectedArchive)
            <div class="ins-contract-archives-modal__details">
                <div class="ins-contract-archives-modal__details-title">
                    بيانات العقد المؤرشف رقم {{ $selectedArchive->id }}
                </div>

                <div class="ins-contract-archives-modal__details-grid">
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">تاريخ العقد</span>
                        <span class="ins-contract-archives-modal__detail-value">{{ $selectedArchive->contract_start?->format('Y-m-d') ?? '—' }}</span>
                    </div>
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">تاريخ الانتهاء</span>
                        <span class="ins-contract-archives-modal__detail-value">{{ $selectedArchive->contract_end?->format('Y-m-d') ?? '—' }}</span>
                    </div>
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">قيمة العقد</span>
                        <span class="ins-contract-archives-modal__detail-value ins-contract-archives-modal__detail-value--amount">{{ number_format((float) $selectedArchive->contract_total, 3, '.', ',') }}</span>
                    </div>
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">عدد الأقساط</span>
                        <span class="ins-contract-archives-modal__detail-value">{{ $selectedArchive->installment_count ?? '—' }}</span>
                    </div>
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">القسط</span>
                        <span class="ins-contract-archives-modal__detail-value ins-contract-archives-modal__detail-value--amount">{{ number_format((float) $selectedArchive->installment_amount, 3, '.', ',') }}</span>
                    </div>
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">المدفوع</span>
                        <span class="ins-contract-archives-modal__detail-value ins-contract-archives-modal__detail-value--amount">{{ number_format((float) $selectedArchive->total_paid, 3, '.', ',') }}</span>
                    </div>
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">المتبقي</span>
                        <span class="ins-contract-archives-modal__detail-value ins-contract-archives-modal__detail-value--amount">{{ number_format((float) $selectedArchive->balance, 3, '.', ',') }}</span>
                    </div>
                    <div class="ins-contract-archives-modal__detail">
                        <span class="ins-contract-archives-modal__detail-label">تاريخ الأرشفة</span>
                        <span class="ins-contract-archives-modal__detail-value">{{ $selectedArchive->archived_at?->format('Y-m-d') ?? '—' }}</span>
                    </div>
                </div>

                @if (filled($selectedArchive->notes))
                    <div class="ins-contract-archives-modal__details-note">
                        <strong>ملاحظات:</strong> {{ \App\Support\Utf8Text::clean($selectedArchive->notes) }}
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
