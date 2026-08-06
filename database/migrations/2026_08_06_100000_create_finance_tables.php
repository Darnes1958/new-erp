<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
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
            $this->ensureFinancePrerequisites($connection);

            if (! Schema::connection($connection)->hasTable('expense_types')) {
                Schema::connection($connection)->create('expense_types', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('expenses')) {
                Schema::connection($connection)->create('expenses', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('expense_type_id');
                    $table->unsignedTinyInteger('payment_method')->default(1);
                    $table->unsignedBigInteger('bank_account_id')->nullable();
                    $table->unsignedBigInteger('cash_box_id')->nullable();
                    $table->unsignedBigInteger('warehouse_id')->nullable();
                    $table->date('expense_date');
                    $table->decimal('amount', 14, 3);
                    $table->string('notes')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->index(['expense_date', 'warehouse_id']);
                });
            }

            $this->ensureForeignKey($connection, 'expenses', 'expense_type_id', 'expense_types');
            $this->ensureForeignKey($connection, 'expenses', 'bank_account_id', 'bank_accounts');
            $this->ensureForeignKey($connection, 'expenses', 'cash_box_id', 'cash_boxes');
            $this->ensureForeignKey($connection, 'expenses', 'warehouse_id', 'warehouses');

            if (! Schema::connection($connection)->hasTable('salary_profiles')) {
                Schema::connection($connection)->create('salary_profiles', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->decimal('salary_amount', 14, 3)->default(0);
                    $table->unsignedBigInteger('warehouse_id')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->decimal('balance', 14, 3)->default(0);
                    $table->timestamps();
                });
            }

            $this->ensureForeignKey($connection, 'salary_profiles', 'warehouse_id', 'warehouses');

            if (! Schema::connection($connection)->hasTable('salary_transactions')) {
                Schema::connection($connection)->create('salary_transactions', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('salary_profile_id');
                    $table->date('transaction_date');
                    $table->string('transaction_type', 32);
                    $table->decimal('amount', 14, 3);
                    $table->string('period_month', 7)->default('0');
                    $table->unsignedBigInteger('bank_account_id')->nullable();
                    $table->unsignedBigInteger('cash_box_id')->nullable();
                    $table->string('notes')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->index(['salary_profile_id', 'period_month']);
                });
            }

            $this->ensureForeignKey($connection, 'salary_transactions', 'salary_profile_id', 'salary_profiles');
            $this->ensureForeignKey($connection, 'salary_transactions', 'bank_account_id', 'bank_accounts');
            $this->ensureForeignKey($connection, 'salary_transactions', 'cash_box_id', 'cash_boxes');

            if (! Schema::connection($connection)->hasTable('rent_profiles')) {
                Schema::connection($connection)->create('rent_profiles', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->decimal('rent_amount', 14, 3)->default(0);
                    $table->unsignedBigInteger('warehouse_id')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->decimal('balance', 14, 3)->default(0);
                    $table->timestamps();
                });
            }

            $this->ensureForeignKey($connection, 'rent_profiles', 'warehouse_id', 'warehouses');

            if (! Schema::connection($connection)->hasTable('rent_transactions')) {
                Schema::connection($connection)->create('rent_transactions', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('rent_profile_id');
                    $table->date('transaction_date');
                    $table->string('transaction_type', 32);
                    $table->decimal('amount', 14, 3);
                    $table->string('period_month', 7)->default('0');
                    $table->unsignedBigInteger('bank_account_id')->nullable();
                    $table->unsignedBigInteger('cash_box_id')->nullable();
                    $table->string('notes')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->index(['rent_profile_id', 'period_month']);
                });
            }

            $this->ensureForeignKey($connection, 'rent_transactions', 'rent_profile_id', 'rent_profiles');
            $this->ensureForeignKey($connection, 'rent_transactions', 'bank_account_id', 'bank_accounts');
            $this->ensureForeignKey($connection, 'rent_transactions', 'cash_box_id', 'cash_boxes');
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('rent_transactions');
            Schema::connection($connection)->dropIfExists('rent_profiles');
            Schema::connection($connection)->dropIfExists('salary_transactions');
            Schema::connection($connection)->dropIfExists('salary_profiles');
            Schema::connection($connection)->dropIfExists('expenses');
            Schema::connection($connection)->dropIfExists('expense_types');
        });
    }

    protected function ensureFinancePrerequisites(string $connection): void
    {
        if (! Schema::connection($connection)->hasTable('warehouses')) {
            Schema::connection($connection)->create('warehouses', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedTinyInteger('warehouse_type')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::connection($connection)->hasTable('cash_boxes')) {
            Schema::connection($connection)->create('cash_boxes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->decimal('opening_balance', 14, 3)->default(0);
                $table->unsignedBigInteger('assigned_user_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::connection($connection)->hasTable('bank_accounts')) {
            Schema::connection($connection)->create('bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('account_number')->nullable();
                $table->decimal('opening_balance', 14, 3)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $this->backfillLegacyMasterData($connection);
    }

    protected function backfillLegacyMasterData(string $connection): void
    {
        if (
            Schema::connection($connection)->hasTable('places')
            && DB::connection($connection)->table('warehouses')->count() === 0
        ) {
            $this->insertWithIdentity($connection, 'warehouses', DB::connection($connection)
                ->table('places')
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'warehouse_type' => (int) ($row->place_type ?? 1),
                    'is_active' => true,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ])
                ->all());
        }

        if (
            Schema::connection($connection)->hasTable('kazenas')
            && DB::connection($connection)->table('cash_boxes')->count() === 0
        ) {
            $this->insertWithIdentity($connection, 'cash_boxes', DB::connection($connection)
                ->table('kazenas')
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'opening_balance' => $row->balance ?? 0,
                    'assigned_user_id' => $row->user_id,
                    'is_active' => true,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ])
                ->all());
        }

        if (
            Schema::connection($connection)->hasTable('accs')
            && DB::connection($connection)->table('bank_accounts')->count() === 0
        ) {
            $this->insertWithIdentity($connection, 'bank_accounts', DB::connection($connection)
                ->table('accs')
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'account_number' => $row->acc,
                    'opening_balance' => $row->raseed ?? 0,
                    'is_active' => true,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ])
                ->all());
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function insertWithIdentity(string $connection, string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::connection($connection)->transaction(function () use ($connection, $table, $chunk): void {
                DB::connection($connection)->unprepared("SET IDENTITY_INSERT [{$table}] ON");

                foreach ($chunk as $row) {
                    DB::connection($connection)->table($table)->insert($row);
                }

                DB::connection($connection)->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            });
        }
    }

    protected function ensureForeignKey(
        string $connection,
        string $table,
        string $column,
        string $referencedTable,
    ): void {
        if (
            ! Schema::connection($connection)->hasTable($table)
            || ! Schema::connection($connection)->hasTable($referencedTable)
            || $this->foreignKeyExists($connection, $table, $column)
        ) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table) use ($column, $referencedTable): void {
            $table->foreign($column)->references('id')->on($referencedTable)->noActionOnDelete();
        });
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
