<?php

namespace App\Services\Company;

use App\Models\CompanySetting;
use App\Models\OurCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Seeds legacy-style default rows for a new empty ERP company database.
 */
class CompanyDefaultsSeeder
{
    /** @var list<array{table: string, id: int, label: string}> */
    protected array $seeded = [];

    /** @var list<array{table: string, id: int, label: string}> */
    protected array $skipped = [];

    public function seed(string $connection, bool $force = false): void
    {
        $this->seeded = [];
        $this->skipped = [];

        if (! config("database.connections.{$connection}")) {
            throw new RuntimeException("Database connection [{$connection}] is not configured.");
        }

        if (! Schema::connection($connection)->hasTable('payment_methods')) {
            throw new RuntimeException(
                "Company schema not found on [{$connection}]. Run erp:migrate-company {$connection} --fresh first."
            );
        }

        $now = now()->format('Y-m-d H:i:s.v');

        $this->seedPaymentMethods($connection, $now, $force);
        $this->seedIdentityRow($connection, 'units', [
            'id' => 1,
            'name' => 'قطعة',
            'abbreviation' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'brands', [
            'id' => 1,
            'name' => 'عام',
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'customer_types', [
            'id' => 1,
            'name' => 'عام',
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'customers', [
            'id' => 1,
            'name' => 'مبيعات عامة',
            'address' => null,
            'mdar' => null,
            'libyana' => null,
            'card_no' => null,
            'others' => null,
            'customer_type_id' => 1,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'suppliers', [
            'id' => 1,
            'name' => 'مشتريات عامة',
            'address' => null,
            'mdar' => null,
            'libyana' => null,
            'card_no' => null,
            'others' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'warehouses', [
            'id' => 1,
            'name' => 'المخزن',
            'warehouse_type' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'warehouses', [
            'id' => 10001,
            'name' => 'الصالة',
            'warehouse_type' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'cash_boxes', [
            'id' => 1,
            'name' => 'الخزينة الرئيسة',
            'opening_balance' => 0,
            'assigned_user_id' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
        $this->seedIdentityRow($connection, 'bank_accounts', [
            'id' => 1,
            'name' => 'الحساب المصرفي الرئيسي',
            'account_number' => null,
            'opening_balance' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $force);
    }

    public function registerCentral(string $connection, ?string $displayName = null): void
    {
        $displayName ??= $this->defaultDisplayName($connection);

        OurCompany::query()->updateOrCreate(
            ['connection_name' => $connection],
            [
                'display_name' => $displayName,
                'display_name_suffix' => null,
                'is_active' => true,
            ],
        );

        CompanySetting::query()->updateOrCreate(
            ['company' => $connection],
            [
                'has_expiry_dates' => false,
                'has_dual_unit' => false,
                'multi_warehouse' => false,
                'wholesale_retail' => false,
                'barcode_enabled' => false,
                'link_sales_to_installments' => false,
                'installment_by_payroll_bank' => true,
                'auto_price_update' => false,
            ],
        );
    }

    /**
     * @return list<array{table: string, id: int, label: string}>
     */
    public function seededRows(): array
    {
        return $this->seeded;
    }

    /**
     * @return list<array{table: string, id: int, label: string}>
     */
    public function skippedRows(): array
    {
        return $this->skipped;
    }

    protected function seedPaymentMethods(string $connection, string $now, bool $force): void
    {
        $methods = [
            ['id' => 1, 'name' => 'نقدي', 'code' => 'cash'],
            ['id' => 2, 'name' => 'مصرفي', 'code' => 'bank'],
            ['id' => 3, 'name' => 'تقسيط', 'code' => 'installment'],
        ];

        DB::connection($connection)->transaction(function () use ($connection, $methods, $now, $force): void {
            DB::connection($connection)->unprepared('SET IDENTITY_INSERT payment_methods ON');

            foreach ($methods as $method) {
                $exists = DB::connection($connection)
                    ->table('payment_methods')
                    ->where('id', $method['id'])
                    ->exists();

                if ($exists && ! $force) {
                    $this->skipped[] = [
                        'table' => 'payment_methods',
                        'id' => $method['id'],
                        'label' => $method['name'],
                    ];

                    continue;
                }

                $payload = [
                    'name' => $method['name'],
                    'code' => $method['code'],
                    'rate' => 0,
                    'adjustment_value' => 0,
                    'adjustment_direction' => 0,
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if ($exists) {
                    DB::connection($connection)
                        ->table('payment_methods')
                        ->where('id', $method['id'])
                        ->update($payload);
                } else {
                    DB::connection($connection)
                        ->table('payment_methods')
                        ->insert([
                            'id' => $method['id'],
                            ...$payload,
                            'created_at' => $now,
                        ]);
                }

                $this->seeded[] = [
                    'table' => 'payment_methods',
                    'id' => $method['id'],
                    'label' => $method['name'],
                ];
            }

            DB::connection($connection)->unprepared('SET IDENTITY_INSERT payment_methods OFF');
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function seedIdentityRow(string $connection, string $table, array $row, bool $force): void
    {
        $id = (int) $row['id'];
        $label = (string) ($row['name'] ?? $id);

        $exists = DB::connection($connection)->table($table)->where('id', $id)->exists();

        if ($exists && ! $force) {
            $this->skipped[] = ['table' => $table, 'id' => $id, 'label' => $label];

            return;
        }

        DB::connection($connection)->transaction(function () use ($connection, $table, $row, $exists, $id, $label): void {
            DB::connection($connection)->unprepared("SET IDENTITY_INSERT [{$table}] ON");

            if ($exists) {
                $update = $row;
                unset($update['id'], $update['created_at']);
                DB::connection($connection)->table($table)->where('id', $id)->update($update);
            } else {
                DB::connection($connection)->table($table)->insert($row);
            }

            DB::connection($connection)->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
        });

        $this->seeded[] = ['table' => $table, 'id' => $id, 'label' => $label];
    }

    protected function defaultDisplayName(string $connection): string
    {
        $suffix = (string) config('erp.conversion.target_name_suffix', '_erp');

        if ($suffix !== '' && str_ends_with($connection, $suffix)) {
            return substr($connection, 0, -strlen($suffix));
        }

        return $connection;
    }
}
