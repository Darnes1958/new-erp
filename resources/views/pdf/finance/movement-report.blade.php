@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 8px;">
        <label style="font-size: 14pt;">{{ \App\Support\Utf8Text::clean($reportTitle) }}</label>
    </div>

    @isset($subtitle)
        <div style="text-align: center; margin-bottom: 8px;">
            <label style="font-size: 12pt;">{{ \App\Support\Utf8Text::clean($subtitle) }}</label>
        </div>
    @endisset

    @isset($balance)
        <div style="margin-bottom: 12px;">
            <label style="font-size: 12pt;">الرصيد : {{ \App\Support\ErpNumber::money($balance) }}</label>
        </div>
    @endisset

    @php
        $service = app(\App\Services\Finance\FinanceMovementReportService::class);
        $total = 0.0;
    @endphp

    @if ($kind === 'expense')
        <table>
            <thead>
            <tr>
                <th style="width: 14%;">التاريخ</th>
                <th>المصرف / الخزينة</th>
                <th style="width: 14%;">المبلغ</th>
                <th style="width: 30%;">ملاحظات</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                @php $total += (float) $row->amount; @endphp
                <tr>
                    <td style="text-align: center;">{{ $row->expense_date?->format('Y-m-d') }}</td>
                    <td>{{ \App\Support\Utf8Text::clean($service->expensePaymentSourceLabel($row)) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->amount) }}</td>
                    <td>{{ \App\Support\Utf8Text::clean($row->notes) }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="2" style="text-align: center;">الإجمالي</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($total) }}</td>
                <td></td>
            </tr>
            </tbody>
        </table>
    @else
        <table>
            <thead>
            <tr>
                <th style="width: 12%;">التاريخ</th>
                <th style="width: 12%;">البيان</th>
                <th>دفعت من</th>
                <th style="width: 12%;">عن شهر</th>
                <th style="width: 12%;">المبلغ</th>
                <th style="width: 24%;">ملاحظات</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                @php $total += (float) $row->amount; @endphp
                <tr>
                    <td style="text-align: center;">{{ $row->transaction_date?->format('Y-m-d') }}</td>
                    <td style="text-align: center;">{{ \App\Support\Utf8Text::clean($row->transaction_type?->getLabel()) }}</td>
                    <td>{{ \App\Support\Utf8Text::clean($service->paymentSourceLabel($row)) }}</td>
                    <td style="text-align: center;">{{ $service->formatPeriodMonth($row->period_month) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->amount) }}</td>
                    <td>{{ \App\Support\Utf8Text::clean($row->notes) }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td colspan="4" style="text-align: center;">الإجمالي</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($total) }}</td>
                <td></td>
            </tr>
            </tbody>
        </table>
    @endif
@endsection
