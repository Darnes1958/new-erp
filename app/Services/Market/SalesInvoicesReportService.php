<?php

namespace App\Services\Market;

class SalesInvoicesReportService
{
    public static function installmentPaymentMethodId(): ?int
    {
        $id = (int) config('erp.payment_method_codes.installment');

        return $id > 0 ? $id : null;
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
