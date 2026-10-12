@extends('pdf.installments.layout')

@section('content')
    <div style="position: absolute; left: 24px; top: 18px; font-size: 11pt;">
        تاريخ الطباعة : {{ now()->toDateString() }}
    </div>

    <div style="text-align: center; margin-bottom: 12px;">
        <label style="font-size: 14pt;">{{ $reportTitle }}</label>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 16%;">التصنيف</th>
            <th>الاسم</th>
            <th style="width: 14%;">مدين</th>
            <th style="width: 14%;">دائن</th>
            <th style="width: 14%;">الرصيد</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td style="text-align: center;">{{ \App\Support\Utf8Text::clean($row->customer_type_name ?? '—') }}</td>
                <td>{{ \App\Support\Utf8Text::clean($row->name) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->mden) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->daen) }}</td>
                <td style="text-align: center;">{!! \App\Support\ErpNumber::moneySignedHtml($row->raseed) !!}</td>
            </tr>
        @endforeach
        <tr style="font-weight: bold;">
            <td colspan="2" style="text-align: center;">الإجمالي</td>
            <td style="text-align: center;">{{ \App\Support\ErpNumber::money($summary['debit']) }}</td>
            <td style="text-align: center;">{{ \App\Support\ErpNumber::money($summary['credit']) }}</td>
            <td style="text-align: center;">{!! \App\Support\ErpNumber::moneySignedHtml($summary['balance']) !!}</td>
        </tr>
        </tbody>
    </table>
@endsection
