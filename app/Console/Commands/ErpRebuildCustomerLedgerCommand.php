<?php

namespace App\Console\Commands;

use App\Database\Views\CustomerLedgerEntriesView;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErpRebuildCustomerLedgerCommand extends Command
{
    protected $signature = 'erp:rebuild-customer-ledger
        {connection? : Company connection name (default: all erp.company_connections)}';

    protected $description = 'Rebuild customer_ledger_entries view (includes archived installment deductions)';

    public function handle(): int
    {
        $requested = $this->argument('connection');
        $connections = filled($requested)
            ? [(string) $requested]
            : config('erp.company_connections', []);

        if ($connections === []) {
            $this->error('No company connections configured.');

            return self::FAILURE;
        }

        foreach ($connections as $connection) {
            if (! config("database.connections.{$connection}")) {
                $this->warn("Skipped [{$connection}]: connection not configured.");

                continue;
            }

            try {
                DB::connection($connection)->getPdo();
            } catch (\Throwable $e) {
                $this->warn("Skipped [{$connection}]: ".$e->getMessage());

                continue;
            }

            try {
                foreach (['sales_invoices', 'customer_receipts', 'customers', 'payment_methods', 'sales_returns'] as $table) {
                    if (! Schema::connection($connection)->hasTable($table)) {
                        $this->warn("Skipped [{$connection}]: missing table {$table}.");

                        continue 2;
                    }
                }

                $hasArchives = Schema::connection($connection)->hasTable('installment_deduction_archives')
                    && Schema::connection($connection)->hasTable('installment_contract_archives');

                DB::connection($connection)->statement(
                    "IF OBJECT_ID(N'customer_ledger_entries', N'V') IS NOT NULL DROP VIEW customer_ledger_entries"
                );
                DB::connection($connection)->statement(
                    (new CustomerLedgerEntriesView)->createSql($connection, true)
                );

                $flag = $hasArchives ? 'with archives' : 'without archives tables';
                $this->info("Rebuilt customer_ledger_entries on [{$connection}] ({$flag}).");
            } catch (\Throwable $e) {
                $this->error("Failed [{$connection}]: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
