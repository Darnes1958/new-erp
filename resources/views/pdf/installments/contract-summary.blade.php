@extends('pdf.installments.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 8px;">
        <label style="font-size: 14pt;">رقم العقد</label>
        <label style="font-size: 14pt; margin-right: 8px;">{{ $contract->id }}</label>
    </div>

    <table style="border: none;">
        <tbody>
        <tr style="border: none; line-height: 18px;">
            <td style="border: none; width: 12%; font-size: 12pt;">تاريخ العقد</td>
            <td style="width: 15%; text-align: center; border: none;">{{ $contract->contract_start?->format('Y-m-d') }}</td>
            <td style="border: none; width: 6%;"></td>
            <td style="border: none; width: 15%; font-size: 12pt;">اسم الزبون</td>
            <td style="width: 24%; border: none;">{{ \App\Support\Utf8Text::clean($contract->customer?->name) }}</td>
        </tr>
        <tr style="border: none; line-height: 18px;">
            <td style="border: none; width: 12%; font-size: 12pt;">رقم الحساب</td>
            <td style="width: 15%; text-align: center; border: none;">{{ \App\Support\Utf8Text::clean($contract->bank_account_number) }}</td>
            <td style="border: none; width: 2%;"></td>
            <td style="border: none; width: 12%; font-size: 12pt;">اسم المصرف</td>
            <td style="width: 30%; border: none;">{{ \App\Support\Utf8Text::clean($contract->installmentBank?->name) }}</td>
        </tr>
        <tr style="border: none; line-height: 18px;">
            <td style="border: none; width: 12%; font-size: 12pt;">اجمالي التقسيط</td>
            <td style="width: 15%; border: none;">{{ \App\Support\ErpNumber::money($contract->contract_total) }}</td>
            <td style="border: none; width: 6%;"></td>
            <td style="border: none; width: 15%; font-size: 12pt;"></td>
            <td style="width: 24%; text-align: center; border: none;"></td>
        </tr>
        <tr style="border: none; line-height: 18px;">
            <td style="border: none; width: 12%; font-size: 12pt;">المطلوب</td>
            <td style="width: 15%; border: none;">{{ \App\Support\ErpNumber::money($contract->balance) }}</td>
            <td style="border: none; width: 6%;"></td>
            <td style="border: none; width: 12%; font-size: 12pt;">المسدد</td>
            <td style="width: 24%; text-align: center; border: none;">{{ \App\Support\ErpNumber::money($contract->total_paid) }}</td>
        </tr>
        <tr style="border: none; line-height: 18px;">
            <td style="border: none; width: 12%; font-size: 12pt;">القسط</td>
            <td style="width: 15%; border: none;">{{ \App\Support\ErpNumber::money($contract->installment_amount) }}</td>
            <td style="border: none; width: 6%;"></td>
            <td style="border: none; width: 15%; font-size: 12pt;">عدد الأقساط</td>
            <td style="width: 24%; text-align: center; border: none;">{{ $contract->installment_count }}</td>
        </tr>
        </tbody>
    </table>

    <br>

    <table>
        <thead>
        <tr>
            <th style="width: 12%;">ت</th>
            <th style="width: 20%;">تاريخ الاستحقاق</th>
            <th style="width: 20%;">تاريخ الخصم</th>
            <th style="width: 16%;">الخصم</th>
            <th style="width: 32%;">طريقة الخصم</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($deductions as $deduction)
            <tr>
                <td style="text-align: center;">{{ $deduction->sequence }}</td>
                <td style="text-align: center;">{{ $deduction->installment_due_date?->format('Y-m-d') }}</td>
                <td style="text-align: center;">{{ $deduction->deduction_date?->format('Y-m-d') }}</td>
                <td style="text-align: center;">{{ \App\Support\ErpNumber::money($deduction->deducted_amount) }}</td>
                <td style="text-align: center;">
                    {{ \App\Enums\InstallmentDeductionType::tryFrom((int) $deduction->deduction_type_id)?->getLabel() }}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
