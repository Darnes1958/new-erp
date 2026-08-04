@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center;">
        <label style="font-size: 14pt;">{{ $reportTitle }}</label>
    </div>

    @include('pdf.installments.partials.filter-lines', ['filterLines' => $filterLines ?? []])

    <table>
        <thead>
        <tr>
            <th>اسم الزبون</th>
            <th style="width: 18%;">رقم العقد</th>
            <th style="width: 12%;">التاريخ</th>
            <th style="width: 10%;">المبلغ</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \App\Support\Utf8Text::clean(\App\Filament\Ins\Resources\InstallmentReturns\Tables\InstallmentReturnsTable::customerLabel($row)) }}</td>
                <td style="text-align: center;">{{ $row->displayContractId() }}</td>
                <td style="text-align: center;">{{ $row->suspended_date?->format('Y-m-d') }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
