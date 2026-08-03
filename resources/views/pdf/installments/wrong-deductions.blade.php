@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center;">
        <label style="font-size: 14pt; margin-right: 12px;">تقرير بالأقساط الواردة بالخطأ حتى تاريخ:</label>
        <label style="font-size: 10pt;">{{ $reportDate }}</label>
    </div>

    @include('pdf.installments.partials.filter-lines', ['filterLines' => $filterLines ?? []])

    <table>
        <thead>
        <tr>
            <th>اسم الزبون</th>
            <th style="width: 18%;">رقم الحساب</th>
            <th style="width: 12%;">التاريخ</th>
            <th style="width: 10%;">القسط</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \App\Support\Utf8Text::clean($row->name) }}</td>
                <td style="text-align: center;">{{ \App\Support\Utf8Text::clean($row->account_number) }}</td>
                <td style="text-align: center;">{{ $row->deduction_date?->format('Y-m-d') }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
