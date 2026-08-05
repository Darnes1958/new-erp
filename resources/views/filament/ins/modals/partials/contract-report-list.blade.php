@php
    /** @var \Illuminate\Support\Collection<int, object> $rows */
    /** @var \App\Models\InstallmentContract|null $contract */
    $total = (float) $rows->sum($amountField);
    $count = $rows->count();
@endphp

<div class="ins-contract-modal">
    <style>
        .ins-contract-modal {
            direction: rtl;
            padding: 0.25rem 0.5rem 0.5rem;
        }

        .ins-contract-modal__intro {
            margin-bottom: 1rem;
            color: rgb(75 85 99);
            font-size: 0.925rem;
            line-height: 1.6;
        }

        .ins-contract-modal__summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .ins-contract-modal__summary-card {
            border: 1px solid rgb(229 231 235);
            border-radius: 0.75rem;
            background: rgb(249 250 251);
            padding: 0.875rem 1rem;
            text-align: center;
        }

        .ins-contract-modal__summary-label {
            display: block;
            margin-bottom: 0.35rem;
            color: rgb(107 114 128);
            font-size: 0.85rem;
        }

        .ins-contract-modal__summary-value {
            display: block;
            color: rgb(17 24 39);
            font-size: 1.125rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .ins-contract-modal__table-wrap {
            overflow: auto;
            max-height: min(60vh, 28rem);
            border: 1px solid rgb(209 213 219);
            border-radius: 0.75rem;
        }

        .ins-contract-modal__table {
            width: 100%;
            min-width: 36rem;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.95rem;
        }

        .ins-contract-modal__table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #9dc1d3;
            color: rgb(17 24 39);
            padding: 0.85rem 1rem;
            text-align: center;
            font-weight: 700;
            border-bottom: 1px solid rgb(156 163 175);
            white-space: nowrap;
        }

        .ins-contract-modal__table tbody td {
            padding: 0.8rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgb(229 231 235);
            vertical-align: middle;
            line-height: 1.5;
        }

        .ins-contract-modal__table tbody tr:nth-child(even) {
            background: rgb(249 250 251);
        }

        .ins-contract-modal__table tbody tr:hover {
            background: rgb(239 246 255);
        }

        .ins-contract-modal__table tfoot td {
            position: sticky;
            bottom: 0;
            background: rgb(243 244 246);
            padding: 0.9rem 1rem;
            text-align: center;
            font-weight: 700;
            border-top: 2px solid rgb(209 213 219);
        }

        .ins-contract-modal__amount {
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.01em;
        }

        .ins-contract-modal__badge {
            display: inline-block;
            min-width: 5.5rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            background: rgb(239 246 255);
            color: rgb(29 78 216);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .ins-contract-modal__empty {
            padding: 2rem 1rem;
            text-align: center;
            color: rgb(107 114 128);
            font-size: 0.95rem;
        }

        .dark .ins-contract-modal__intro {
            color: rgb(156 163 175);
        }

        .dark .ins-contract-modal__summary-card {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }

        .dark .ins-contract-modal__summary-label {
            color: rgb(156 163 175);
        }

        .dark .ins-contract-modal__summary-value {
            color: rgb(243 244 246);
        }

        .dark .ins-contract-modal__table-wrap {
            border-color: rgb(75 85 99);
        }

        .dark .ins-contract-modal__table tbody td {
            border-bottom-color: rgb(55 65 81);
        }

        .dark .ins-contract-modal__table tbody tr:nth-child(even) {
            background: rgb(31 41 55);
        }

        .dark .ins-contract-modal__table tbody tr:hover {
            background: rgb(30 58 95);
        }

        .dark .ins-contract-modal__table tfoot td {
            background: rgb(31 41 55);
            border-top-color: rgb(75 85 99);
        }
    </style>

    @if ($contract)
        <div class="ins-contract-modal__intro">
            عقد رقم <strong>{{ $contract->id }}</strong>
            @if ($contract->customer?->name)
                — {{ \App\Support\Utf8Text::clean($contract->customer->name) }}
            @endif
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="ins-contract-modal__empty">{{ $emptyMessage }}</div>
    @else
        <div class="ins-contract-modal__summary">
            <div class="ins-contract-modal__summary-card">
                <span class="ins-contract-modal__summary-label">{{ $countLabel }}</span>
                <strong class="ins-contract-modal__summary-value">{{ $count }}</strong>
            </div>
            <div class="ins-contract-modal__summary-card">
                <span class="ins-contract-modal__summary-label">{{ $totalLabel }}</span>
                <strong class="ins-contract-modal__summary-value ins-contract-modal__amount">{{ number_format($total, 3, '.', ',') }}</strong>
            </div>
        </div>

        <div class="ins-contract-modal__table-wrap">
            <table class="ins-contract-modal__table">
                <thead>
                <tr>
                    <th style="width: 12%;">م</th>
                    <th style="width: 18%;">الرقم</th>
                    <th style="width: 22%;">التاريخ</th>
                    <th style="width: 24%;">المبلغ</th>
                    <th style="width: 24%;">{{ $detailColumnLabel }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $index => $row)
                    @php
                        $date = data_get($row, $dateField);
                        $dateValue = $date instanceof \Carbon\CarbonInterface ? $date->format('Y-m-d') : $date;
                        $status = data_get($row, $statusField);
                        $statusLabel = is_object($status) && method_exists($status, 'getLabel')
                            ? $status->getLabel()
                            : ($status ?? '—');
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row->id }}</td>
                        <td>{{ $dateValue }}</td>
                        <td class="ins-contract-modal__amount">{{ number_format((float) data_get($row, $amountField), 3, '.', ',') }}</td>
                        <td>
                            <span class="ins-contract-modal__badge">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="3">الإجمالي</td>
                    <td class="ins-contract-modal__amount">{{ number_format($total, 3, '.', ',') }}</td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
