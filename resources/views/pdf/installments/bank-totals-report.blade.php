@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 12px;">
        <label style="font-size: 14pt;">{{ $reportTitle }}</label>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 8%;">الرقم</th>
            <th>{{ $nameHeading }}</th>
            <th style="width: 10%;">عدد العقود</th>
            <th style="width: 10%;">اجمالي العقود</th>
            <th style="width: 10%;">المسدد</th>
            <th style="width: 10%;">الرصيد</th>
            <th style="width: 10%;">الفائض</th>
            <th style="width: 10%;">الترجيع</th>
            <th style="width: 10%;">بالخطأ</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td style="text-align: center;">{{ $row->id }}</td>
                <td>{{ \App\Support\Utf8Text::clean($row->name) }}</td>
                <td style="text-align: center;">{{ (int) ($row->contracts_count ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($row->contracts_total ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($row->total_paid ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($row->balance_total ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($row->surplus_total ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($row->suspended_total ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($row->wrong_total ?? 0) }}</td>
            </tr>
        @endforeach
        <tr style="font-weight: bold;">
            <td></td>
            <td>الإجمالي</td>
            <td style="text-align: center;">{{ $summary['contracts_count'] }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['contracts_total']) }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['total_paid']) }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['balance_total']) }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['surplus_total']) }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['suspended_total']) }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['wrong_total']) }}</td>
        </tr>
        </tbody>
    </table>
@endsection
