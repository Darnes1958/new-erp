<div class="space-y-4" dir="rtl">
    <div class="rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-gray-800 dark:border-warning-700 dark:bg-gray-900 dark:text-gray-100">
        <div>
            حساب المصرف (الصحيح):
            <b dir="ltr">{{ $accountNumber }}</b>
            @if (filled($customerName ?? null))
                — الاسم في الحافظة: <b>{{ $customerName }}</b>
            @endif
        </div>
        <div class="mt-1">سيتم تصحيح <b>{{ $siblingCount }}</b> قسطاً وارداً بالخطأ لنفس الحساب بعد اختيار العقد.</div>
    </div>

    @livewire('ins.correct-wrong-batch-line-contract-picker', [
        'lineId' => $lineId,
        'accountNumber' => $accountNumber,
        'payrollBankId' => $payrollBankId,
    ], key('wrong-correct-'.$lineId))
</div>
