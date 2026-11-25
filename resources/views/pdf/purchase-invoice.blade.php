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
            margin-right: 12px;
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

        table.invoice-header {
            width: 100%;
            margin: 0 0 24px 0;
            border: none;
        }

        table.invoice-header tr,
        table.invoice-header td {
            border: none;
            margin: 0;
        }
    </style>
</head>
<body>
<div>
    <table class="invoice-header">
        <tr>
            <td style="width: 38%; vertical-align: top; text-align: right;">
                @include('pdf.partials.company-header', [
                    'company' => $company,
                    'showLogo' => false,
                    'nameSize' => '22pt',
                    'suffixSize' => '11pt',
                    'addressSize' => '11pt',
                    'phoneSize' => '11pt',
                ])
            </td>
            <td style="width: 24%; vertical-align: top; text-align: center;">
                @php($logoSrc = $company?->logoPdfSrc())
                @if ($logoSrc)
                    <img src="{{ $logoSrc }}" alt="" style="max-height: 80px; max-width: 160px;">
                @endif
            </td>
            <td style="width: 38%; vertical-align: top; text-align: left;">
                <label>فاتورة مشتريات رقم: {{ $invoice->id }}</label>
                <div>
                    <label>بتاريخ:</label>
                    <label style="font-size: 12px;">{{ $invoice->invoice_date?->format('Y-m-d') }}</label>
                </div>
            </td>
        </tr>
    </table>

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
            @if ($showBuyPrices ?? false)
                <th width="12%">السعر</th>
                <th width="12%">المجموع</th>
            @endif
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
                @if ($showBuyPrices ?? false)
                    <td style="text-align: right;">{{ number_format((float) $line->unit_cost_primary, 3) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $line->line_cost_total, 3) }}</td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td colspan="2" style="text-align: left; font-weight: bold;">إجمالي الكمية</td>
            <td style="text-align: center; font-weight: bold; background: lightgray;">{{ number_format((float) $invoice->lines->sum('qty_primary'), 3) }}</td>
            @if ($hasDualUnit)
                <td style="text-align: center; font-weight: bold; background: lightgray;">{{ number_format((float) $invoice->lines->sum('qty_secondary'), 3) }}</td>
            @endif
            @if ($showBuyPrices ?? false)
                <td></td>
                <td></td>
            @endif
        </tr>
        <tr style="border: none">
            <td style="border: none" colspan="{{ 1 + ($hasDualUnit ? 1 : 0) + (($showBuyPrices ?? false) ? 2 : 0) }}"></td>
            <td>إجمالي الفاتورة</td>
            <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->lines_subtotal, 3) }}</td>
        </tr>
        @if ((float) $invoice->discount > 0)
            <tr style="border: none">
                <td style="border: none" colspan="{{ 1 + ($hasDualUnit ? 1 : 0) + (($showBuyPrices ?? false) ? 2 : 0) }}"></td>
                <td>الخصم</td>
                <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->discount, 3) }}</td>
            </tr>
        @endif
        <tr style="border: none">
            <td style="border: none" colspan="{{ 1 + ($hasDualUnit ? 1 : 0) + (($showBuyPrices ?? false) ? 2 : 0) }}"></td>
            <td>المدفوع</td>
            <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->amount_paid, 3) }}</td>
        </tr>
        <tr style="border: none">
            <td style="border: none" colspan="{{ 1 + ($hasDualUnit ? 1 : 0) + (($showBuyPrices ?? false) ? 2 : 0) }}"></td>
            <td>المتبقي</td>
            <td style="font-weight: bold; text-align: right; background: lightgray;">{{ number_format((float) $invoice->balance, 3) }}</td>
        </tr>
        </tbody>
    </table>
</div>
</body>
</html>
