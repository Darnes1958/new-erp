<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&display=swap" rel="stylesheet"/>
    <style>
        body {
            direction: rtl;
            font-family: Amiri, serif;
        }

        table {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        tr {
            line-height: 18px;
            border: 1pt solid gray;
        }

        th {
            text-align: center;
            font-size: 13px;
            height: 26px;
            border: 1pt solid gray;
            background: lightgray;
        }

        td {
            text-align: right;
            border: 1pt solid gray;
            padding: 2px 4px;
        }

        h3 {
            margin: 12px 0 6px;
            font-size: 15px;
        }

        .meta {
            margin-bottom: 10px;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div>
    @include('pdf.partials.company-header', [
        'company' => $company,
        'nameSize' => '24pt',
        'suffixSize' => '18pt',
        'addressSize' => '18pt',
    ])
    <br>
    <div style="text-align: center;">
        <label style="font-size: 14pt;">الحركة اليومية — تفصيلي</label>
    </div>
    <div class="meta">
        @if ($dateFrom || $dateTo)
            <div>الفترة: {{ $dateFrom ?? '—' }} — {{ $dateTo ?? '—' }}</div>
        @endif
        @if ($warehouseName)
            <div>المخزن: {{ $warehouseName }}</div>
        @endif
    </div>

    <h3>فواتير المشتريات</h3>
    <table>
        <thead>
        <tr>
            <th>رقم الفاتورة</th>
            <th>التاريخ</th>
            <th>المورد</th>
            <th>الإجمالي</th>
            <th>المدفوع</th>
            <th>المتبقي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($purchases as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ optional($row->invoice_date)->format('Y-m-d') }}</td>
                <td>{{ $row->supplier?->name }}</td>
                <td>{{ number_format((float) $row->lines_subtotal - (float) $row->discount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->amount_paid, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->balance, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>فواتير المبيعات</h3>
    <table>
        <thead>
        <tr>
            <th>رقم الفاتورة</th>
            <th>التاريخ</th>
            <th>الزبون</th>
            <th>الإجمالي</th>
            <th>المدفوع</th>
            <th>المتبقي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($sales as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ optional($row->invoice_date)->format('Y-m-d') }}</td>
                <td>{{ $row->customer?->name }}</td>
                <td>{{ number_format((float) $row->grand_total, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->amount_paid, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->balance, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>إيصالات الموردين</h3>
    <table>
        <thead>
        <tr>
            <th>الرقم</th>
            <th>التاريخ</th>
            <th>المورد</th>
            <th>البيان</th>
            <th>النوع</th>
            <th>المبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($supplierPayments as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ optional($row->payment_date)->format('Y-m-d') }}</td>
                <td>{{ $row->supplier?->name }}</td>
                <td>{{ $row->transaction_kind?->getLabel() }}</td>
                <td>{{ (int) $row->flow_direction === 0 ? 'قبض' : 'دفع' }}</td>
                <td>{{ number_format((float) $row->amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>إيصالات الزبائن</h3>
    <table>
        <thead>
        <tr>
            <th>الرقم</th>
            <th>التاريخ</th>
            <th>الزبون</th>
            <th>البيان</th>
            <th>النوع</th>
            <th>المبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($customerReceipts as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ optional($row->receipt_date)->format('Y-m-d') }}</td>
                <td>{{ $row->customer?->name }}</td>
                <td>{{ $row->transaction_kind?->getLabel() }}</td>
                <td>{{ (int) $row->flow_direction === 0 ? 'قبض' : 'دفع' }}</td>
                <td>{{ number_format((float) $row->amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>ترجيع مبيعات</h3>
    <table>
        <thead>
        <tr>
            <th>التاريخ</th>
            <th>الزبون</th>
            <th>الصنف</th>
            <th>الكمية</th>
            <th>الإجمالي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($salesReturns as $row)
            <tr>
                <td>{{ optional($row->return_date)->format('Y-m-d') }}</td>
                <td>{{ $row->salesInvoice?->customer?->name }}</td>
                <td>{{ $row->item?->name }}</td>
                <td>{{ number_format((float) $row->qty_primary, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->line_total, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>ترجيع مشتريات</h3>
    <table>
        <thead>
        <tr>
            <th>التاريخ</th>
            <th>المورد</th>
            <th>الصنف</th>
            <th>الكمية</th>
            <th>الإجمالي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($purchaseReturns as $row)
            <tr>
                <td>{{ optional($row->return_date)->format('Y-m-d') }}</td>
                <td>{{ $row->purchaseInvoice?->supplier?->name }}</td>
                <td>{{ $row->item?->name }}</td>
                <td>{{ number_format((float) $row->qty_primary, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->line_total, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>المصروفات</h3>
    <table>
        <thead>
        <tr>
            <th>التاريخ</th>
            <th>البيان</th>
            <th>دفعت من</th>
            <th>المبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($expenses as $row)
            <tr>
                <td>{{ optional($row->expense_date)->format('Y-m-d') }}</td>
                <td>{{ $row->expenseType?->name }}</td>
                <td>{{ $service->paymentSourceLabel($row->bankAccount?->name, $row->cashBox?->name) }}</td>
                <td>{{ number_format((float) $row->amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
