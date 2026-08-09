<?php

namespace App\Models;

class CashBoxLedgerEntry extends CompanyModel
{
    public $incrementing = false;

    protected $table = 'cash_box_ledger_entries';

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
