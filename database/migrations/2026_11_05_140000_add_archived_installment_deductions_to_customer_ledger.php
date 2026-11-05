<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use App\Database\Views\CustomerLedgerEntriesView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->rebuildCustomerLedger($connection);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->rebuildCustomerLedger($connection);
        });
    }

    protected function rebuildCustomerLedger(string $connection): void
    {
        if (! $this->companyHasTables($connection, [
            'sales_invoices',
            'customer_receipts',
            'customers',
            'payment_methods',
            'sales_returns',
        ])) {
            return;
        }

        DB::connection($connection)->statement('IF OBJECT_ID(N\'customer_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW customer_ledger_entries');
        DB::connection($connection)->statement(
            (new CustomerLedgerEntriesView)->createSql($connection, true)
        );
    }
};
