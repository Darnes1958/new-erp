<?php

namespace App\Services\Conversion;

use App\Services\Conversion\Concerns\ConvertsSqlServerCompanyData;
use App\Services\Conversion\Concerns\MapsLegacyEmpToUserId;
use App\Support\Conversion\LegacyConnectionNaming;
use App\Support\Conversion\LegacySchemaDetector;
use App\Support\Conversion\LegacySchemaKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Converts legacy INS company databases (main.no, kst_trans.no, sells.order_no, jeha.jeha_no, …)
 * into the new ERP schema.
 *
 * INS storage mapping:
 * - place           → workplaces (job locations, NOT warehouses)
 * - stores_names    → warehouses (storage, warehouse_type=0)
 * - halls_names     → warehouses (showrooms, warehouse_type=1, id = 10000 + hall_no)
 * - sells.sell_type → 1=store (place_no=st_no), 2=hall (place_no=hall_no)
 * - jeha            → customers (jeha_type <> 2) / suppliers (jeha_type = 2)
 * FromExcel, FromExcel2, Mahjoza, Kaema, *_work, *_view (except where needed), Operations, …
 */
class InsCompanyDataConverter
{
    use ConvertsSqlServerCompanyData;
    use MapsLegacyEmpToUserId;

    protected string $source;

    protected string $target;

    /** @var array<string, callable> */
    protected array $steps = [];

    public function __construct(string $legacy, ?string $target = null)
    {
        $this->source = LegacyConnectionNaming::legacyName($legacy);
        $this->target = LegacyConnectionNaming::legacyName($target ?? LegacyConnectionNaming::targetName($legacy));

        $this->steps = [
            'inspect' => fn () => $this->inspectSchema(),
            'register_company' => fn () => $this->registerCompany(),
            'company_settings' => fn () => $this->convertCompanySettings(),
            'payment_methods' => fn () => $this->convertPaymentMethods(),
            'master' => fn () => $this->convertMasterData(),
            'items' => fn () => $this->convertItems(),
            'sales' => fn () => $this->convertSales(),
            'installments' => fn () => $this->convertInstallments(),
            'users' => fn () => $this->convertUsers(),
            'created_by' => fn () => $this->backfillCreatedBy(),
        ];
    }

    public function legacyConnection(): string
    {
        return $this->source;
    }

    public function targetConnection(): string
    {
        return $this->target;
    }

    public function availableSteps(): array
    {
        return array_keys($this->steps);
    }

    public function convert(bool $fresh = false, ?array $only = null): void
    {
        $this->assertConnections($only);

        if ($fresh) {
            $this->clearTarget($only);
        }

        $steps = $only
            ? array_intersect_key($this->steps, array_flip($only))
            : $this->steps;

        foreach ($steps as $name => $step) {
            $this->log("Converting: {$name}");

            if ($name !== 'inspect') {
                $this->assertInsSource();
            }

            if (in_array($name, ['inspect', 'register_company', 'company_settings', 'users'], true)) {
                $step();
            } else {
                DB::connection($this->target)->transaction($step);
            }
            $this->log("Done: {$name}");
        }
    }

    /**
     * @param  array<string>|null  $only
     */
    protected function assertConnections(?array $only = null): void
    {
        $targetSteps = ['payment_methods', 'master', 'items', 'sales', 'installments'];
        $needsTarget = $only === null
            || count(array_intersect($only, $targetSteps)) > 0;

        $connections = [$this->source, $this->insCentralConnection()];

        if ($needsTarget) {
            $connections[] = $this->target;
        }

        foreach (array_unique($connections) as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function assertInsSource(): void
    {
        $kind = LegacySchemaDetector::detect($this->source);

        if ($kind !== LegacySchemaKind::Ins) {
            throw new RuntimeException(
                "Source [{$this->source}] is not an INS database (expected table [main]). Detected: {$kind->label()}."
            );
        }
    }

    /**
     * @param  array<string>|null  $only
     */
    protected function clearTarget(?array $only): void
    {
        $allGroups = [
            'register_company' => [],
            'company_settings' => [],
            'payment_methods' => ['payment_methods'],
            'master' => [
                'installment_banks', 'payroll_banks', 'workplaces',
                'customers', 'suppliers', 'customer_types', 'warehouses',
            ],
            'items' => ['item_barcodes', 'item_prices', 'items', 'brands', 'item_types', 'units'],
            'sales' => ['sales_invoice_lines', 'sales_returns', 'sales_invoices'],
            'installments' => [
                'installment_deduction_archives', 'installment_deductions',
                'installment_contract_archives', 'installment_contracts',
            ],
        ];

        $groups = $only ? array_intersect_key($allGroups, array_flip($only)) : $allGroups;
        $tables = [];

        foreach ($groups as $groupTables) {
            $tables = array_merge($tables, $groupTables);
        }

        $tables = array_values(array_unique($tables));

        if ($tables === []) {
            return;
        }

        $this->log('Clearing target tables: '.implode(', ', $tables));

        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"');

        foreach ($tables as $table) {
            if (Schema::connection($this->target)->hasTable($table)) {
                DB::connection($this->target)->table($table)->delete();
            }
        }

        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL"');
    }

    protected function inspectSchema(): void
    {
        $this->assertInsSource();

        $kind = LegacySchemaDetector::detect($this->source);
        $naming = LegacyConnectionNaming::describe($this->source);
        $central = $this->insCentralConnection();

        $this->log("Legacy connection : {$naming['legacy']}");
        $this->log("Target connection : {$naming['target']} (mode={$naming['mode']})");
        $this->log("INS central DB    : {$central}");
        $this->log("Schema kind       : {$kind->label()}");

        $registry = $this->findLegacyCompanyRegistryRow($this->source);

        if ($registry) {
            $this->log("Customers row     : {$registry->CompanyName}".(filled($registry->CompanyNameSuffix ?? null) ? " / {$registry->CompanyNameSuffix}" : ''));
        } else {
            $this->log("Customers row     : (not found for Company={$this->source})");
        }

        foreach (LegacySchemaDetector::tableCounts($this->source, $kind) as $table => $count) {
            $this->log(sprintf('  %-12s %s', $table.':', $count === null ? '(missing)' : (string) $count));
        }
    }

    protected function registerCompany(): void
    {
        $legacyName = $this->source;
        $targetName = $this->target;
        $row = $this->findLegacyCompanyRegistryRow($legacyName);

        if (! $row) {
            $this->log("No Customers row found for [{$legacyName}] on [{$this->insCentralConnection()}] — using connection name as display name.");

            DB::connection($this->centralConnection())->table('our_companies')->updateOrInsert(
                ['connection_name' => $targetName],
                [
                    'display_name' => $legacyName,
                    'display_name_suffix' => null,
                    'comp_code' => null,
                    'address' => null,
                    'phone' => null,
                    'is_active' => true,
                ],
            );

            return;
        }

        DB::connection($this->centralConnection())->table('our_companies')->updateOrInsert(
            ['connection_name' => $targetName],
            [
                'display_name' => $this->stringOrNull($row->CompanyName ?? null) ?? $legacyName,
                'display_name_suffix' => $this->stringOrNull($row->CompanyNameSuffix ?? null),
                'comp_code' => $this->stringOrNull($row->CompCode ?? null),
                'address' => null,
                'phone' => null,
                'is_active' => true,
            ],
        );

        $this->log("Registered our_companies [{$targetName}] from legacy [{$legacyName}]");
    }

    protected function convertCompanySettings(): void
    {
        $central = $this->insCentralConnection();

        if (! config("database.connections.{$central}")) {
            throw new RuntimeException("INS central connection [{$central}] is not configured.");
        }

        $row = DB::connection($central)
            ->table('Customers')
            ->where('Company', $this->source)
            ->first();

        if (! $row) {
            $this->log("No Customers row found for [{$this->source}] on [{$central}] — skipping company_settings.");

            return;
        }

        DB::connection($this->centralConnection())->table('company_settings')->updateOrInsert(
            ['company' => $this->target],
            [
                'has_expiry_dates' => false,
                'has_dual_unit' => false,
                'multi_warehouse' => false,
                'wholesale_retail' => false,
                'barcode_enabled' => false,
                'link_sales_to_installments' => true,
                'installment_by_payroll_bank' => ! ((int) ($row->oneBank ?? 0) === 1),
                'auto_price_update' => false,
                'user_message' => null,
                'alert_message' => $this->stringOrNull($row->message ?? null),
            ],
        );

        $this->log("Company settings from INS Customers: {$this->source} -> {$this->target} (central={$central})");
    }

    protected function insCentralConnection(): string
    {
        return (string) config('erp.ins_central_connection', 'useradmin');
    }

    protected function convertPaymentMethods(): void
    {
        if (! $this->legacyHasTable('price_type')) {
            $this->log('Skipped payment_methods: table [price_type] not found.');

            return;
        }

        $legacyByType = $this->legacyTableOrdered('price_type', 'type_no')->keyBy(fn ($row) => (int) $row->type_no);

        $erpSlots = [
            1 => ['legacy_type' => 1, 'code' => 'cash'],
            2 => ['legacy_type' => 3, 'code' => 'bank'],        // INS صك → ERP bank
            3 => ['legacy_type' => 2, 'code' => 'installment'], // INS تقسيط → ERP installment
        ];

        $rows = [];

        foreach ($erpSlots as $erpId => $slot) {
            $legacy = $legacyByType->get($slot['legacy_type']);

            if ($legacy === null) {
                continue;
            }

            $rows[] = [
                'id' => $erpId,
                'name' => $this->stringOrNull($legacy->type_name ?? null) ?? "type_{$erpId}",
                'code' => $slot['code'],
                'rate' => $legacy->rate ?? 0,
                'adjustment_value' => $legacy->val ?? 0,
                'adjustment_direction' => (int) ($legacy->inc_dec ?? 0),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->insertWithIdentity('payment_methods', $rows);
        $this->log('Converted '.count($rows).' payment method(s).');
    }

    /**
     * INS price_type 2 = installment, 3 = check — ERP uses 3 = installment, 2 = bank.
     */
    protected function mapInsPaymentMethodId(mixed $insTypeNo): int
    {
        return match ((int) $insTypeNo) {
            2 => 3,
            3 => 2,
            default => (int) $insTypeNo,
        };
    }

    protected function convertMasterData(): void
    {
        if ($this->legacyHasTable('jeha_type')) {
            $this->insertWithIdentity('customer_types', $this->legacyTableOrdered('jeha_type', 'type_no')->map(fn ($row) => [
                'id' => (int) $row->type_no,
                'name' => $this->stringOrNull($row->type_name ?? null) ?? 'type_'.$row->type_no,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        if ($this->legacyHasTable('place')) {
            $this->insertWithIdentity('workplaces', $this->legacyTableOrdered('place', 'place_no')->map(fn ($row) => [
                'id' => (int) $row->place_no,
                'name' => $this->stringOrNull($row->place_name ?? null) ?? 'place_'.$row->place_no,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        $this->convertInsWarehouses();

        if ($this->legacyHasTable('BankTajmeehy')) {
            $this->insertWithIdentity('payroll_banks', $this->legacyTableOrdered('BankTajmeehy', 'TajNo')->map(fn ($row) => [
                'id' => (int) $row->TajNo,
                'name' => $this->stringOrNull($row->TajName ?? null) ?? 'taj_'.$row->TajNo,
                'account_number' => $this->stringOrNull($row->TajAcc ?? $row->acc ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        if ($this->legacyHasTable('bank')) {
            $this->insertWithIdentity('installment_banks', $this->legacyTableOrdered('bank', 'bank_no')->map(fn ($row) => [
                'id' => (int) $row->bank_no,
                'name' => $this->stringOrNull($row->bank_name ?? null) ?? 'bank_'.$row->bank_no,
                'payroll_bank_id' => filled($row->bank_tajmeeh ?? $row->taj_id ?? null)
                    ? (int) ($row->bank_tajmeeh ?? $row->taj_id)
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        if ($this->legacyHasTable('jeha')) {
            $customers = [];
            $suppliers = [];

            foreach ($this->legacyTableOrdered('jeha', 'jeha_no') as $row) {
                $payload = [
                    'id' => (int) $row->jeha_no,
                    'name' => $this->stringOrNull($row->jeha_name ?? null) ?? 'jeha_'.$row->jeha_no,
                    'address' => $this->stringOrNull($row->address ?? null),
                    'mdar' => $this->stringOrNull($row->mdar ?? null),
                    'libyana' => $this->stringOrNull($row->libyana ?? null),
                    'card_no' => $this->stringOrNull($row->card_no ?? null),
                    'others' => $this->stringOrNull($row->others ?? null),
                    'customer_type_id' => filled($row->jeha_type ?? null) ? (int) $row->jeha_type : null,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ((int) ($row->jeha_type ?? 1) === 2) {
                    unset($payload['customer_type_id']);
                    $suppliers[] = $payload;
                } else {
                    $customers[] = $payload;
                }
            }

            $this->insertWithIdentity('customers', $customers);
            $this->insertWithIdentity('suppliers', collect($suppliers)->map(function (array $row) {
                unset($row['customer_type_id']);

                return $row;
            })->all());

            $this->log('Converted '.count($customers).' customer(s) and '.count($suppliers).' supplier(s) from jeha.');
        }

        $this->convertDefaultFinancialAccounts();
    }

    protected function convertDefaultFinancialAccounts(): void
    {
        if (! DB::connection($this->target)->table('cash_boxes')->where('id', 1)->exists()) {
            $this->insertWithIdentity('cash_boxes', [[
                'id' => 1,
                'name' => 'الخزينة الرئيسة',
                'opening_balance' => 0,
                'assigned_user_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        }

        if (! DB::connection($this->target)->table('bank_accounts')->where('id', 1)->exists()) {
            $this->insertWithIdentity('bank_accounts', [[
                'id' => 1,
                'name' => 'الحساب المصرفي الرئيسي',
                'account_number' => null,
                'opening_balance' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        }

        $this->log('Ensured default cash box and bank account exist.');
    }

    protected function convertItems(): void
    {
        if (! DB::connection($this->target)->table('units')->where('id', 1)->exists()) {
            $this->insertWithIdentity('units', [[
                'id' => 1,
                'name' => 'قطعة',
                'abbreviation' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        }

        if ($this->legacyHasTable('item_type')) {
            $this->insertWithIdentity('item_types', $this->legacyTableOrdered('item_type', 'type_no')->map(fn ($row) => [
                'id' => (int) $row->type_no,
                'name' => $this->stringOrNull($row->type_name ?? null) ?? 'type_'.$row->type_no,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        if ($this->legacyHasTable('items')) {
            $this->insertWithIdentity('items', $this->legacyTableOrdered('items', 'item_no')->map(fn ($row) => [
                'id' => (int) $row->item_no,
                'name' => $this->stringOrNull($row->item_name ?? null) ?? 'item_'.$row->item_no,
                'barcode' => (string) $row->item_no,
                'item_type_id' => filled($row->item_type ?? null) ? (int) $row->item_type : null,
                'brand_id' => null,
                'primary_unit_id' => 1,
                'secondary_unit_id' => null,
                'has_dual_unit' => false,
                'conversion_factor' => 1,
                'default_buy_price' => $row->price_buy ?? 0,
                'is_active' => (bool) ((int) ($row->available ?? 1)),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            $this->insertWithIdentity('item_barcodes', $this->legacyTableOrdered('items', 'item_no')->map(fn ($row) => [
                'id' => (int) $row->item_no,
                'item_id' => (int) $row->item_no,
                'barcode' => (string) $row->item_no,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        $buyPrices = [];
        $sellPrices = [];

        if ($this->legacyHasTable('item_price_buy')) {
            $buyPrices = $this->legacyTableOrdered('item_price_buy', 'rec_no')->map(fn ($row) => [
                'item_id' => (int) $row->item_no,
                'payment_method_id' => $this->mapInsPaymentMethodId($row->price_type),
                'price_kind' => 'buy',
                'price_primary' => $row->price ?? 0,
                'price_secondary' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
        }

        if ($this->legacyHasTable('item_price_sell')) {
            $sellPrices = $this->legacyTableOrdered('item_price_sell', 'rec_no')->map(fn ($row) => [
                'item_id' => (int) $row->item_no,
                'payment_method_id' => $this->mapInsPaymentMethodId($row->price_type),
                'price_kind' => 'sell',
                'price_primary' => $row->price ?? 0,
                'price_secondary' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
        }

        $this->insertRows('item_prices', $buyPrices);
        $this->insertRows('item_prices', $sellPrices);

        $this->log('Converted '.count($buyPrices).' buy price(s) and '.count($sellPrices).' sell price(s).');
    }

    protected function convertSales(): void
    {
        if (! $this->legacyHasTable('sells')) {
            $this->log('Skipped sales: table [sells] not found.');

            return;
        }

        $invoiceCount = 0;

        DB::connection($this->source)
            ->table('sells')
            ->orderBy('order_no')
            ->chunk(1000, function ($chunk) use (&$invoiceCount): void {
                $invoiceRows = $chunk->map(function ($row) {
                    $grandTotal = (float) ($row->tot ?? 0);
                    $amountPaid = (float) ($row->cash ?? 0);

                    return [
                        'id' => (int) $row->order_no,
                        'invoice_date' => $row->order_date,
                        'customer_id' => (int) $row->jeha,
                        'payment_method_id' => $this->mapInsPaymentMethodId($row->price_type),
                        'warehouse_id' => $this->resolveInsSalesWarehouseId($row),
                        'is_retail' => true,
                        'lines_subtotal' => $row->tot1 ?? 0,
                        'extra_cost' => $row->tot_charges ?? 0,
                        'rate_markup' => 0,
                        'difference_amount' => 0,
                        'discount' => $row->ksm ?? 0,
                        'grand_total' => $grandTotal,
                        'amount_paid' => $amountPaid,
                        'balance' => max($grandTotal - $amountPaid, 0),
                        'deferred_amount' => $row->not_cash ?? 0,
                        'refund_amount' => 0,
                        'unpaid_date' => null,
                        'notes' => $this->stringOrNull($row->notes ?? null),
                        'created_by' => $this->resolveCreatedBy($row->emp ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();

                $this->insertWithIdentity('sales_invoices', $invoiceRows);
                $invoiceCount += count($invoiceRows);
            });

        $this->log('Converted '.$invoiceCount.' sales invoice(s).');

        if (! $this->legacyHasTable('sell_tran')) {
            return;
        }

        $lineCount = 0;

        DB::connection($this->source)
            ->table('sell_tran')
            ->orderBy('rec_no')
            ->chunk(2000, function ($chunk) use (&$lineCount): void {
                $lineRows = $chunk->map(function ($row) {
                    $qty = (float) ($row->quant ?? 0);
                    $unitPrice = (float) ($row->price ?? 0);

                    return [
                        'id' => (int) $row->rec_no,
                        'sales_invoice_id' => (int) $row->order_no,
                        'item_id' => (int) $row->item_no,
                        'barcode' => (string) $row->item_no,
                        'qty_primary' => $qty,
                        'qty_secondary' => 0,
                        'unit_price_primary' => $unitPrice,
                        'unit_price_secondary' => 0,
                        'line_total' => $qty * $unitPrice,
                        'profit' => $row->rebh ?? 0,
                        'sales_return_id' => null,
                        'created_by' => $this->resolveCreatedBy($row->emp ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();

                $this->insertWithIdentity('sales_invoice_lines', $lineRows);
                $lineCount += count($lineRows);
            });

        $this->log('Converted '.$lineCount.' sales invoice line(s).');
    }

    protected function convertInstallments(): void
    {
        $this->ensureInstallmentCustomersExist();
        $this->ensureInstallmentReferenceDataExist();

        $contractEnds = $this->legacyContractEndDates('kst_trans');
        $archivedContractEnds = $this->legacyContractEndDates('TransArc');

        if ($this->legacyHasTable('main')) {
            $salesInvoiceIds = DB::connection($this->target)
                ->table('sales_invoices')
                ->pluck('id')
                ->flip();

            $contracts = $this->legacyTableOrdered('main', 'no')->map(function ($row) use ($contractEnds, $salesInvoiceIds) {
                $mapped = $this->mapInsContractRow($row, $contractEnds);

                if ($mapped['sales_invoice_id'] !== null && ! $salesInvoiceIds->has($mapped['sales_invoice_id'])) {
                    $mapped['sales_invoice_id'] = null;
                }

                return $mapped;
            })->all();
            $this->insertRows('installment_contracts', $contracts);
            $this->log('Converted '.count($contracts).' installment contract(s).');
        }

        if ($this->legacyHasTable('MainArc')) {
            $salesInvoiceIds = DB::connection($this->target)
                ->table('sales_invoices')
                ->pluck('id')
                ->flip();

            $mainArcRows = $this->legacyMainArcRows();

            $archiveIdByContractNo = $mainArcRows
                ->groupBy(fn ($row) => (int) $row->no)
                ->map(fn (Collection $rows) => $rows->min(
                    fn ($row) => InsMainArc::archiveIdFromRow($row, $this->source)
                ));

            $archives = $mainArcRows->map(function ($row) use ($archivedContractEnds, $salesInvoiceIds) {
                $mapped = $this->mapInsContractRow($row, $archivedContractEnds);
                $mapped['id'] = InsMainArc::archiveIdFromRow($row, $this->source);

                if ($mapped['sales_invoice_id'] !== null && ! $salesInvoiceIds->has($mapped['sales_invoice_id'])) {
                    $mapped['sales_invoice_id'] = null;
                }

                unset(
                    $mapped['last_deduction_month'],
                    $mapped['next_installment_date'],
                    $mapped['late_amount'],
                    $mapped['installments_remaining'],
                    $mapped['surplus_count'],
                    $mapped['surplus_amount'],
                    $mapped['suspended_count'],
                    $mapped['suspended_amount'],
                );
                $mapped['archived_at'] = $row->inp_date ?? now();

                return $mapped;
            })->all();

            $this->insertRows('installment_contract_archives', $archives);
            $this->log('Converted '.count($archives).' archived installment contract(s).');
        } else {
            $archiveIdByContractNo = collect();
        }

        if ($this->legacyHasTable('kst_trans')) {
            $deductionCount = 0;

            DB::connection($this->source)
                ->table('kst_trans')
                ->orderBy('wrec_no')
                ->chunk(2000, function ($chunk) use (&$deductionCount): void {
                    $deductions = $chunk
                        ->filter(fn ($row) => filled($row->ksm ?? null) && (float) $row->ksm != 0.0)
                        ->map(fn ($row) => $this->mapInsDeductionRow($row))
                        ->all();

                    if ($deductions === []) {
                        return;
                    }

                    $this->insertWithIdentity('installment_deductions', $deductions);
                    $deductionCount += count($deductions);
                });

            $this->log('Converted '.$deductionCount.' installment deduction(s) (non-zero ksm only).');
        }

        if ($this->legacyHasTable('TransArc')) {
            $archivedDeductionCount = 0;

            DB::connection($this->source)
                ->table('TransArc')
                ->orderBy('wrec_no')
                ->chunk(2000, function ($chunk) use ($archiveIdByContractNo, &$archivedDeductionCount): void {
                    $archivedDeductions = $chunk
                        ->filter(fn ($row) => filled($row->ksm ?? null) && (float) $row->ksm != 0.0)
                        ->map(function ($row) use ($archiveIdByContractNo) {
                            $mapped = $this->mapInsDeductionRow($row);
                            unset($mapped['surplus_id']);
                            $mapped['installment_contract_id'] = (int) ($archiveIdByContractNo[(int) $row->no] ?? $row->no);

                            return $mapped;
                        })
                        ->all();

                    if ($archivedDeductions === []) {
                        return;
                    }

                    $this->insertWithIdentity('installment_deduction_archives', $archivedDeductions);
                    $archivedDeductionCount += count($archivedDeductions);
                });

            $this->log('Converted '.$archivedDeductionCount.' archived installment deduction(s) (non-zero ksm only).');
        }
    }

    /**
     * MainArc (and sometimes main) may reference jeha ids deleted from jeha master.
     * Create placeholder customers so archive contracts keep their FK.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function ensureInstallmentCustomersExist(): void
    {
        $existing = DB::connection($this->target)
            ->table('customers')
            ->pluck('id')
            ->flip();

        /** @var array<int, array<string, mixed>> $placeholders */
        $placeholders = [];

        foreach (['main', 'MainArc'] as $table) {
            if (! $this->legacyHasTable($table)) {
                continue;
            }

            foreach ($this->legacyTableOrdered($table, 'no') as $row) {
                $customerId = (int) ($row->jeha ?? 0);

                if ($customerId <= 0 || $existing->has($customerId)) {
                    continue;
                }

                $placeholders[$customerId] = [
                    'id' => $customerId,
                    'name' => $this->stringOrNull($row->name ?? null) ?? 'غير معروف',
                    'address' => null,
                    'mdar' => null,
                    'libyana' => null,
                    'card_no' => null,
                    'others' => null,
                    'customer_type_id' => null,
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $existing->put($customerId, true);
            }
        }

        if ($placeholders === []) {
            return;
        }

        $this->insertWithIdentity('customers', array_values($placeholders));
        $this->log('Created '.count($placeholders).' placeholder customer(s) for contracts missing from jeha.');
    }

    /**
     * Contracts may reference banks / payroll banks / workplaces before a full master import
     * (e.g. --resume). Import any missing rows referenced by main + MainArc.
     */
    protected function ensureInstallmentReferenceDataExist(): void
    {
        [$bankIds, $payrollBankIds, $workplaceIds] = $this->collectLegacyInstallmentReferences();

        if ($payrollBankIds !== [] && $this->legacyHasTable('BankTajmeehy')) {
            $existing = DB::connection($this->target)->table('payroll_banks')->pluck('id')->flip();
            $rows = [];

            foreach ($this->legacyTableOrdered('BankTajmeehy', 'TajNo') as $row) {
                $id = (int) $row->TajNo;

                if (! in_array($id, $payrollBankIds, true) || $existing->has($id)) {
                    continue;
                }

                $rows[] = [
                    'id' => $id,
                    'name' => $this->stringOrNull($row->TajName ?? null) ?? 'taj_'.$id,
                    'account_number' => $this->stringOrNull($row->TajAcc ?? $row->acc ?? null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $existing->put($id, true);
            }

            $this->insertWithIdentity('payroll_banks', $rows);

            if ($rows !== []) {
                $this->log('Ensured '.count($rows).' payroll bank(s) for installment contracts.');
            }
        }

        if ($workplaceIds !== [] && $this->legacyHasTable('place')) {
            $existing = DB::connection($this->target)->table('workplaces')->pluck('id')->flip();
            $rows = [];

            foreach ($this->legacyTableOrdered('place', 'place_no') as $row) {
                $id = (int) $row->place_no;

                if (! in_array($id, $workplaceIds, true) || $existing->has($id)) {
                    continue;
                }

                $rows[] = [
                    'id' => $id,
                    'name' => $this->stringOrNull($row->place_name ?? null) ?? 'place_'.$id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $existing->put($id, true);
            }

            $this->insertWithIdentity('workplaces', $rows);

            if ($rows !== []) {
                $this->log('Ensured '.count($rows).' workplace(s) for installment contracts.');
            }
        }

        if ($bankIds !== [] && $this->legacyHasTable('bank')) {
            $existing = DB::connection($this->target)->table('installment_banks')->pluck('id')->flip();
            $rows = [];

            foreach ($this->legacyTableOrdered('bank', 'bank_no') as $row) {
                $id = (int) $row->bank_no;

                if (! in_array($id, $bankIds, true) || $existing->has($id)) {
                    continue;
                }

                $rows[] = [
                    'id' => $id,
                    'name' => $this->stringOrNull($row->bank_name ?? null) ?? 'bank_'.$id,
                    'payroll_bank_id' => filled($row->bank_tajmeeh ?? $row->taj_id ?? null)
                        ? (int) ($row->bank_tajmeeh ?? $row->taj_id)
                        : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $existing->put($id, true);
            }

            $this->insertWithIdentity('installment_banks', $rows);

            if ($rows !== []) {
                $this->log('Ensured '.count($rows).' installment bank(s) for installment contracts.');
            }
        }
    }

    /**
     * @return array{0: list<int>, 1: list<int>, 2: list<int>}
     */
    protected function collectLegacyInstallmentReferences(): array
    {
        $bankIds = [];
        $payrollBankIds = [];
        $workplaceIds = [];

        foreach (['main', 'MainArc'] as $table) {
            if (! $this->legacyHasTable($table)) {
                continue;
            }

            foreach ($this->legacyTable($table) as $row) {
                if (filled($row->bank ?? null) && (int) $row->bank > 0) {
                    $bankIds[(int) $row->bank] = true;
                }

                if (filled($row->taj_id ?? null) && (int) $row->taj_id > 0) {
                    $payrollBankIds[(int) $row->taj_id] = true;
                }

                if (filled($row->place ?? null) && (int) $row->place > 0) {
                    $workplaceIds[(int) $row->place] = true;
                }
            }
        }

        return [array_keys($bankIds), array_keys($payrollBankIds), array_keys($workplaceIds)];
    }

    /**
     * @param  Collection<int, string|null>  $contractEnds
     * @return array<string, mixed>
     */
    protected function mapInsContractRow(object $row, Collection $contractEnds): array
    {
        $contractId = (int) $row->no;

        return [
            'id' => $contractId,
            'customer_id' => (int) $row->jeha,
            'installment_bank_id' => filled($row->bank ?? null) ? (int) $row->bank : null,
            'workplace_id' => filled($row->place ?? null) ? (int) $row->place : null,
            'payroll_bank_id' => filled($row->taj_id ?? null) ? (int) $row->taj_id : null,
            'bank_account_number' => $this->stringOrNull($row->acc ?? null),
            'contract_start' => $row->sul_date ?? null,
            'contract_end' => $contractEnds->get($contractId),
            'contract_total' => $row->sul ?? 0,
            'installment_count' => (int) ($row->kst_count ?? 0),
            'installment_amount' => $row->kst ?? 0,
            'total_paid' => $row->sul_pay ?? 0,
            'balance' => $row->raseed ?? 0,
            'sales_invoice_id' => filled($row->order_no ?? null) ? (int) $row->order_no : null,
            'cheques_in' => (int) ($row->chk_in ?? 0),
            'cheques_out' => (int) ($row->chk_out ?? 0),
            'last_deduction_month' => null,
            'next_installment_date' => null,
            'late_amount' => 0,
            'installments_remaining' => 0,
            'surplus_count' => 0,
            'surplus_amount' => 0,
            'suspended_count' => 0,
            'suspended_amount' => 0,
            'notes' => $this->stringOrNull($row->notes ?? null),
            'created_by' => $this->resolveCreatedBy($row->emp ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapInsDeductionRow(object $row): array
    {
        return [
            'id' => (int) $row->wrec_no,
            'installment_contract_id' => (int) $row->no,
            'sequence' => (int) ($row->ser ?? 0),
            'deducted_amount' => $row->ksm ?? 0,
            'deduction_date' => $row->ksm_date ?? null,
            'installment_due_date' => $row->kst_date ?? null,
            'deduction_type_id' => (int) ($row->ksm_type ?? 0),
            'notes' => $this->stringOrNull($row->kst_notes ?? null),
            'batch_id' => filled($row->h_no ?? null) && (int) $row->h_no > 0 ? (int) $row->h_no : null,
            'surplus_id' => null,
            'remaining_balance' => 0,
            'created_by' => $this->resolveCreatedBy($row->emp ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return Collection<int, string|null>
     */
    protected function legacyContractEndDates(string $table): Collection
    {
        if (! $this->legacyHasTable($table)) {
            return collect();
        }

        return DB::connection($this->source)
            ->table($table)
            ->selectRaw('[no], MAX(kst_date) as contract_end')
            ->groupBy('no')
            ->pluck('contract_end', 'no');
    }

    /**
     * INS halls share numeric ids with stores — offset hall warehouse ids to avoid collisions.
     */
    protected function insHallWarehouseId(int $hallNo): int
    {
        return 10000 + $hallNo;
    }

    protected function convertInsWarehouses(): void
    {
        $rows = [];

        if ($this->legacyHasTable('stores_names')) {
            foreach ($this->legacyTableOrdered('stores_names', 'st_no') as $row) {
                $rows[] = [
                    'id' => (int) $row->st_no,
                    'name' => $this->stringOrNull($row->st_name ?? null) ?? 'store_'.$row->st_no,
                    'warehouse_type' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($this->legacyHasTable('halls_names')) {
            foreach ($this->legacyTableOrdered('halls_names', 'hall_no') as $row) {
                $rows[] = [
                    'id' => $this->insHallWarehouseId((int) $row->hall_no),
                    'name' => $this->stringOrNull($row->hall_name ?? null) ?? 'hall_'.$row->hall_no,
                    'warehouse_type' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows === []) {
            $this->log('Skipped warehouses: [stores_names] and [halls_names] not found.');

            return;
        }

        $this->insertWithIdentity('warehouses', $rows);
        $this->log('Converted '.count($rows).' warehouse(s) from stores_names / halls_names.');
    }

    /**
     * sell_type 1 → stores_names.st_no | sell_type 2 → halls_names.hall_no (offset id).
     */
    protected function resolveInsSalesWarehouseId(object $row): int
    {
        $placeNo = (int) $row->place_no;
        $sellType = (int) ($row->sell_type ?? 1);

        if ($sellType === 1) {
            return $placeNo;
        }

        return $this->insHallWarehouseId($placeNo);
    }

    protected function convertUsers(): void
    {
        $central = $this->insCentralConnection();
        $targetCentral = $this->centralConnection();

        if (! Schema::connection($central)->hasTable('users')) {
            $this->log("Skipped users: table [users] not found on [{$central}].");

            return;
        }

        $legacyUsers = DB::connection($central)
            ->table('users')
            ->where('company', $this->source)
            ->orderBy('id')
            ->get();

        if ($legacyUsers->isEmpty()) {
            $this->log("No users found for company [{$this->source}] on [{$central}].");

            return;
        }

        $rows = $legacyUsers->map(function ($row) use ($targetCentral) {
            $payload = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'email_verified_at' => $row->email_verified_at ?? null,
                'password' => $row->password,
                'company' => $this->target,
                'warehouse_id' => null,
                'status' => 1,
                'remember_token' => $row->remember_token ?? null,
                'is_prog' => false,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ];

            if (Schema::connection($targetCentral)->hasColumn('users', 'empno')) {
                $payload['old_user_id'] = (int) $row->id;

                if (isset($row->empno) && $row->empno !== null) {
                    $payload['empno'] = (int) $row->empno;
                }
            }

            return $payload;
        })->all();

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::connection($targetCentral)->transaction(function () use ($targetCentral, $chunk): void {
                DB::connection($targetCentral)->unprepared('SET IDENTITY_INSERT [users] ON');

                foreach ($chunk as $row) {
                    $id = (int) $row['id'];
                    $payload = collect($row)->except('id')->all();

                    $existingById = DB::connection($targetCentral)->table('users')->where('id', $id)->exists();
                    $existingByEmail = filled($row['email'])
                        && DB::connection($targetCentral)->table('users')->where('email', $row['email'])->exists();

                    if ($existingById) {
                        DB::connection($targetCentral)->table('users')->where('id', $id)->update($payload);

                        continue;
                    }

                    if ($existingByEmail) {
                        $this->log("Skipped user id {$id}: email [{$row['email']}] already exists in ERP (emp map will use existing account).");

                        continue;
                    }

                    DB::connection($targetCentral)->table('users')->insert($row);
                }

                DB::connection($targetCentral)->unprepared('SET IDENTITY_INSERT [users] OFF');
            });
        }

        $userIds = array_column($rows, 'id');
        $this->importUserRolesFromInsCentral($central, $targetCentral, $userIds);

        $this->empToUserIdLoaded = false;
        $this->loadEmpToUserMap();

        $this->log('Imported '.count($rows).' user(s) for ['.$this->source.'] → ['.$this->target.'].');
        $this->log('Emp map entries: '.count($this->empToUserId));
    }

    /**
     * Backfill empno / old_user_id on central users so SQL conversion scripts can join on empno.
     */
    public function ensureUserEmpnoForSqlScripts(): void
    {
        $targetCentral = $this->centralConnection();
        $insCentral = $this->insCentralConnection();

        if (! Schema::connection($targetCentral)->hasColumn('users', 'empno')) {
            throw new RuntimeException(
                'Central users table is missing column [empno]. Run central migrations first: php artisan migrate'
            );
        }

        if (! Schema::connection($insCentral)->hasColumn('users', 'empno')) {
            $this->log('Legacy users have no empno column — SQL created_by joins will use fallback user id only.');

            return;
        }

        $legacyUsers = DB::connection($insCentral)
            ->table('users')
            ->where('company', $this->source)
            ->whereNotNull('empno')
            ->get(['id', 'email', 'empno']);

        $updated = 0;

        foreach ($legacyUsers as $row) {
            $targetId = $this->resolveTargetUserId($targetCentral, (int) $row->id, $row->email);

            if ($targetId === null) {
                continue;
            }

            DB::connection($targetCentral)
                ->table('users')
                ->where('id', $targetId)
                ->update([
                    'empno' => (int) $row->empno,
                    'old_user_id' => (int) $row->id,
                    'updated_at' => now(),
                ]);

            $updated++;
        }

        $this->log("Backfilled empno on {$updated} central user(s) for SQL scripts.");
    }

    /**
     * @param  list<int>  $userIds
     */
    protected function importUserRolesFromInsCentral(string $sourceCentral, string $targetCentral, array $userIds): void
    {
        if ($userIds === [] || ! Schema::connection($sourceCentral)->hasTable('model_has_roles')) {
            return;
        }

        $existingRoles = DB::connection($targetCentral)->table('roles')->pluck('id')->all();
        $existingRoles = array_fill_keys($existingRoles, true);

        $assigned = 0;

        foreach (
            DB::connection($sourceCentral)
                ->table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->whereIn('model_id', $userIds)
                ->get() as $row
        ) {
            $roleId = (int) $row->role_id;

            if (! isset($existingRoles[$roleId])) {
                continue;
            }

            $exists = DB::connection($targetCentral)
                ->table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', (int) $row->model_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::connection($targetCentral)->table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => (int) $row->model_id,
            ]);

            $assigned++;
        }

        if ($assigned > 0) {
            $this->log("Assigned {$assigned} role(s) to imported users.");
        }
    }

    protected function backfillCreatedBy(): void
    {
        $this->loadEmpToUserMap();

        if ($this->empToUserId === []) {
            throw new RuntimeException(
                'No emp → user map found. Run the [users] step first (ins:convert '.$this->source.' --only=users).'
            );
        }

        $total = 0;
        $total += $this->backfillCreatedByFromLegacy('sells', 'order_no', 'sales_invoices', 'id');
        $total += $this->backfillCreatedByFromLegacy('sell_tran', 'rec_no', 'sales_invoice_lines', 'id');
        $total += $this->backfillCreatedByFromLegacy('main', 'no', 'installment_contracts', 'id');
        if ($this->legacyHasColumn('MainArc', 'id')) {
            $total += $this->backfillCreatedByFromLegacy('MainArc', 'id', 'installment_contract_archives', 'id');
        } else {
            $total += $this->backfillMainArcCreatedByWithoutLegacyId();
        }
        $total += $this->backfillCreatedByFromLegacy('kst_trans', 'wrec_no', 'installment_deductions', 'id');
        $total += $this->backfillCreatedByFromLegacy('TransArc', 'wrec_no', 'installment_deduction_archives', 'id');

        $this->log("Backfilled created_by on {$total} row(s).");
    }

    protected function backfillCreatedByFromLegacy(
        string $legacyTable,
        string $legacyKey,
        string $targetTable,
        ?string $targetKey = null,
    ): int {
        if (! $this->legacyHasTable($legacyTable)) {
            return 0;
        }

        if (! Schema::connection($this->target)->hasTable($targetTable)) {
            return 0;
        }

        $targetKey ??= $legacyKey;

        if (DB::connection($this->target)->getDriverName() === 'sqlsrv') {
            $updated = $this->backfillCreatedByUsingSqlServerJoin(
                $legacyTable,
                $legacyKey,
                $targetTable,
                $targetKey,
            );
            $this->log("  {$targetTable}: {$updated}");

            return $updated;
        }

        $updated = 0;

        foreach ($this->empToUserId as $empNo => $userId) {
            DB::connection($this->source)
                ->table($legacyTable)
                ->where('emp', $empNo)
                ->select($legacyKey)
                ->orderBy($legacyKey)
                ->chunk(1000, function ($rows) use ($legacyKey, $targetTable, $targetKey, $userId, &$updated): void {
                    $ids = $rows->pluck($legacyKey)->all();

                    if ($ids === []) {
                        return;
                    }

                    $updated += DB::connection($this->target)
                        ->table($targetTable)
                        ->whereIn($targetKey, $ids)
                        ->update(['created_by' => $userId]);
                });
        }

        $this->log("  {$targetTable}: {$updated}");

        return $updated;
    }

    protected function backfillCreatedByUsingSqlServerJoin(
        string $legacyTable,
        string $legacyKey,
        string $targetTable,
        string $targetKey,
    ): int {
        $targetDb = DB::connection($this->target)->getDatabaseName();
        $sourceDb = DB::connection($this->source)->getDatabaseName();
        $legacyCentralDb = DB::connection($this->insCentralConnection())->getDatabaseName();
        $erpCentralDb = DB::connection($this->centralConnection())->getDatabaseName();
        $legacyCompany = str_replace("'", "''", $this->source);

        $sql = <<<SQL
            UPDATE target
            SET target.created_by = mapped.user_id
            FROM [{$targetDb}].dbo.[{$targetTable}] AS target
            INNER JOIN [{$sourceDb}].dbo.[{$legacyTable}] AS legacy
                ON target.[{$targetKey}] = legacy.[{$legacyKey}]
            INNER JOIN (
                SELECT
                    lu.empno,
                    COALESCE(u_by_id.id, u_by_email.id) AS user_id
                FROM [{$legacyCentralDb}].dbo.[users] AS lu
                LEFT JOIN [{$erpCentralDb}].dbo.[users] AS u_by_id
                    ON u_by_id.id = lu.id
                LEFT JOIN [{$erpCentralDb}].dbo.[users] AS u_by_email
                    ON u_by_email.email = lu.email
                WHERE lu.company = N'{$legacyCompany}'
                    AND lu.empno IS NOT NULL
            ) AS mapped ON mapped.empno = legacy.emp
            WHERE legacy.emp IS NOT NULL
                AND legacy.emp > 0
                AND mapped.user_id IS NOT NULL
        SQL;

        return DB::connection($this->target)->update($sql);
    }

    protected function backfillMainArcCreatedByWithoutLegacyId(): int
    {
        if (! $this->legacyHasTable('MainArc')) {
            return 0;
        }

        if (! Schema::connection($this->target)->hasTable('installment_contract_archives')) {
            return 0;
        }

        $targetDb = DB::connection($this->target)->getDatabaseName();
        $sourceDb = DB::connection($this->source)->getDatabaseName();
        $legacyCentralDb = DB::connection($this->insCentralConnection())->getDatabaseName();
        $erpCentralDb = DB::connection($this->centralConnection())->getDatabaseName();
        $legacyCompany = str_replace("'", "''", $this->source);
        $offset = InsMainArc::ID_OFFSET;

        $sql = <<<SQL
            UPDATE target
            SET target.created_by = mapped.user_id
            FROM [{$targetDb}].dbo.[installment_contract_archives] AS target
            INNER JOIN [{$sourceDb}].dbo.[MainArc] AS legacy
                ON target.[id] = legacy.[order_no] + {$offset}
            INNER JOIN (
                SELECT
                    lu.empno,
                    COALESCE(u_by_id.id, u_by_email.id) AS user_id
                FROM [{$legacyCentralDb}].dbo.[users] AS lu
                LEFT JOIN [{$erpCentralDb}].dbo.[users] AS u_by_id
                    ON u_by_id.id = lu.id
                LEFT JOIN [{$erpCentralDb}].dbo.[users] AS u_by_email
                    ON u_by_email.email = lu.email
                WHERE lu.company = N'{$legacyCompany}'
                    AND lu.empno IS NOT NULL
            ) AS mapped ON mapped.empno = legacy.emp
            WHERE legacy.emp IS NOT NULL
                AND legacy.emp > 0
                AND mapped.user_id IS NOT NULL
        SQL;

        $updated = DB::connection($this->target)->update($sql);
        $this->log("  installment_contract_archives: {$updated}");

        return $updated;
    }

    protected function findLegacyCompanyRegistryRow(string $legacyName): ?object
    {
        $central = $this->insCentralConnection();

        if (! config("database.connections.{$central}")) {
            return null;
        }

        if (! Schema::connection($central)->hasTable('Customers')) {
            return null;
        }

        return DB::connection($central)
            ->table('Customers')
            ->where('Company', $legacyName)
            ->first();
    }
}
