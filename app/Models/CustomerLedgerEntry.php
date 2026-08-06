<?php

namespace App\Models;

class CustomerLedgerEntry extends CompanyModel
{
    public $incrementing = false;

    protected $table = 'customer_ledger_entries';

    protected $primaryKey = 'idd';

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'rep_date' => 'date',
            'transaction_kind' => 'integer',
        ];
    }
}
