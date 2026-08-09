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

        .stats {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            border: 1pt solid gray;
        }

        .stats-row {
            display: table-row;
        }

        .stats-cell {
            display: table-cell;
            padding: 6px 8px;
            border: 1pt solid gray;
            text-align: center;
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
        <label style="font-size: 14pt;">خلاصة الحركة اليومية</label>
    </div>
    <div class="meta">
        @if ($dateFrom || $dateTo)
            <div>الفترة: {{ $dateFrom ?? '—' }} — {{ $dateTo ?? '—' }}</div>
        @endif
        @if ($warehouseName)
            <div>المخزن: {{ $warehouseName }}</div>
        @endif
    </div>

    <div class="stats">
        <div class="stats-row">
            <div class="stats-cell"><strong>مشتريات</strong><br>{{ number_format($stats['purchases'], 3, '.', ',') }}</div>
            <div class="stats-cell"><strong>مبيعات</strong><br>{{ number_format($stats['sales'], 3, '.', ',') }}</div>
            <div class="stats-cell"><strong>قبض</strong><br>{{ number_format($stats['collections'], 3, '.', ',') }}</div>
            <div class="stats-cell"><strong>دفع</strong><br>{{ number_format($stats['payments'], 3, '.', ',') }}</div>
        </div>
        <div class="stats-row">
            <div class="stats-cell"><strong>ترجيع مشتريات</strong><br>{{ number_format($stats['purchase_returns'], 3, '.', ',') }}</div>
            <div class="stats-cell"><strong>ترجيع مبيعات</strong><br>{{ number_format($stats['sales_returns'], 3, '.', ',') }}</div>
            <div class="stats-cell"><strong>مصروفات</strong><br>{{ number_format($stats['expenses'], 3, '.', ',') }}</div>
            <div class="stats-cell"><strong>صافي التدفق</strong><br>{{ number_format($stats['net_cash_flow'], 3, '.', ',') }}</div>
        </div>
    </div>

    <h3>المشتريات</h3>
    <table>
        <thead>
        <tr>
            <th>نقطة البيع</th>
            <th>الإجمالي</th>
            <th>المدفوع</th>
            <th>الباقي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($purchases as $row)
            <tr>
                <td>{{ $row->warehouse_name }}</td>
                <td>{{ number_format((float) $row->total_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->paid_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->balance_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>المبيعات</h3>
    <table>
        <thead>
        <tr>
            <th>نقطة البيع</th>
            <th>الإجمالي</th>
            <th>المدفوع</th>
            <th>الباقي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($sales as $row)
            <tr>
                <td>{{ $row->warehouse_name }}</td>
                <td>{{ number_format((float) $row->total_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->paid_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->balance_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>إيصالات الموردين</h3>
    <table>
        <thead>
        <tr>
            <th>البيان</th>
            <th>طريقة الدفع</th>
            <th>الخزينة / الحساب</th>
            <th>قبض</th>
            <th>دفع</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($supplierPayments as $row)
            <tr>
                <td>{{ $service->transactionKindLabel($row->transaction_kind) }}</td>
                <td>{{ $row->payment_method_name }}</td>
                <td>{{ $service->paymentSourceLabel($row->bank_account_name ?? null, $row->cash_box_name ?? null) }}</td>
                <td>{{ number_format((float) $row->collection_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->payment_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>إيصالات الزبائن</h3>
    <table>
        <thead>
        <tr>
            <th>البيان</th>
            <th>طريقة الدفع</th>
            <th>الخزينة / الحساب</th>
            <th>قبض</th>
            <th>دفع</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($customerReceipts as $row)
            <tr>
                <td>{{ $service->transactionKindLabel($row->transaction_kind) }}</td>
                <td>{{ $row->payment_method_name }}</td>
                <td>{{ $service->paymentSourceLabel($row->bank_account_name ?? null, $row->cash_box_name ?? null) }}</td>
                <td>{{ number_format((float) $row->collection_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->payment_amount, 3, '.', ',') }}</td>
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
            <th>نوع المصروف</th>
            <th>دفعت من</th>
            <th>المبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($expenses as $row)
            <tr>
                <td>{{ $row->expense_type_name }}</td>
                <td>{{ $row->payment_source_name }}</td>
                <td>{{ number_format((float) $row->total_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>المرتبات</h3>
    <table>
        <thead>
        <tr>
            <th>البيان</th>
            <th>المبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($salaries as $row)
            <tr>
                <td>{{ $row->transaction_type }}</td>
                <td>{{ number_format((float) $row->total_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="2" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>الإيجارات</h3>
    <table>
        <thead>
        <tr>
            <th>البيان</th>
            <th>المبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($rents as $row)
            <tr>
                <td>{{ $row->transaction_type }}</td>
                <td>{{ number_format((float) $row->total_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="2" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>ترجيع مبيعات</h3>
    <table>
        <thead>
        <tr>
            <th>التاريخ</th>
            <th>الإجمالي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($salesReturns as $row)
            <tr>
                <td>{{ optional($row->return_date)->format('Y-m-d') }}</td>
                <td>{{ number_format((float) $row->total_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="2" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>ترجيع مشتريات</h3>
    <table>
        <thead>
        <tr>
            <th>التاريخ</th>
            <th>الإجمالي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($purchaseReturns as $row)
            <tr>
                <td>{{ optional($row->return_date)->format('Y-m-d') }}</td>
                <td>{{ number_format((float) $row->total_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="2" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>أرصدة الخزائن</h3>
    <table>
        <thead>
        <tr>
            <th>الخزينة</th>
            <th>وارد</th>
            <th>صادر</th>
            <th>الصافي</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($cashBoxes as $row)
            <tr>
                <td>{{ $row->cash_box_name }}</td>
                <td>{{ number_format((float) $row->inflow_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->outflow_amount, 3, '.', ',') }}</td>
                <td>{{ number_format((float) $row->net_amount, 3, '.', ',') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align: center;">لا توجد بيانات</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
