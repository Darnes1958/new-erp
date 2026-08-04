@extends('pdf.installments.layout')

@section('content')
    <div style="display: flex; flex-direction: row; justify-content: center; align-items: center; font-size: 14pt; margin-top: 16px; margin-bottom: 16px;">
        <label>السادة المحترومون / </label>
        <label>{{ \App\Support\Utf8Text::clean($payrollBank->name) }}</label>
    </div>

    <div style="font-size: 14pt; line-height: 2; margin-bottom: 24px;">
        <label>تحية طيبة .. </label>
        <div style="display: flex; flex-wrap: wrap;">
            <label>نأمل منكم إيقاف خصم الأقساط من حساب السيد / </label>
            <label>{{ \App\Support\Utf8Text::clean($contract->customer?->name) }}</label>
        </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; font-size: 14pt; line-height: 2; margin-right: 40px;">
        <label>حساب جاري رقم &nbsp;</label>
        <label>{{ \App\Support\Utf8Text::clean($contract->bank_account_number) }}&nbsp;&nbsp;&nbsp;</label>

        @if ((float) $contract->installment_amount != 0)
            <label>وقيمة القسط&nbsp;</label>
            <label>{{ \App\Support\ErpNumber::money($contract->installment_amount) }}</label>
        @endif
    </div>

    <div style="display: flex; flex-wrap: wrap; font-size: 14pt; line-height: 2; margin-right: 40px;">
        <label>لحساب الشركة التجميعي رقم&nbsp;</label>
        <label>{{ \App\Support\Utf8Text::clean($payrollBank->account_number) }}</label>
    </div>

    <div style="display: flex; flex-wrap: wrap; font-size: 14pt; line-height: 2; margin-top: 24px;">
        <label>وذلك اعتباراً من تاريخ&nbsp;</label>
        <label>{{ $contract->stop?->stop_date?->format('Y-m-d') }}&nbsp;&nbsp;</label>
        <label>مع رفع الحجز إن وجد </label>
    </div>

    <div style="line-height: 2; text-align: center; font-size: 14pt; margin-top: 16px;">
        <label>نشكركم علي حسن تعاونكم </label>
    </div>

    <div style="line-height: 2; text-align: center; font-size: 14pt;">
        والسلام عليكم ورحمة الله وبركاته
    </div>

    <br><br><br>
    <div style="text-align: left; margin-left: 100px; font-size: 14pt;">التوقيع ................... </div>
    <div style="text-align: left; margin-left: 100px; font-size: 14pt;">مفوض الشركة /</div>
@endsection
