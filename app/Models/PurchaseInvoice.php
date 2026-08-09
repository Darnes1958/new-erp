<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseInvoice extends CompanyModel
{
    protected $fillable = [
        'invoice_date',
        'supplier_id',
        'payment_method_id',
        'warehouse_id',
        'lines_subtotal',
        'discount',
        'amount_paid',
        'balance',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PurchaseInvoice $invoice): void {
            $invoice->assertCanBeDeleted();
        });

        static::saved(function (PurchaseInvoice $invoice): void {
            if ($invoice->wasChanged(['amount_paid', 'discount'])) {
                $invoice->recalculateTotals();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(PurchaseInvoiceLine::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function destinationWarehouseTransfer(): HasOne
    {
        return $this->hasOne(WarehouseTransfer::class, 'destination_purchase_invoice_id');
    }

    public function canBeDeleted(): bool
    {
        return $this->deletionBlockReason() === null;
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->purchaseReturns()->exists()) {
            return 'هذه الفاتورة مرتبطة بترجيعات .. لا يجوز الغاءها';
        }

        if ($this->supplierPayments()->exists()) {
            return 'هذه الفاتورة مرتبطة بإيصالات موردين .. لا يجوز الغاءها';
        }

        if ($this->destinationWarehouseTransfer()->exists()) {
            return 'هذه الفاتورة مرتبطة بتحويل مخزن .. لا يجوز الغاءها';
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

    public static function signedPaymentAmount(SupplierPayment $payment, ?float $amountOverride = null): float
    {
        $amount = $amountOverride ?? (float) $payment->amount;

        return (int) $payment->flow_direction === 1 ? $amount : -$amount;
    }

    public static function totalPaymentsForInvoice(int $invoiceId, ?float $replaceKind5Amount = null): float
    {
        return (float) SupplierPayment::query()
            ->where('purchase_invoice_id', $invoiceId)
            ->get()
            ->sum(function (SupplierPayment $payment) use ($replaceKind5Amount): float {
                $amount = (float) $payment->amount;

                if ($replaceKind5Amount !== null && (int) $payment->transaction_kind === 5) {
                    $amount = $replaceKind5Amount;
                }

                return static::signedPaymentAmount($payment, $amount);
            });
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->lines()->sum('line_cost_total');

        $this->forceFill([
            'lines_subtotal' => $subtotal,
            'balance' => $subtotal - (float) $this->discount - static::totalPaymentsForInvoice((int) $this->id),
        ])->saveQuietly();
    }
}
