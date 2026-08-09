<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesInvoice extends CompanyModel
{
    protected $fillable = [
        'invoice_date',
        'customer_id',
        'payment_method_id',
        'warehouse_id',
        'is_retail',
        'lines_subtotal',
        'extra_cost',
        'rate_markup',
        'difference_amount',
        'discount',
        'grand_total',
        'amount_paid',
        'balance',
        'deferred_amount',
        'refund_amount',
        'unpaid_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'is_retail' => 'boolean',
            'unpaid_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (SalesInvoice $invoice): void {
            $invoice->assertCanBeDeleted();
        });

        static::saved(function (SalesInvoice $invoice): void {
            if ($invoice->wasChanged(['amount_paid', 'extra_cost', 'discount', 'difference_amount'])) {
                $invoice->recalculateTotals();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(CustomerReceipt::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function installmentContract(): HasOne
    {
        return $this->hasOne(InstallmentContract::class);
    }

    public function installmentContractArchive(): HasOne
    {
        return $this->hasOne(InstallmentContractArchive::class);
    }

    public function installmentCancelledContract(): HasOne
    {
        return $this->hasOne(InstallmentCancelledContract::class);
    }

    public function hasInstallmentContract(): bool
    {
        return $this->installmentContract()->exists()
            || $this->installmentContractArchive()->exists()
            || $this->installmentCancelledContract()->exists();
    }

    public function hasLinkedReceipts(): bool
    {
        return $this->receipts()->exists();
    }

    public function hasLinkedReturns(): bool
    {
        return $this->salesReturns()->exists();
    }

    public function canBeDeleted(): bool
    {
        return $this->deletionBlockReason() === null;
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->hasInstallmentContract()) {
            return 'هذه الفاتورة مقيدة بعقد تقسيط .. لا يجوز الغاءها';
        }

        if ($this->hasLinkedReturns()) {
            return 'هذه الفاتورة مرتبطة بترجيعات .. لا يجوز الغاءها';
        }

        if ($this->hasLinkedReceipts()) {
            return 'هذه الفاتورة مرتبطة بإيصالات .. لا يجوز الغاءها';
        }

        return null;
    }

    public function assertCanBeDeleted(): void
    {
        $reason = $this->deletionBlockReason();

        if ($reason !== null) {
            throw new \RuntimeException($reason);
        }
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->lines()->sum('line_total');
        $grandTotal = $subtotal
            + (float) $this->extra_cost
            - (float) $this->discount
            + (float) $this->difference_amount;
        $paid = (float) $this->amount_paid;

        $this->forceFill([
            'lines_subtotal' => $subtotal,
            'grand_total' => $grandTotal,
            'balance' => $grandTotal - $paid,
        ])->saveQuietly();
    }
}
