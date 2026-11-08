<?php

namespace App\Console\Commands;

use App\Support\CompanyConnections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErpNormalizePaymentMethodIdsCommand extends Command
{
    protected $signature = 'erp:normalize-payment-method-ids
        {connection : Company database connection (e.g. Anwar_erp)}
        {--dry-run : Show current rows and FK counts without changing data}
        {--swap-foreign-keys : Swap payment_method_id 2↔3 even if labels already look correct}
        {--force : Apply even if current names/codes are unclear}';

    protected $description = 'Keep payment_methods ids 2=صك/مصرفي and 3=تقسيط; swap labels and FK values instead of changing primary keys';

    /**
     * @var list<string>
     */
    protected array $foreignKeyTables = [
        'item_prices',
        'sales_invoices',
        'purchase_invoices',
        'customer_receipts',
        'supplier_payments',
        'fund_transfers',
        'sales_quotations',
        'sales_offer_invoices',
        'sales_invoice_work',
        'purchase_invoice_work',
        'sales_offer_invoice_work',
    ];

    public function handle(): int
    {
        $connection = (string) $this->argument('connection');

        if (! CompanyConnections::isValid($connection)) {
            $this->error("Invalid company connection [{$connection}]");

            return self::FAILURE;
        }

        if (! Schema::connection($connection)->hasTable('payment_methods')) {
            $this->error("[{$connection}] has no payment_methods table.");

            return self::FAILURE;
        }

        $two = DB::connection($connection)->table('payment_methods')->where('id', 2)->first();
        $three = DB::connection($connection)->table('payment_methods')->where('id', 3)->first();

        if (! $two || ! $three) {
            $this->error('Rows id=2 and id=3 must both exist in payment_methods.');

            return self::FAILURE;
        }

        $this->info("[{$connection}] payment_methods now:");
        $this->table(
            ['id', 'name', 'code'],
            [
                [(int) $two->id, $two->name, $two->code],
                [(int) $three->id, $three->name, $three->code],
            ],
        );

        $this->info('Foreign key counts (2 vs 3):');
        $this->table(['table', 'id=2', 'id=3'], $this->foreignKeyCounts($connection));

        $twoLooksInstallment = $this->looksLikeInstallment($two);
        $threeLooksInstallment = $this->looksLikeInstallment($three);
        $swapLabels = $twoLooksInstallment && ! $threeLooksInstallment;
        $swapForeignKeys = $swapLabels || (bool) $this->option('swap-foreign-keys');

        if (! $swapLabels && ! $this->option('swap-foreign-keys')) {
            $this->warn('id=2 does not look like تقسيط. Labels may already match ERP (2=صك، 3=تقسيط).');
            $this->warn('If invoices still store INS numbers, rerun with --swap-foreign-keys.');

            if ($this->option('dry-run') || ! $this->option('force')) {
                return self::SUCCESS;
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run. Would '.($swapLabels ? 'swap names/codes of 2 and 3' : 'leave labels').' and '.($swapForeignKeys ? 'swap FK 2↔3' : 'leave FKs').'.');

            return self::SUCCESS;
        }

        DB::connection($connection)->transaction(function () use ($connection, $two, $three, $swapLabels, $swapForeignKeys): void {
            if ($swapLabels) {
                $this->swapLabels($connection, $two, $three);
            }

            if ($swapForeignKeys) {
                $this->swapForeignKeys($connection);
            }
        });

        $this->info('Done. Recheck payment_methods and a تقسيط sales invoice: payment_method_id should be 3.');

        return self::SUCCESS;
    }

    protected function looksLikeInstallment(object $row): bool
    {
        $code = strtolower(trim((string) ($row->code ?? '')));
        $name = trim((string) ($row->name ?? ''));

        return $code === 'installment' || str_contains($name, 'تقسيط');
    }

    /**
     * @return list<array{0: string, 1: int, 2: int}>
     */
    protected function foreignKeyCounts(string $connection): array
    {
        $rows = [];

        foreach ($this->foreignKeyTables as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                continue;
            }

            $query = DB::connection($connection)->table($table);

            $rows[] = [
                $table,
                (int) (clone $query)->where('payment_method_id', 2)->count(),
                (int) (clone $query)->where('payment_method_id', 3)->count(),
            ];
        }

        return $rows;
    }

    protected function swapLabels(string $connection, object $two, object $three): void
    {
        DB::connection($connection)->table('payment_methods')->where('id', 2)->update([
            'name' => $three->name,
            'code' => 'bank',
            'rate' => $three->rate,
            'adjustment_value' => $three->adjustment_value,
            'adjustment_direction' => $three->adjustment_direction,
            'updated_at' => now(),
        ]);

        DB::connection($connection)->table('payment_methods')->where('id', 3)->update([
            'name' => $two->name,
            'code' => 'installment',
            'rate' => $two->rate,
            'adjustment_value' => $two->adjustment_value,
            'adjustment_direction' => $two->adjustment_direction,
            'updated_at' => now(),
        ]);

        $this->info('Swapped names/codes of payment_methods 2 and 3. Primary keys were not changed.');
    }

    protected function swapForeignKeys(string $connection): void
    {
        foreach ($this->foreignKeyTables as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                continue;
            }

            if ($table === 'item_prices') {
                $this->swapItemPrices($connection);

                continue;
            }

            $updated = DB::connection($connection)->update(
                "UPDATE [{$table}] SET payment_method_id = CASE payment_method_id WHEN 2 THEN 3 WHEN 3 THEN 2 ELSE payment_method_id END WHERE payment_method_id IN (2, 3)"
            );

            $this->line("  {$table}: {$updated} row(s)");
        }
    }

    protected function swapItemPrices(string $connection): void
    {
        $db = DB::connection($connection);
        $constraint = $this->itemPricesPaymentMethodForeignKey($connection);
        $updated = 0;

        if ($constraint) {
            $db->unprepared("ALTER TABLE item_prices NOCHECK CONSTRAINT [{$constraint}]");
        }

        try {
            $db->update('UPDATE item_prices SET payment_method_id = payment_method_id + 900 WHERE payment_method_id IN (2, 3)');
            $updated = $db->update(
                'UPDATE item_prices SET payment_method_id = CASE payment_method_id WHEN 902 THEN 3 WHEN 903 THEN 2 ELSE payment_method_id END WHERE payment_method_id IN (902, 903)'
            );
        } finally {
            if ($constraint) {
                $db->unprepared("ALTER TABLE item_prices WITH CHECK CHECK CONSTRAINT [{$constraint}]");
            }
        }

        $this->line("  item_prices: {$updated} row(s)");
    }

    protected function itemPricesPaymentMethodForeignKey(string $connection): ?string
    {
        $row = DB::connection($connection)->selectOne(
            "SELECT fk.name
             FROM sys.foreign_keys AS fk
             INNER JOIN sys.foreign_key_columns AS fkc ON fkc.constraint_object_id = fk.object_id
             INNER JOIN sys.columns AS c
                ON c.object_id = fkc.parent_object_id
               AND c.column_id = fkc.parent_column_id
             INNER JOIN sys.tables AS parent ON parent.object_id = fk.parent_object_id
             WHERE parent.name = 'item_prices'
               AND c.name = 'payment_method_id'",
        );

        $name = $row->name ?? null;

        return filled($name) ? (string) $name : 'item_prices_payment_method_id_foreign';
    }
}
