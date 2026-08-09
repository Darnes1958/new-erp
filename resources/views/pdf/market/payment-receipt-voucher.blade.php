<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&display=swap" rel="stylesheet"/>
    <style>
        body {
            direction: rtl;
            font-family: Amiri, serif;
            text-align: right;
            font-size: 14pt;
        }

        .box {
            border: 2px solid #4b5563;
            border-radius: 12px;
            padding: 24px;
            margin-top: 16px;
        }

        .title {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 24px;
        }

        .line {
            margin-bottom: 16px;
        }

        .label {
            font-size: 14pt;
        }

        .value {
            font-weight: bold;
            background: #f9fafb;
            padding: 0 4px;
        }

        .signature {
            margin-top: 48px;
            font-size: 16pt;
        }

        .meta {
            margin-top: 20px;
            font-size: 12pt;
            color: #374151;
        }
    </style>
</head>
<body>
@include('pdf.partials.company-header', [
    'company' => $company,
    'nameSize' => '20pt',
    'suffixSize' => '16pt',
    'addressSize' => '14pt',
])

<div class="box">
    <div class="title">{{ $voucher['title'] }} رقم {{ $voucher['id'] }}</div>

    <div class="line">
        <span class="label">بتاريخ : </span>
        <span class="value">{{ $voucher['date'] }}</span>
    </div>

    <div class="line">
        <span class="label">{{ $voucher['partyLine'] }}</span>
    </div>

    <div class="line">
        <span class="label">مبلغ وقدره : </span>
        <span class="value">{{ $voucher['amount'] }}</span>
        <span class="label"> ({{ $voucher['amountWords'] }})</span>
    </div>

    @if ($voucher['transactionKind'])
        <div class="meta">البيان : {{ $voucher['transactionKind'] }}</div>
    @endif

    @if ($voucher['paymentMethod'] || $voucher['paymentSource'] !== '—')
        <div class="meta">
            @if ($voucher['paymentMethod'])
                طريقة الدفع : {{ $voucher['paymentMethod'] }}
            @endif
            @if ($voucher['paymentSource'] !== '—')
                &nbsp;&nbsp;|&nbsp;&nbsp;بواسطة : {{ $voucher['paymentSource'] }}
            @endif
        </div>
    @endif

    @if ($voucher['warehouse'])
        <div class="meta">المخزن : {{ $voucher['warehouse'] }}</div>
    @endif

    @if ($voucher['notes'])
        <div class="meta">ملاحظات : {{ $voucher['notes'] }}</div>
    @endif

    <div class="signature">
        <div>المستلم</div>
        <div style="margin-top: 32px;">...........................</div>
    </div>
</div>
</body>
</html>
