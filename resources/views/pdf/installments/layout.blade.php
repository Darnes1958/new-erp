<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&display=swap" rel="stylesheet"/>
    <style>
        body {
            position: relative;
            direction: rtl;
            font-family: Amiri, serif;
        }

        table {
            width: 96%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 8px;
        }

        tr {
            line-height: 20px;
        }

        th {
            text-align: center;
            border: 1pt solid gray;
            font-size: 14px;
            height: 30px;
            background: #9dc1d3;
        }

        td {
            text-align: right;
            border: 1pt solid lightgray;
            padding: 2px 6px;
        }

        .letter-frame {
            border: 1.8pt solid #5a8a9a;
            padding: 12px 16px 18px;
            min-height: 255mm;
            box-sizing: border-box;
        }

        .letter-rule {
            border: none;
            border-top: 1.6pt solid #5a8a9a;
            border-bottom: 0.5pt solid #5a8a9a;
            height: 3px;
            margin: 8px 0 16px;
        }
    </style>
</head>
<body>
@if ($letterHeader ?? false)
    <div class="letter-frame">
        <div>
            @include('pdf.installments.partials.stop-letter-header', [
                'company' => $company ?? null,
                'reportDate' => $reportDate ?? null,
            ])
        </div>
        <div class="letter-rule"></div>
@else
    <div>
        @include('pdf.partials.company-header', ['company' => $company ?? null])
    </div>
    <br>
@endif

@yield('content')

@if ($letterHeader ?? false)
    </div>
@endif
</body>
</html>
