<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link href="https://fonts.googleapis.com/css2?family=Amiri&display=swap" rel="stylesheet"/>
    <style>
        body {
            direction: rtl;
            font-family: Amiri, serif;
            font-size: 16px;
            line-height: 32px;
        }

        .dotted {
            display: inline-block;
            border-style: dotted;
            border-top: none;
            border-right: none;
            border-left: none;
            padding-left: 4px;
            padding-right: 4px;
            text-align: center;
        }

        .section-title {
            font-size: 18pt;
            color: #bf800c;
            text-align: right;
        }

        .line {
            text-align: right;
            font-size: 11pt;
        }
    </style>
</head>
<body>
<div style="position: relative;">
    <div style="text-align: right; display: inline-flex; position: absolute; right: 0; top: 0;">
        <label style="padding-left: 4px;">رقم العقد</label>
        <label style="padding-left: 4px;">{{ $contract->id }}</label>
    </div>
    <div style="text-align: left; display: inline-flex; position: absolute; top: 0; left: 0;">
        <label style="padding-left: 4px;">تاريخ العقد</label>
        <label style="padding-left: 4px;">{{ $contract->contract_start?->format('Y-m-d') }}</label>
    </div>
</div>

<div style="text-align: center; font-size: 18pt; margin-top: 2rem;">
    <label>{{ \App\Support\Utf8Text::clean($company?->display_name) }}</label>
</div>
@if ($company?->display_name_suffix)
    <div style="text-align: center; font-size: 18pt;">
        <label>{{ \App\Support\Utf8Text::clean($company->display_name_suffix) }}</label>
    </div>
@endif

<div style="display: inline-flex;">
    <label style="text-align: center; font-size: 18pt; padding-right: 300px;">عقد بيع لأجل</label>
</div>

<div class="section-title">أولا بيانات تعبأ من قبل المحل</div>

<div class="line">
    <label style="display: inline-block; padding-right: 4px;">الإخوة مصرف /</label>
    <label class="dotted" style="width: 350px;">{{ \App\Support\Utf8Text::clean($payrollBankName) }}</label>
    <label style="display: inline-block;">نرجو منكم إستقطاع الأقساط الشهرية المترتبة علي</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">الأخ /</label>
    <label class="dotted" style="width: 160px;">{{ \App\Support\Utf8Text::clean($contract->customer?->name) }}</label>
    <label style="display: inline-block;">لصالح هذه الشركة علماً بان القيمة الإجمالية المترتبة علي هذه الاقساط</label>
    <label class="dotted" style="width: 80px;">{{ \App\Support\ErpNumber::money($contract->contract_total) }}</label>
    <label style="display: inline-block;">دينار ليبي</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">علي أن يبدا الإستقطاع من شهر</label>
    <label class="dotted" style="width: 70px;">{{ $periodFrom }}</label>
    <label style="display: inline-block;">إلي شهر</label>
    <label class="dotted" style="width: 70px;">{{ $periodTo }}</label>
    <label style="display: inline-block;">عدد الاشهر</label>
    <label class="dotted" style="width: 30px;">{{ $contract->installment_count }}</label>
    <label style="display: inline-block;">و قيمة الإستقطاع الشهري</label>
    <label class="dotted" style="width: 85px;">{{ \App\Support\ErpNumber::money($contract->installment_amount) }}</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">وذلك لحساب الشركة التجميعي رقم</label>
    <label class="dotted" style="width: 160px;">{{ \App\Support\Utf8Text::clean($payrollAccountNumber) }}</label>
    <label style="display: inline-block;">مصرف</label>
    <label class="dotted" style="width: 300px;">{{ \App\Support\Utf8Text::clean($payrollBankName) }}</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">علي أن يتحمل الزبون أتعاب المصرف</label>
</div>

<div class="section-title">ثانياً بيانات تعبأ من قبل الزبون</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">انا الموقع أدناه</label>
    <label class="dotted" style="width: 300px;">{{ \App\Support\Utf8Text::clean($contract->customer?->name) }}</label>
    <label style="display: inline-block;">بطاقة شخصية رقم</label>
    <label class="dotted" style="width: 160px;">{{ \App\Support\Utf8Text::clean($contract->customer?->card_no) }}</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">اخول مصرف</label>
    <label class="dotted" style="width: 360px;">{{ \App\Support\Utf8Text::clean($payrollBankName) }}</label>
    <label style="display: inline-block;">باستقطاع مبلغ وقدره</label>
    <label class="dotted" style="width: 120px;">{{ \App\Support\ErpNumber::money($contract->installment_amount) }}</label>
</div>
<div class="line">
    <label style="display: inline-block;">من حسابي رقم</label>
    <label class="dotted" style="width: 200px;">{{ \App\Support\Utf8Text::clean($contract->bank_account_number) }}</label>
    <label style="display: inline-block;">لصالح الحساب الخاص بالشركة رقم</label>
    <label class="dotted" style="width: 200px;">{{ \App\Support\Utf8Text::clean($payrollAccountNumber) }}</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">وذلك باستقطاع المبلغ المذكور من حسابي طرفكم شهرياً علي أن يبدا الإستقطاع من شهر</label>
    <label class="dotted" style="width: 100px;">{{ $contract->contract_start?->format('Y-m-d') }}</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">إلي أن تصل قيمة الإستقطاع مبلغ وقدره</label>
    <label class="dotted" style="width: 300px;">{{ \App\Support\ErpNumber::money($contract->contract_total) }}</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">وأن أتحمل أتعاب الخدمات المصرفية ولا يحق لي إيقاف الإستقطاع إلا بموافقة خطية من الشركة وذلك إقرار مني بذلك</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">الإسم</label>
    <label class="dotted" style="width: 300px;">{{ \App\Support\Utf8Text::clean($contract->customer?->name) }}</label>
    <label style="display: inline-block; padding-right: 4px;">التوقيع</label>
    <label class="dotted" style="width: 280px;"></label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">مدار</label>
    <label class="dotted" style="width: 300px;">{{ \App\Support\Utf8Text::clean($contract->customer?->mdar) }}</label>
    <label style="display: inline-block; padding-right: 4px;">لبيانا</label>
    <label class="dotted" style="width: 300px;">{{ \App\Support\Utf8Text::clean($contract->customer?->libyana) }}</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">ملاحظات /</label>
    <label class="dotted" style="width: 600px;">{{ \App\Support\Utf8Text::clean($contract->notes) }}</label>
</div>

<div class="section-title">ثالثاً بيانات تعبأ من قبل المصرف</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">يفيد مصرف</label>
    <label class="dotted" style="width: 200px;"></label>
    <label style="display: inline-block;">فرع</label>
    <label class="dotted" style="width: 100px;"></label>
    <label style="display: inline-block;">بالموافقة علي خصم الأقساط الشهرية من حساب</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">الاخ</label>
    <label class="dotted" style="width: 200px;"></label>
    <label style="display: inline-block;">رقم</label>
    <label class="dotted" style="width: 200px;"></label>
    <label style="display: inline-block;">في حال توفر الرصيد أو ورود</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">المرتبات او السحب علي المكشوف بعد خصم الإستقطاعات والإلتزامات الخاصة بالمصرف وقيدها إلي حساب</label>
</div>
<div class="line">
    <label style="display: inline-block; padding-right: 4px;">الشركة رقم</label>
    <label class="dotted" style="width: 200px;"></label>
    <label style="display: inline-block;">طرف مصرف</label>
    <label class="dotted" style="width: 100px;"></label>
    <label style="display: inline-block;">فرع</label>
    <label class="dotted" style="width: 100px;"></label>
</div>

<div style="position: fixed; bottom: 40px; left: 80px; font-size: 18pt; color: #bf800c;">
    <label>إعتماد الشركة</label>
</div>
<div style="position: fixed; bottom: 40px; right: 80px; font-size: 18pt; color: #bf800c;">
    <label>إعتماد المصرف</label>
</div>
<div style="position: fixed; bottom: 20px; right: 40px; font-size: 18pt; color: #bf800c;">
    <label class="dotted" style="width: 200px;"></label>
</div>
<div style="position: fixed; bottom: 20px; left: 40px; font-size: 18pt; color: #bf800c;">
    <label class="dotted" style="width: 200px;"></label>
</div>
</body>
</html>
