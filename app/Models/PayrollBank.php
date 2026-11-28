<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class PayrollBank extends CompanyModel
{
    protected $fillable = [
        'name',
        'account_number',
        'bank_main_id',
        'bank_account_id',
        'bank_ledger_from',
    ];

    protected function casts(): array
    {
        return [
            'bank_ledger_from' => 'date',
        ];
    }

    public function bankMain(): BelongsTo
    {
        return $this->belongsTo(BankMain::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public static function isLinkedToBankAccount(int $bankAccountId): bool
    {
        $model = new static;
        $connection = $model->getConnectionName();

        if (! Schema::connection($connection)->hasTable($model->getTable())) {
            return false;
        }

        if (! Schema::connection($connection)->hasColumn($model->getTable(), 'bank_account_id')) {
            return false;
        }

        return static::query()->where('bank_account_id', $bankAccountId)->exists();
    }

    public function installmentBanks(): HasMany
    {
        return $this->hasMany(InstallmentBank::class);
    }

    public function installmentContracts(): HasMany
    {
        return $this->hasMany(InstallmentContract::class);
    }
}
