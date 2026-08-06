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
    <br>
    <div style="text-align: center;">
        <label style="font-size: 14pt; margin-right: 12px;">تقرير عن إذن صرف مخازن رقم</label>
        <label style="font-size: 10pt;">{{ $transfer->id }}</label>
    </div>
    <div>
        <label style="font-size: 14pt; margin-right: 12px;">بتاريخ</label>
        <label style="font-size: 10pt;">{{ $transfer->transfer_date?->format('Y-m-d') }}</label>
    </div>
    <div>
        <label style="font-size: 14pt; margin-right: 12px;">من:</label>
        <label style="font-size: 10pt;">{{ $transfer->warehouseFrom?->name }}</label>
    </div>
    <div>
        <label style="font-size: 14pt; margin-right: 12px;">إلى:</label>
        <label style="font-size: 10pt;">{{ $transfer->warehouseTo?->name }}</label>
    </div>
    <br>

    <table align="right">
        <thead>
            <tr style="background: lightgray;">
                <th style="width: 20%;">رقم الصنف</th>
                <th>اسم الصنف</th>
                <th style="width: 20%;">الكمية</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->lines as $line)
                <tr>
                    <td>{{ $line->item_id }}</td>
                    <td>{{ $line->item?->name }}</td>
                    <td>{{ $line->qty_primary }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
</body>
</html>
