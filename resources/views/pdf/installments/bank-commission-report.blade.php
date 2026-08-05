@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 12px;">
        <label style="font-size: 14pt;">{{ $reportTitle }}</label>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 18%;">المصرف الأم</th>
            <th style="width: 22%;">الحساب التجميعي</th>
            <th style="width: 14%;">عدد الأقساط</th>
            <th style="width: 23%;">اجمالي الأقساط المحصلة</th>
            <th style="width: 23%;">العمولة</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \App\Support\Utf8Text::clean($row->bankMain?->name ?? '—') }}</td>
                <td>{{ \App\Support\Utf8Text::clean($row->name) }}</td>
                <td style="text-align: center;">{{ (int) ($row->installments_count ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($row->collected_total ?? 0) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($commissionFor($row)) }}</td>
            </tr>
        @endforeach
        <tr style="font-weight: bold;">
            <td colspan="2">الإجمالي</td>
            <td style="text-align: center;">{{ $summary['installments_count'] }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['collected_total']) }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['commission_total']) }}</td>
        </tr>
        </tbody>
    </table>
@endsection
