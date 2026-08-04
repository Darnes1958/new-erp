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
    </style>
</head>
<body>
<div>
    @include('pdf.partials.company-header', ['company' => $company ?? null])
</div>
<br>

@yield('content')
</body>
</html>
