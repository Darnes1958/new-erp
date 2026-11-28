<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use App\Database\Views\PaymentAccountLedgerEntriesView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->rebuildPaymentAccountLedgers($connection);
        });
    }

    public function down(): void
    {
        $this->up();
    }

    protected function rebuildPaymentAccountLedgers(string $connection): void
    {
        if (! $this->companyHasTables($connection, [
            'customer_receipts',
            'supplier_payments',
            'fund_transfers',
            'cash_boxes',
            'bank_accounts',
        ])) {
            return;
        }

        $views = new PaymentAccountLedgerEntriesView;

        DB::connection($connection)->statement('IF OBJECT_ID(N\'cash_box_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW cash_box_ledger_entries');
        DB::connection($connection)->statement('IF OBJECT_ID(N\'bank_account_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW bank_account_ledger_entries');
        DB::connection($connection)->statement($views->cashBoxSql());
        DB::connection($connection)->statement($views->bankAccountSql($connection));
    }
};
