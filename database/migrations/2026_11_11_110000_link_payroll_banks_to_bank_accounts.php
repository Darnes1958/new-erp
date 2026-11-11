<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use App\Database\Views\PaymentAccountLedgerEntriesView;
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
            if (! Schema::connection($connection)->hasTable('payroll_banks')) {
                return;
            }

            if (! Schema::connection($connection)->hasColumn('payroll_banks', 'bank_account_id')) {
                Schema::connection($connection)->table('payroll_banks', function (Blueprint $table): void {
                    $table->unsignedBigInteger('bank_account_id')->nullable()->after('bank_main_id');
                    $table->date('bank_ledger_from')->nullable()->after('bank_account_id');
                });
            } elseif (! Schema::connection($connection)->hasColumn('payroll_banks', 'bank_ledger_from')) {
                Schema::connection($connection)->table('payroll_banks', function (Blueprint $table): void {
                    $table->date('bank_ledger_from')->nullable()->after('bank_account_id');
                });
            }

            if (
                Schema::connection($connection)->hasTable('bank_accounts')
                && Schema::connection($connection)->hasColumn('payroll_banks', 'bank_account_id')
                && ! $this->foreignKeyExists($connection, 'payroll_banks', 'bank_account_id')
            ) {
                Schema::connection($connection)->table('payroll_banks', function (Blueprint $table): void {
                    $table->foreign('bank_account_id')
                        ->references('id')
                        ->on('bank_accounts')
                        ->noActionOnDelete();
                });
            }

            $this->rebuildBankAccountLedger($connection);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (
                Schema::connection($connection)->hasTable('payroll_banks')
                && Schema::connection($connection)->hasColumn('payroll_banks', 'bank_account_id')
                && $this->foreignKeyExists($connection, 'payroll_banks', 'bank_account_id')
            ) {
                Schema::connection($connection)->table('payroll_banks', function (Blueprint $table): void {
                    $table->dropForeign(['bank_account_id']);
                });
            }

            if (Schema::connection($connection)->hasTable('payroll_banks')) {
                Schema::connection($connection)->table('payroll_banks', function (Blueprint $table) use ($connection): void {
                    $drop = [];

                    if (Schema::connection($connection)->hasColumn('payroll_banks', 'bank_ledger_from')) {
                        $drop[] = 'bank_ledger_from';
                    }

                    if (Schema::connection($connection)->hasColumn('payroll_banks', 'bank_account_id')) {
                        $drop[] = 'bank_account_id';
                    }

                    if ($drop !== []) {
                        $table->dropColumn($drop);
                    }
                });
            }

            $this->rebuildBankAccountLedger($connection);
        });
    }

    protected function rebuildBankAccountLedger(string $connection): void
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

        DB::connection($connection)->statement('IF OBJECT_ID(N\'bank_account_ledger_entries\', N\'V\') IS NOT NULL DROP VIEW bank_account_ledger_entries');
        DB::connection($connection)->statement($views->bankAccountSql($connection));
    }

    protected function foreignKeyExists(string $connection, string $table, string $column): bool
    {
        $result = DB::connection($connection)->selectOne(
            'SELECT 1 AS found
             FROM sys.foreign_keys fk
             INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
             INNER JOIN sys.columns c ON fkc.parent_column_id = c.column_id AND fkc.parent_object_id = c.object_id
             INNER JOIN sys.tables t ON fk.parent_object_id = t.object_id
             WHERE t.name = ? AND c.name = ?',
            [$table, $column],
        );

        return $result !== null;
    }
};
