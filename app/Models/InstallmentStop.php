<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentStop extends CompanyModel
{
    protected $table = 'installment_stops';

    protected $fillable = [
        'installment_contract_id',
        'stop_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stop_date' => 'date',
        ];
    }

    public function installmentContract(): BelongsTo
    {
        return $this->belongsTo(InstallmentContract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
