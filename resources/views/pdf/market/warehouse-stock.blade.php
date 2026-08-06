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
    @include('pdf.partials.company-header', [
        'company' => $company,
        'nameSize' => '24pt',
        'suffixSize' => '18pt',
        'addressSize' => '18pt',
    ])
    <br>
    <div style="text-align: center;">
        <label style="font-size: 14pt; margin-right: 12px;">تقرير عن المخزون</label>
        <label>بتاريخ: {{ $repDate }}</label>
    </div>
    @if ($warehouseName)
        <div>
            <label style="font-size: 14pt; margin-right: 12px;">المكان:</label>
            <label style="font-size: 12pt; font-weight: bolder;">{{ $warehouseName }}</label>
        </div>
    @endif
    <br>

    <table align="right">
        <thead>
            <tr style="background: lightgray;">
                @if ($multiWarehouse && ! $warehouseName)
                    <th style="width: 15%;">المكان</th>
                @endif
                <th>اسم الصنف</th>
                <th style="width: 10%;">رقم الصنف</th>
                <th style="width: 10%;">الرصيد الكلي</th>
                @if ($multiWarehouse)
                    <th style="width: 10%;">رصيد المكان</th>
                @endif
                @if ($showCosts)
                    <th style="width: 10%;">سعر الشراء</th>
                    <th style="width: 10%;">متوسط السعر</th>
                    <th style="width: 10%;">تكلفة المكان</th>
                @endif
                <th style="width: 10%;">سعر البيع</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @if ($multiWarehouse && ! $warehouseName)
                        <td>{{ $row->warehouse_name }}</td>
                    @endif
                    <td>{{ $row->item_name }}</td>
                    <td style="text-align: center;">{{ $row->item_id }}</td>
                    <td>{{ $row->total_qty_primary }}</td>
                    @if ($multiWarehouse)
                        <td>{{ $row->warehouse_qty_primary }}</td>
                    @endif
                    @if ($showCosts)
                        <td>{{ number_format((float) $row->catalog_buy_price, 3, '.', ',') }}</td>
                        <td>{{ number_format((float) $row->avg_unit_cost, 3, '.', ',') }}</td>
                        <td>{{ number_format((float) $row->warehouse_cost_total, 3, '.', ',') }}</td>
                    @endif
                    <td>{{ number_format((float) $row->sell_price_primary, 3, '.', ',') }}</td>
                </tr>
            @endforeach
            @if ($showCosts)
                <tr style="font-weight: bold;">
                    <td>الإجمالي</td>
                    @if ($multiWarehouse && ! $warehouseName)
                        <td></td>
                    @endif
                    <td></td>
                    <td></td>
                    @if ($multiWarehouse)
                        <td></td>
                    @endif
                    <td></td>
                    <td></td>
                    <td>{{ number_format((float) $summary['warehouse_cost_total'], 3, '.', ',') }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
</body>
</html>
