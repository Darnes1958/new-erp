@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 8px;">
        <label style="font-size: 14pt;">{{ $reportTitle }}</label>
    </div>

    <div style="margin-bottom: 12px;">
        <label style="font-size: 14pt; margin-left: 8px;">للمصرف التجميعي :</label>
        <label style="font-size: 10pt;">{{ \App\Support\Utf8Text::clean($payrollBank->name) }}</label>
    </div>

    @if ($type === \App\Enums\BankReportType::Collected)
        <table>
            <thead>
            <tr>
                <th>اسم الزبون</th>
                <th style="width: 10%;">رقم العقد</th>
                <th style="width: 12%;">اجمالي العقد</th>
                <th style="width: 10%;">القسط</th>
                <th style="width: 10%;">المسدد</th>
                <th style="width: 12%;">تاريخ الخصم</th>
                <th style="width: 10%;">الخصم</th>
            </tr>
            </thead>
            <tbody>
            @php
                $sumContractTotal = 0;
                $sumPaid = 0;
                $sumDeducted = 0;
            @endphp
            @foreach ($rows as $row)
                @php
                    $contract = $row->installmentContract;
                    $sumContractTotal += (float) ($contract?->contract_total ?? 0);
                    $sumPaid += (float) ($contract?->total_paid ?? 0);
                    $sumDeducted += (float) $row->deducted_amount;
                @endphp
                <tr>
                    <td>{{ \App\Support\Utf8Text::clean($contract?->customer?->name) }}</td>
                    <td style="text-align: center;">{{ $row->installment_contract_id }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($contract?->contract_total) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($contract?->installment_amount) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($contract?->total_paid) }}</td>
                    <td style="text-align: center;">{{ $row->deduction_date?->format('Y-m-d') }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->deducted_amount) }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td>الإجمالي</td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumContractTotal) }}</td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumPaid) }}</td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumDeducted) }}</td>
            </tr>
            </tbody>
        </table>
    @elseif ($type === \App\Enums\BankReportType::Late)
        <table>
            <thead>
            <tr>
                <th>اسم الزبون</th>
                <th style="width: 8%;">رقم العقد</th>
                <th style="width: 16%;">رقم الحساب</th>
                <th style="width: 10%;">اجمالي العقد</th>
                <th style="width: 8%;">القسط</th>
                <th style="width: 10%;">المسدد</th>
                <th style="width: 8%;">المتأخرة</th>
                <th style="width: 12%;">ت.آخر قسط</th>
            </tr>
            </thead>
            <tbody>
            @php
                $sumContractTotal = 0;
                $sumPaid = 0;
            @endphp
            @foreach ($rows as $row)
                @php
                    $sumContractTotal += (float) $row->contract_total;
                    $sumPaid += (float) $row->total_paid;
                @endphp
                <tr>
                    <td>{{ \App\Support\Utf8Text::clean($row->customer?->name) }}</td>
                    <td style="text-align: center;">{{ $row->id }}</td>
                    <td style="text-align: center;">{{ \App\Support\Utf8Text::clean($row->bank_account_number) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::format($row->contract_total, 0) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->installment_amount) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::format($row->total_paid, 0) }}</td>
                    <td style="text-align: center;">{{ (int) $row->late_amount }}</td>
                    <td style="text-align: center;">{{ $row->last_deduction_month?->format('Y-m-d') }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td>الإجمالي</td>
                <td></td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::format($sumContractTotal, 0) }}</td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::format($sumPaid, 0) }}</td>
                <td></td>
                <td></td>
            </tr>
            </tbody>
        </table>
    @elseif ($type === \App\Enums\BankReportType::Uncollected)
        <table>
            <thead>
            <tr>
                <th>اسم الزبون</th>
                <th style="width: 10%;">رقم العقد</th>
                <th style="width: 12%;">اجمالي العقد</th>
                <th style="width: 10%;">القسط</th>
                <th style="width: 10%;">المسدد</th>
                <th style="width: 12%;">الرصيد</th>
                <th style="width: 12%;">تاريخ آخر خصم</th>
            </tr>
            </thead>
            <tbody>
            @php
                $sumContractTotal = 0;
                $sumPaid = 0;
                $sumBalance = 0;
            @endphp
            @foreach ($rows as $row)
                @php
                    $sumContractTotal += (float) $row->contract_total;
                    $sumPaid += (float) $row->total_paid;
                    $sumBalance += (float) $row->balance;
                @endphp
                <tr>
                    <td>{{ \App\Support\Utf8Text::clean($row->customer?->name) }}</td>
                    <td style="text-align: center;">{{ $row->id }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->contract_total) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->installment_amount) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->total_paid) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->balance) }}</td>
                    <td style="text-align: center;">{{ $row->last_deduction_month?->format('Y-m-d') }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td>الإجمالي</td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumContractTotal) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumPaid) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumBalance) }}</td>
                <td></td>
                <td></td>
            </tr>
            </tbody>
        </table>
    @else
        <table>
            <thead>
            <tr>
                <th>اسم الزبون</th>
                <th style="width: 10%;">رقم العقد</th>
                <th style="width: 18%;">رقم الحساب</th>
                <th style="width: 12%;">اجمالي العقد</th>
                <th style="width: 10%;">القسط</th>
                <th style="width: 10%;">المسدد</th>
                <th style="width: 12%;">الرصيد</th>
            </tr>
            </thead>
            <tbody>
            @php
                $sumContractTotal = 0;
                $sumPaid = 0;
                $sumBalance = 0;
            @endphp
            @foreach ($rows as $row)
                @php
                    $sumContractTotal += (float) $row->contract_total;
                    $sumPaid += (float) $row->total_paid;
                    $sumBalance += (float) $row->balance;
                @endphp
                <tr>
                    <td>{{ \App\Support\Utf8Text::clean($row->customer?->name) }}</td>
                    <td style="text-align: center;">{{ $row->id }}</td>
                    <td style="text-align: center;">{{ \App\Support\Utf8Text::clean($row->bank_account_number) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->contract_total) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->installment_amount) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->total_paid) }}</td>
                    <td style="text-align: center;">{{ \App\Support\ErpNumber::money($row->balance) }}</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td>الإجمالي</td>
                <td></td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumContractTotal) }}</td>
                <td></td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumPaid) }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($sumBalance) }}</td>
            </tr>
            </tbody>
        </table>
    @endif
@endsection
