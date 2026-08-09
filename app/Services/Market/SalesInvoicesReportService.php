<?php

namespace App\Services\Market;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Cache;

class SalesInvoicesReportService
{
    public static function installmentPaymentMethodId(): ?int
    {
        return Cache::remember(
            'payment_method_installment_id',
            now()->addHour(),
            fn (): ?int => PaymentMethod::query()->where('code', 'installment')->value('id'),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function tabLabels(): array
    {
        return [
            'all' => 'الكل',
            'installment' => 'تقسيط',
            'installment_active' => 'تقسيط قائم',
            'installment_archive' => 'تقسيط أرشيف',
            'installment_no_contract' => 'تقسيط بدون عقد',
            'cash' => 'نقداً',
            'cash_unpaid' => 'نقداً آجلة',
            'cash_paid' => 'نقداً مدفوعة',
        ];
    }
}
