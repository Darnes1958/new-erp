<?php

namespace App\Models;

class BankAccountLedgerEntry extends CompanyModel
{
    public $incrementing = false;

    protected $table = 'bank_account_ledger_entries';

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
