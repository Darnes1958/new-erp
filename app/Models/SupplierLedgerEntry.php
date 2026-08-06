<?php

namespace App\Models;

class SupplierLedgerEntry extends CompanyModel
{
    public $incrementing = false;

    protected $table = 'supplier_ledger_entries';

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
