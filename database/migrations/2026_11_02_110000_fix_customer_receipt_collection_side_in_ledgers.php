<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use App\Database\Views\CustomerLedgerEntriesView;
use App\Database\Views\PaymentAccountLedgerEntriesView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (Schema::connection($connection)->hasTable('customer_receipts')) {
                DB::connection($connection)->update(
                    'UPDATE customer_receipts SET flow_direction = 0 WHERE transaction_kind IN (1, 3, 6) AND flow_direction <> 0'
                );
                DB::connection($connection)->update(
                    'UPDATE customer_receipts SET flow_direction = 1 WHERE transaction_kind IN (2, 4, 5) AND flow_direction <> 1'
                );
            }

            $this->rebuildCustomerLedger($connection);
            $this->rebuildPaymentAccountLedgers($connection);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->rebuildCustomerLedger($connection);
            $this->rebuildPaymentAccountLedgers($connection);
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
