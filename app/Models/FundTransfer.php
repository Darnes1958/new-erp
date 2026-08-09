<?php

namespace App\Models;

use App\Enums\FundTransferKind;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransfer extends CompanyModel
{
    protected $fillable = [
        'transfer_date',
        'transfer_kind',
        'from_cash_box_id',
        'to_cash_box_id',
        'from_bank_account_id',
        'to_bank_account_id',
        'payment_method_id',
        'amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'transfer_kind' => FundTransferKind::class,
            'amount' => 'decimal:3',
        ];
    }

    public function fromCashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'from_cash_box_id');
    }

    public function toCashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'to_cash_box_id');
    }

    public function fromBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_bank_account_id');
    }

    public function toBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fromAccountName(): string
    {
        $kind = $this->transfer_kind;

        if ($kind->usesFromCashBox()) {
            return $this->fromCashBox?->name ?? '—';
        }

        return $this->fromBankAccount?->name ?? '—';
    }

    public function toAccountName(): string
    {
        $kind = $this->transfer_kind;

        if ($kind->usesToCashBox()) {
            return $this->toCashBox?->name ?? '—';
        }

        return $this->toBankAccount?->name ?? '—';
    }
}
