<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use App\Database\Views\CustomerLedgerEntriesView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->ensureIndexes($connection);
            $this->rebuildCustomerLedger($connection);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->rebuildCustomerLedger($connection);
        });
    }

    protected function ensureIndexes(string $connection): void
    {
        if (Schema::connection($connection)->hasTable('installment_contract_archives')) {
            $this->ensureIndex($connection, 'installment_contract_archives', 'ica_customer_id_idx', ['customer_id']);
        }

        if (Schema::connection($connection)->hasTable('installment_contracts')) {
            $this->ensureIndex($connection, 'installment_contracts', 'ic_customer_id_idx', ['customer_id']);
        }

        if (Schema::connection($connection)->hasTable('installment_deduction_archives')) {
            $this->ensureIndex(
                $connection,
                'installment_deduction_archives',
                'ida_contract_date_idx',
                ['installment_contract_id', 'deduction_date'],
            );
        }

        if (Schema::connection($connection)->hasTable('installment_deductions')) {
            $this->ensureIndex(
                $connection,
                'installment_deductions',
                'id_contract_date_idx',
                ['installment_contract_id', 'deduction_date'],
            );
        }
    }

    /**
     * @param  list<string>  $columns
     */
    protected function ensureIndex(string $connection, string $table, string $indexName, array $columns): void
    {
        $exists = DB::connection($connection)->selectOne(
            <<<'SQL'
SELECT 1 AS ok
FROM sys.indexes
WHERE object_id = OBJECT_ID(?)
  AND name = ?
SQL,
            ['dbo.'.$table, $indexName],
        );

        if ($exists) {
            return;
        }

        try {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
                $blueprint->index($columns, $indexName);
            });
        } catch (\Throwable) {
            // Index may already exist under another name / race.
        }
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

        DB::connection($connection)->statement(
            "IF OBJECT_ID(N'customer_ledger_entries', N'V') IS NOT NULL DROP VIEW customer_ledger_entries"
        );
        DB::connection($connection)->statement(
            (new CustomerLedgerEntriesView)->createSql($connection, true)
        );
    }
};
