@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 10px;">
        <label style="font-size: 14pt; margin-right: 12px;">كشف إيقاف خصم بدون عقد حتى تاريخ:</label>
        <label style="font-size: 10pt;">{{ $reportDate }}</label>
    </div>

    @include('pdf.installments.partials.filter-lines', ['filterLines' => $filterLines ?? []])

    <div style="text-align: center; margin-bottom: 15px; font-size: 12pt;">
        <label>نأمل منكم إيقاف خصم الأقساط من حسابات الزبائن المبينة في الكشف أدناه</label>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 8%; font-size: 12px;">ت</th>
            <th style="width: 18%; font-size: 12px;">المصرف التجميعي</th>
            <th style="width: 18%; font-size: 12px;">رقم الحساب</th>
            <th style="font-size: 12px;">الاسم</th>
            <th style="width: 15%; font-size: 12px;">تاريخ الإيقاف</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $index => $row)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ \App\Support\Utf8Text::clean($row->payrollBank?->name) }}</td>
                <td style="text-align: center;">{{ \App\Support\Utf8Text::clean($row->account_number) }}</td>
                <td>{{ \App\Support\Utf8Text::clean($row->name) }}</td>
                <td style="text-align: center;">{{ $row->stop_date?->format('Y-m-d') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
