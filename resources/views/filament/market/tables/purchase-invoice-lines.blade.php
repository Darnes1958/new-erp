@php
    /** @var \App\Models\PurchaseInvoice $invoice */
    $invoice = $getRecord();
    $lines = $invoice->lines;
    $dualUnit = \App\Support\CompanySettings::hasDualUnit();
    $formatWhole = static fn (mixed $value): string => number_format(round((float) $value), 0, '.', ',');
@endphp

@if ($lines->isEmpty())
    <p class="px-2 py-2 text-xs text-gray-500 dark:text-gray-400">لا توجد بنود</p>
@else
    <div class="purchase-inv-lines-wrap w-full min-w-0 overflow-x-auto py-1">
        <table class="purchase-inv-lines-table w-full border-collapse text-xs">
            <colgroup>
                <col class="col-id">
                <col class="col-name">
                <col class="col-qty">
                @if ($dualUnit)
                    <col class="col-qty">
                @endif
                <col class="col-money">
                <col class="col-money">
            </colgroup>
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800">
                    <th class="border border-gray-200 px-2 py-2 text-right dark:border-gray-700">رقم الصنف</th>
                    <th class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">اسم الصنف</th>
                    <th class="border border-gray-200 px-3 py-2 text-center dark:border-gray-700">الكمية</th>
                    @if ($dualUnit)
                        <th class="border border-gray-200 px-3 py-2 text-center dark:border-gray-700">كمية 2</th>
                    @endif
                    <th class="border border-gray-200 px-3 py-2 text-end dark:border-gray-700">السعر</th>
                    <th class="border border-gray-200 px-3 py-2 text-end dark:border-gray-700">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td class="border border-gray-200 px-2 py-2 text-right dark:border-gray-700">{{ $line->item_id }}</td>
                        <td class="border border-gray-200 px-3 py-2 text-right dark:border-gray-700">{{ $line->item?->name }}</td>
                        <td class="border border-gray-200 px-3 py-2 text-center dark:border-gray-700">
                            <span dir="ltr" class="purchase-inv-num purchase-inv-num--center">{{ $formatWhole($line->qty_primary) }}</span>
                        </td>
                        @if ($dualUnit)
                            <td class="border border-gray-200 px-3 py-2 text-center dark:border-gray-700">
                                <span dir="ltr" class="purchase-inv-num purchase-inv-num--center">{{ $formatWhole($line->qty_secondary) }}</span>
                            </td>
                        @endif
                        <td class="border border-gray-200 px-3 py-2 text-end dark:border-gray-700">
                            <span dir="ltr" class="purchase-inv-num">{{ $formatWhole($line->unit_cost_primary) }}</span>
                        </td>
                        <td class="border border-gray-200 px-3 py-2 text-end dark:border-gray-700">
                            <span dir="ltr" class="purchase-inv-num">{{ $formatWhole($line->line_cost_total) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <style>
        .purchase-inv-lines-wrap {
            width: 100%;
            max-width: none;
        }

        .purchase-inv-lines-table {
            table-layout: fixed;
        }

        .purchase-inv-lines-table .col-id {
            width: 4.5rem;
        }

        .purchase-inv-lines-table .col-name {
            width: auto;
        }

        .purchase-inv-lines-table .col-qty {
            width: 5.5rem;
        }

        .purchase-inv-lines-table .col-money {
            width: 7rem;
        }

        .purchase-inv-num {
            display: inline-block;
            width: 100%;
            unicode-bidi: isolate;
            text-align: right;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .purchase-inv-num--center {
            text-align: center;
        }
    </style>
@endif
