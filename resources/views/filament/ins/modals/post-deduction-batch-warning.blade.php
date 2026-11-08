<div style="direction: rtl; text-align: right; line-height: 1.7; font-size: 0.95rem;">
    <p style="margin-bottom: 0.75rem; font-weight: 600; color: rgb(21 128 61);">
        أنت بصدد <strong>ترحيل الحافظة رقم {{ $batch->id }}</strong>
        @if(filled($batch->branchDisplayName()))
            — {{ $batch->branchDisplayName() }}
        @endif
    </p>

    <p style="margin-bottom: 0.75rem;">
        الترحيل عملية محاسبية مهمة: تُخصم الأقساط من العقود وتُسجَّل في الحسابات.
        قد تستغرق العملية <strong>عدة دقائق</strong> حسب عدد السطور
        @if(isset($linesCount))
            ({{ number_format($linesCount) }} سطر)
        @endif
        فلا تغلق الصفحة ولا تُعد الضغط على الزر أثناء التنفيذ.
    </p>

    <p style="margin-bottom: 0.5rem; font-weight: 600;">عند التأكيد سيتم:</p>
    <ul style="margin: 0 1.25rem 0.75rem 0; padding: 0; list-style: disc;">
        <li>ترحيل جميع أقساط هذه الحافظة.</li>
        <li>تأثير الخصم على العقود القائمة أو المؤرشفة أو الملغية حسب موقع كل عقد.</li>
        <li>إغلاق الحافظة بعد اكتمال الترحيل.</li>
    </ul>

    <p style="margin-bottom: 0; padding: 0.75rem; border-radius: 0.5rem; background: rgb(255 251 235); border: 1px solid rgb(253 230 138);">
        لا تبدأ الترحيل إلا بعد مراجعة السطور والتأكد من صحة البيانات.
        اضغط <strong>تأكيد الترحيل</strong> للمتابعة، أو <strong>إلغاء</strong> للرجوع دون تنفيذ.
    </p>
</div>
