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
            <th>اسم المورد</th>
            <th style="width: 16%;">مدين</th>
            <th style="width: 16%;">دائن</th>
            <th style="width: 16%;">الرصيد</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \App\Support\Utf8Text::clean($row->name) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->mden) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->daen) }}</td>
                <td style="text-align: center;">{!! \App\Support\ErpNumber::moneySignedHtml($row->raseed) !!}</td>
            </tr>
        @endforeach
        <tr style="font-weight: bold;">
            <td style="text-align: center;">الإجمالي</td>
            <td style="text-align: center;">{{ \App\Support\ErpNumber::money($summary['debit']) }}</td>
            <td style="text-align: center;">{{ \App\Support\ErpNumber::money($summary['credit']) }}</td>
            <td style="text-align: center;">{!! \App\Support\ErpNumber::moneySignedHtml($summary['balance']) !!}</td>
        </tr>
        </tbody>
    </table>
@endsection
