@php
    $format = static fn (mixed $value): string => number_format((float) $value, 2, '.', ',');
    $rows = $rows ?? [];
    $purchaseTotal = (float) ($purchase_total ?? 0);
    $salesTotal = (float) ($sales_total ?? 0);
    $marginTotal = $salesTotal - $purchaseTotal;
@endphp

<style>
    .spd-modal {
        direction: rtl;
        padding: 0.15rem 0.15rem 0.35rem;
    }

    .spd-modal__summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 0.9rem;
    }

    .spd-modal__card {
        border: 1px solid rgb(226 232 240);
        border-radius: 0.85rem;
        background: linear-gradient(180deg, rgb(248 250 252) 0%, rgb(241 245 249) 100%);
        padding: 0.8rem 0.95rem;
        text-align: center;
        box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
    }

    .spd-modal__card-label {
        display: block;
        margin-bottom: 0.3rem;
        color: rgb(100 116 139);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .spd-modal__card-value {
        display: block;
        color: rgb(15 23 42);
        font-size: 1.15rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
        direction: ltr;
    }

    .spd-modal__card--buy .spd-modal__card-value {
        color: rgb(180 83 9);
    }

    .spd-modal__card--sell .spd-modal__card-value {
        color: rgb(37 99 235);
    }

    .spd-modal__card--margin .spd-modal__card-value {
        color: rgb(22 163 74);
    }

    .spd-modal__card--margin.is-negative .spd-modal__card-value {
        color: rgb(220 38 38);
    }

    .spd-modal__table-wrap {
        overflow: auto;
        max-height: min(62vh, 32rem);
        border: 1px solid rgb(203 213 225);
        border-radius: 0.9rem;
        background: #fff;
        box-shadow: 0 8px 24px rgb(15 23 42 / 6%);
    }

    .spd-modal__table {
        display: table;
        width: 100%;
        min-width: 56rem;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.875rem;
    }

    .spd-modal__table thead {
        display: table-header-group;
    }

    .spd-modal__table tbody {
        display: table-row-group;
    }

    .spd-modal__table tfoot {
        display: table-footer-group;
    }

    .spd-modal__table tr {
        display: table-row;
    }

    .spd-modal__table th,
    .spd-modal__table td {
        display: table-cell;
        vertical-align: middle;
    }

    .spd-modal__table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: rgb(15 118 110);
        color: #fff;
        padding: 0.7rem 0.75rem;
        text-align: center;
        font-weight: 700;
        white-space: nowrap;
        border-bottom: 1px solid rgb(13 148 136);
        letter-spacing: 0;
    }

    .spd-modal__table tbody td {
        padding: 0.7rem 0.75rem;
        border-bottom: 1px solid rgb(226 232 240);
        color: rgb(30 41 59);
        line-height: 1.45;
    }

    .spd-modal__table tbody tr:nth-child(even) td {
        background: rgb(248 250 252);
    }

    .spd-modal__table tbody tr:hover td {
        background: rgb(240 253 250);
    }

    .spd-modal__table tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 1;
        background: rgb(241 245 249);
        padding: 0.75rem;
        font-weight: 800;
        border-top: 1px solid rgb(203 213 225);
        color: rgb(15 23 42);
    }

    .spd-modal__name {
        text-align: right;
        font-weight: 600;
        white-space: nowrap;
    }

    .spd-modal__center {
        text-align: center;
    }

    .spd-modal__num {
        text-align: end;
        font-variant-numeric: tabular-nums;
        direction: ltr;
        unicode-bidi: isolate;
        white-space: nowrap;
    }

    .spd-modal__badge {
        display: inline-flex;
        min-width: 3rem;
        justify-content: center;
        padding: 0.15rem 0.55rem;
        border-radius: 999px;
        background: rgb(224 242 254);
        color: rgb(3 105 161);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .spd-modal__margin {
        font-weight: 700;
        color: rgb(22 163 74);
    }

    .spd-modal__margin.is-negative {
        color: rgb(220 38 38);
    }

    .spd-modal__empty {
        padding: 2.25rem 1rem;
        text-align: center;
        color: rgb(100 116 139);
        font-size: 0.95rem;
        border: 1px dashed rgb(203 213 225);
        border-radius: 0.9rem;
        background: rgb(248 250 252);
    }

    .dark .spd-modal__card {
        border-color: rgb(51 65 85);
        background: linear-gradient(180deg, rgb(30 41 59) 0%, rgb(15 23 42) 100%);
    }

    .dark .spd-modal__card-label {
        color: rgb(148 163 184);
    }

    .dark .spd-modal__card-value,
    .dark .spd-modal__table tbody td,
    .dark .spd-modal__table tfoot td {
        color: rgb(241 245 249);
    }

    .dark .spd-modal__table-wrap {
        border-color: rgb(51 65 85);
        background: rgb(15 23 42);
    }

    .dark .spd-modal__table tbody td {
        border-bottom-color: rgb(51 65 85);
    }

    .dark .spd-modal__table tbody tr:nth-child(even) td {
        background: rgb(30 41 59);
    }

    .dark .spd-modal__table tbody tr:hover td {
        background: rgb(19 78 74);
    }

    .dark .spd-modal__table tfoot td {
        background: rgb(30 41 59);
        border-top-color: rgb(51 65 85);
    }

    .dark .spd-modal__empty {
        color: rgb(148 163 184);
        border-color: rgb(51 65 85);
        background: rgb(15 23 42);
    }

    @media (max-width: 640px) {
        .spd-modal__summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="spd-modal">
    @if ($rows === [])
        <div class="spd-modal__empty">لا توجد تفاصيل شراء</div>
    @else
        <div class="spd-modal__summary">
            <div class="spd-modal__card spd-modal__card--buy">
                <span class="spd-modal__card-label">إجمالي الشراء</span>
                <span class="spd-modal__card-value">{{ $format($purchaseTotal) }}</span>
            </div>
            <div class="spd-modal__card spd-modal__card--sell">
                <span class="spd-modal__card-label">إجمالي البيع</span>
                <span class="spd-modal__card-value">{{ $format($salesTotal) }}</span>
            </div>
            <div @class(['spd-modal__card spd-modal__card--margin', 'is-negative' => $marginTotal < 0])>
                <span class="spd-modal__card-label">الهامش</span>
                <span class="spd-modal__card-value">{{ $format($marginTotal) }}</span>
            </div>
        </div>

        <div class="spd-modal__table-wrap">
            <table class="spd-modal__table">
                <thead>
                    <tr>
                        <th>رقم فاتورة الشراء</th>
                        <th>رقم الصنف</th>
                        <th>اسم الصنف</th>
                        <th>الكمية</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>إجمالي الشراء</th>
                        <th>إجمالي البيع</th>
                        <th>الهامش</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $margin = (float) $row['margin']; @endphp
                        <tr>
                            <td class="spd-modal__center">
                                @if (! empty($row['purchase_invoice_id']))
                                    <span class="spd-modal__badge">{{ $row['purchase_invoice_id'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="spd-modal__center">{{ $row['item_id'] }}</td>
                            <td class="spd-modal__name">{{ $row['item_name'] }}</td>
                            <td class="spd-modal__num">{{ $format($row['qty']) }}</td>
                            <td class="spd-modal__num">{{ $format($row['buy_price']) }}</td>
                            <td class="spd-modal__num">{{ $format($row['sell_price']) }}</td>
                            <td class="spd-modal__num">{{ $format($row['purchase_total']) }}</td>
                            <td class="spd-modal__num">{{ $format($row['sales_total']) }}</td>
                            <td @class(['spd-modal__num spd-modal__margin', 'is-negative' => $margin < 0])>
                                {{ $format($margin) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="spd-modal__center" colspan="6">الملخص</td>
                        <td class="spd-modal__num">{{ $format($purchaseTotal) }}</td>
                        <td class="spd-modal__num">{{ $format($salesTotal) }}</td>
                        <td @class(['spd-modal__num spd-modal__margin', 'is-negative' => $marginTotal < 0])>
                            {{ $format($marginTotal) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
