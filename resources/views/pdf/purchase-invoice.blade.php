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
            width: 96%;
            font-size: 14px;
            border-collapse: collapse;
        }

        tr {
            line-height: 20px;
            border: 1pt solid gray;
        }

        th {
            text-align: center;
            font-size: 14px;
            height: 30px;
            border: 1pt solid gray;
        }

        td {
            text-align: right;
            border: 1pt solid gray;
        }
    </style>
</head>
<body>
<div>
    <label style="font-family: Amiri; font-size: 24pt; margin-right: 12px;">{{ $company?->display_name }}</label>
    <br>
    @if ($company?->address)
        <label style="font-family: Amiri; font-size: 18pt; margin-right: 12px;">{{ $company->address }}</label>
        <br>
    @endif
    <br>
    <br>
    <label style="margin-right: 12px;">فاتورة مشتريات رقم: {{ $invoice->id }}</label>
    <div>
        <label style="margin-right: 12px;">بتاريخ:</label>
        <label style="font-size: 12px;">{{ $invoice->invoice_date?->format('Y-m-d') }}</label>
    </div>
    <div>
        <label style="margin-right: 12px;">اسم المورد:</label>
        <label>{{ $invoice->supplier?->name }}</label>
    </div>
    <div>
        <label style="margin-right: 12px;">صدرت من:</label>
        <label>{{ $invoice->warehouse?->name }}</label>
    </div>
    <br>

    <table>
        <thead>
        <tr style="background: #9dc1d3;">
            <th width="12%">رقم الصنف</th>
            <th>اسم الصنف</th>
            <th width="8%">الكمية</th>
            @if ($hasDualUnit)
                <th width="8%">الكمية 2</th>
            @endif
            <th width="12%">السعر</th>
            <th width="12%">المجموع</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td style="color: #0c63e4; text-align: center;">{{ $line->item_id }}</td>
                <td style="text-align: right;">{{ $line->item?->name }}</td>
                <td style="text-align: center;">{{ number_format((float) $line->qty_primary, 0) }}</td>
                @if ($hasDualUnit)
                    <td style="text-align: center;">{{ number_format((float) $line->qty_secondary, 0) }}</td>
                @endif
                <td style="text-align: right;">{{ number_format((float) $line->unit_cost_primary, 3) }}</td>
                <td style="text-align: right;">{{ number_format((float) $line->line_cost_total, 3) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tbody>
        @php($summaryColspan = $hasDualUnit ? 4 : 3)
        <tr style="border: none">
            <td style="border: none" colspan="{{ $summaryColspan }}"></td>
            <td>إجمالي الفاتورة</td>
            <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->lines_subtotal, 3) }}</td>
        </tr>
        @if ((float) $invoice->discount > 0)
            <tr style="border: none">
                <td style="border: none" colspan="{{ $summaryColspan }}"></td>
                <td>الخصم</td>
                <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->discount, 3) }}</td>
            </tr>
        @endif
        <tr style="border: none">
            <td style="border: none" colspan="{{ $summaryColspan }}"></td>
            <td>المدفوع</td>
            <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->amount_paid, 3) }}</td>
        </tr>
        <tr style="border: none">
            <td style="border: none" colspan="{{ $summaryColspan }}"></td>
            <td>المتبقي</td>
            <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->balance, 3) }}</td>
        </tr>
        </tbody>
    </table>
</div>
</body>
</html>
