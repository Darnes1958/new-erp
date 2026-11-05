@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 12px;">
        <label style="font-size: 14pt;">{{ $reportTitle }}</label>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 28%;">المصرف التجميعي</th>
            <th style="width: 14%;">عدد الأقساط المحصلة</th>
            <th style="width: 18%;">إجمالي المحصلة</th>
            <th style="width: 22%;">إجمالي المفترض تحصيلها</th>
            <th style="width: 18%;">نسبة التحصيل</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \App\Support\Utf8Text::clean($row->name) }}</td>
                <td style="text-align: center;">{{ $collectedCountFor($row) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($collectedTotalFor($row)) }}</td>
                <td style="text-align: right;">{{ \App\Support\ErpNumber::money($expectedTotalFor($row)) }}</td>
                <td style="text-align: center;">{{ number_format($collectionRatioFor($row), 1) }} %</td>
            </tr>
        @endforeach
        <tr style="font-weight: bold;">
            <td>الإجمالي</td>
            <td style="text-align: center;">{{ $summary['collected_count'] }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['collected_total']) }}</td>
            <td style="text-align: right;">{{ \App\Support\ErpNumber::money($summary['expected_total']) }}</td>
            <td style="text-align: center;">{{ number_format($summary['collection_ratio'], 1) }} %</td>
        </tr>
        </tbody>
    </table>
@endsection
