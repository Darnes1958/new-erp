@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 8px;">
        <label style="font-size: 14pt;">كشف حساب المورد : {{ \App\Support\Utf8Text::clean($supplier->name) }}</label>
    </div>

    <div style="text-align: center; margin-bottom: 8px;">
        <label style="font-size: 12pt;">من تاريخ : {{ $dateFrom }}</label>
    </div>

    <div style="text-align: center; margin-bottom: 8px;">
        <label style="font-size: 12pt;">الرصيد السابق : {{ \App\Support\ErpNumber::money($openingBalance) }}</label>
    </div>

    <div style="text-align: center; margin-bottom: 12px;">
        <label style="font-size: 12pt;">
            مدين : {{ \App\Support\ErpNumber::money($periodTotals['debit']) }}
            &nbsp;&nbsp;
            دائن : {{ \App\Support\ErpNumber::money($periodTotals['credit']) }}
            &nbsp;&nbsp;
            الرصيد : {{ \App\Support\ErpNumber::money($periodTotals['balance']) }}
        </label>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 10%;">البيان</th>
            <th style="width: 10%;">التاريخ</th>
            <th style="width: 8%;">الرقم الألي</th>
            <th style="width: 12%;">طريقة الدفع</th>
            <th style="width: 10%;">مدين</th>
            <th style="width: 10%;">دائن</th>
            <th style="width: 10%;">الرصيد</th>
            <th>ملاحظات</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td style="text-align: center;">{{ \App\Support\Utf8Text::clean($service->transactionKindLabel((int) $row->transaction_kind)) }}</td>
                <td style="text-align: center;">{{ $row->rep_date?->format('Y-m-d') }}</td>
                <td style="text-align: center;">{{ $row->id }}</td>
                <td>{{ \App\Support\Utf8Text::clean($row->payment_method_name) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->mden) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->daen) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->running_balance ?? 0) }}</td>
                <td>{{ \App\Support\Utf8Text::clean($row->notes) }}</td>
            </tr>
        @endforeach
        <tr style="font-weight: bold;">
            <td colspan="4" style="text-align: center;">الإجمالي</td>
            <td style="text-align: center;">{{ \App\Support\ErpNumber::money($periodTotals['debit']) }}</td>
            <td style="text-align: center;">{{ \App\Support\ErpNumber::money($periodTotals['credit']) }}</td>
            <td colspan="2"></td>
        </tr>
        </tbody>
    </table>
@endsection
