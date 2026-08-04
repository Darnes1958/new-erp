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
            border-collapse: collapse;
            border: 1pt solid lightgray;
            margin-right: 12px;
            font-size: 12px;
        }

        tr, th, td {
            border: 1pt solid lightgray;
        }
    </style>
</head>
<body>
<div>
    <div style="display: inline-flex; width: 100%;">
        <div style="text-align: left; position: absolute; left: 0;">
            <label style="padding-left: 4px;">فاتورة عرض رقم: {{ $invoice->id }}</label>
            <div>
                <label style="margin-right: 12px;">بتاريخ:</label>
                <label style="font-size: 12px;">{{ $invoice->invoice_date?->format('Y-m-d') }}</label>
            </div>
        </div>

        <div style="text-align: left; position: absolute; right: 0;">
            @include('pdf.partials.company-header', [
                'company' => $company,
                'nameSize' => '24pt',
                'suffixSize' => '18pt',
                'addressSize' => '14pt',
            ])
        </div>
    </div>

    <br><br><br><br>

    <div style="text-align: center; margin: 16px 0 24px 0;">
        <label style="font-family: Amiri; font-size: 22pt; font-weight: bold;">فاتورة عرض</label>
    </div>

    <div>
        <label style="margin-right: 12px;">اسم الزبون:</label>
        <label>{{ $invoice->customer?->name }}</label>
    </div>
    <div>
        <label style="margin-right: 12px;">صدرت من:</label>
        <label>{{ $invoice->warehouse?->name }}</label>
    </div>
    <br>

    <table width="100%" align="right" style="border: none;">
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
                <td style="text-align: right;">{{ number_format((float) $line->unit_price_primary, 3) }}</td>
                <td style="text-align: right;">{{ number_format((float) $line->line_total, 3) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tbody>
        @php($summaryColspan = $hasDualUnit ? 3 : 2)
        <tr style="border: none;">
            <td style="border: none;" colspan="{{ $summaryColspan }}"></td>
            <td style="padding: 4px; border: none;">إجمالي الفاتورة</td>
            <td style="font-weight: bold; text-align: center; background: lightgray;">{{ number_format((float) $invoice->lines_subtotal, 3) }}</td>
        </tr>
        @if ((float) $invoice->difference_amount > 0)
            <tr style="border: none;">
                <td style="border: none;" colspan="{{ $summaryColspan }}"></td>
                <td style="padding: 4px; border: none;">عمولة مصرفية</td>
                <td style="font-weight: bold; text-align: center; background: lightgray;">{{ number_format((float) $invoice->difference_amount, 3) }}</td>
            </tr>
        @endif
        @if ((float) $invoice->extra_cost > 0)
            <tr style="border: none;">
                <td style="border: none;" colspan="{{ $summaryColspan }}"></td>
                <td style="padding: 4px; border: none;">تكلفة إضافية</td>
                <td style="font-weight: bold; text-align: center; background: lightgray;">{{ number_format((float) $invoice->extra_cost, 3) }}</td>
            </tr>
        @endif
        @if ((float) $invoice->difference_amount > 0 || (float) $invoice->extra_cost > 0)
            <tr style="border: none;">
                <td style="border: none;" colspan="{{ $summaryColspan }}"></td>
                <td style="padding: 4px; border: none;">الإجمالي</td>
                <td style="font-weight: bold; text-align: center; background: lightgray;">{{ number_format((float) $invoice->grand_total, 3) }}</td>
            </tr>
        @endif
        </tbody>
    </table>
</div>
</body>
</html>
