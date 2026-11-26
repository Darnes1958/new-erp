<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ConversionReviewIssue extends CompanyModel
{
    protected $table = 'conversion_review_issues';

    protected $fillable = [
        'issue_code',
        'severity',
        'message',
        'customer_id',
        'contract_id',
        'invoice_id',
        'journal_id',
        'party_name',
        'details',
        'status',
        'manager_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public static function isEnabledForCurrentCompany(): bool
    {
        return Auth::user()?->company === 'Bentaher3';
    }

    public static function canAccessScreen(): bool
    {
        return static::isEnabledForCurrentCompany() && (bool) Auth::user()?->is_prog;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(InstallmentContract::class, 'contract_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public static function codeLabel(string $code): string
    {
        return match ($code) {
            'installment_plan_mismatch' => 'عدم تطابق مبلغ×عدد مع الفواتير',
            'installment_illogical_schedule' => 'جدول أقساط غير منطقي',
            'installment_overpay' => 'سداد أكبر من قيمة العقد',
            'installment_no_invoice' => 'زبون أقساط بلا فاتورة وبلا خطة',
            'installment_no_invoice_with_plan' => 'عقد بلا فاتورة بيع',
            'installment_no_plan' => 'اسم بلا نمط مبلغ*عدد',
            'installment_orphan_payment' => 'سداد بلا عقد',
            'return_unlinked' => 'مرتجع بيع بلا فاتورة أصل',
            'missing_warehouse' => 'مخزن محذوف في المصدر',
            'cash_box_as_customer' => 'خزينة مسجّلة كزبون',
            'supplier_from_purchase' => 'مورد من فاتورة شراء',
            'transfer_bad_warehouse' => 'نقل بمخزن غير صالح',
            'item_types' => 'تصنيف أصناف ضعيف',
            default => $code,
        };
    }

    public static function severityFor(string $code): string
    {
        return match ($code) {
            'installment_illogical_schedule',
            'installment_overpay',
            'installment_no_invoice',
            'return_unlinked' => 'error',
            'installment_plan_mismatch',
            'installment_no_invoice_with_plan',
            'installment_no_plan',
            'installment_orphan_payment' => 'warning',
            default => 'info',
        };
    }
}
