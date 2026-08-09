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
            margin-top: 12px;
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

        .meta {
            margin-bottom: 10px;
            font-size: 13px;
        }

        .title {
            text-align: center;
            font-size: 14pt;
            margin: 8px 0;
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
    <div class="title">{{ $reportTitle }}</div>
    <div class="meta">
        @if ($dateFrom || $dateTo)
            <div>الفترة: {{ $dateFrom ?? '—' }} — {{ $dateTo ?? '—' }}</div>
        @endif
        @if ($partyName)
            <div>{{ $partyLabel }}: {{ $partyName }}</div>
        @endif
        @if ($warehouseName)
            <div>المخزن: {{ $warehouseName }}</div>
        @endif
        @if ($tabLabel && $tabLabel !== 'الكل')
            <div>{{ $filterLabel ?? 'نوع الفواتير' }}: {{ $tabLabel }}</div>
        @endif
    </div>

    <table>
        <thead>
        <tr>
            @foreach ($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers) }}" style="text-align: center;">لا توجد بيانات</td>
            </tr>
        @endforelse
        </tbody>
        @if ($rows->isNotEmpty() && ! empty($totals))
            <tfoot>
            <tr style="font-weight: bold;">
                @foreach ($totals as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
            </tfoot>
        @endif
    </table>
</div>
</body>
</html>
