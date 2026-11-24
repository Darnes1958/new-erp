<?php

namespace App\Services\Conversion;

use App\Enums\InstallmentDeductionType;
use App\Services\Conversion\Concerns\ConvertsSqlServerCompanyData;
use App\Services\Installments\InstallmentContractMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AlmedadDataConverter
{
    use ConvertsSqlServerCompanyData;

    public const PAYMENT_CASH = 1;

    public const PAYMENT_BANK = 2;

    public const PAYMENT_INSTALLMENT = 3;

    protected string $source;

    protected string $target;

    /** @var list<array{code: string, message: string, context: array<string, mixed>}> */
    protected array $issues = [];

    protected int $defaultWarehouseId = 1;

    /** @var array<int, true> */
    protected array $itemIds = [];

    /** @var array<int, true> */
    protected array $customerIds = [];

    /** @var array<int, true> */
    protected array $supplierIds = [];

    /** @var array<int, true> */
    protected array $warehouseIds = [];

    /** @var array<int, int> */
    protected array $customerPaymentMethod = [];


    public function __construct(string $source = 'ALMEDADSOFT_0001', string $target = 'Bentaher3')
    {
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * @param  list<string>|null  $only
     */
    public function convert(bool $fresh = false, ?array $only = null): void
    {
        $this->assertConnections();

        $steps = [
            'register_company' => fn () => $this->registerCompany(),
            'payment_methods' => fn () => $this->convertPaymentMethods(),
            'master' => fn () => $this->convertMaster(),
            'parties' => fn () => $this->convertParties(),
            'items' => fn () => $this->convertItems(),
            'stock' => fn () => $this->convertStock(),
            'purchases' => fn () => $this->convertPurchases(),
            'sales' => fn () => $this->convertSales(),
            'sales_returns' => fn () => $this->convertSalesReturns(),
            'transfers' => fn () => $this->convertTransfers(),
            'installments' => fn () => $this->convertInstallments(),
        ];

        if ($only) {
            $steps = array_intersect_key($steps, array_flip($only));
        }

        if ($fresh) {
            $this->log('Clearing converted tables on target...');
            $this->clearConvertedTables();
        }

        $this->disableForeignKeys();

        try {
            foreach ($steps as $name => $step) {
                $this->log("Converting: {$name}");
                $step();
                $this->log("Done: {$name}");
            }
        } finally {
            $this->enableForeignKeys();
            $this->writeIssues();
        }
    }

    /**
     * @return list<array{code: string, message: string, context: array<string, mixed>}>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    protected function assertConnections(): void
    {
        foreach ([$this->source, $this->target] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }

        if (! Schema::connection($this->target)->hasTable('sales_invoices')) {
            throw new RuntimeException("Target [{$this->target}] has no company schema. Run: php artisan erp:migrate-company {$this->target}");
        }
    }

    protected function registerCompany(): void
    {
        $display = 'بن طاهر';
        $suffix = 'توريد وبيع الأثاث';
        $central = (string) config('erp.central_connection', 'sqlsrv');

        DB::connection($central)->table('our_companies')->updateOrInsert(
            ['connection_name' => $this->target],
            [
                'display_name' => $display,
                'display_name_suffix' => $suffix,
                'is_active' => true,
            ],
        );

        DB::connection($central)->table('our_companies')
            ->where('connection_name', 'Almedad_erp')
            ->delete();

        DB::connection($central)->table('company_settings')
            ->where('company', 'Almedad_erp')
            ->delete();

        DB::connection($central)->table('company_settings')->updateOrInsert(
            ['company' => $this->target],
            [
                'has_expiry_dates' => false,
                'has_dual_unit' => false,
                'multi_warehouse' => true,
                'wholesale_retail' => true,
                'barcode_enabled' => true,
                'link_sales_to_installments' => false,
                'installment_by_payroll_bank' => false,
                'auto_price_update' => false,
            ],
        );

        $this->log("Registered our_companies [{$this->target}]");
    }

    protected function convertPaymentMethods(): void
    {
        $now = now();
        $this->insertWithIdentity('payment_methods', [
            ['id' => self::PAYMENT_CASH, 'name' => 'نقدي', 'code' => 'cash', 'rate' => 0, 'adjustment_value' => 0, 'adjustment_direction' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::PAYMENT_BANK, 'name' => 'مصرفي', 'code' => 'bank', 'rate' => 0, 'adjustment_value' => 0, 'adjustment_direction' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::PAYMENT_INSTALLMENT, 'name' => 'تقسيط', 'code' => 'installment', 'rate' => 0, 'adjustment_value' => 0, 'adjustment_direction' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    protected function convertMaster(): void
    {
        $now = now();

        $units = $this->legacyHasTable('UnitMain')
            ? DB::connection($this->source)->table('UnitMain')->orderBy('id')->get()
            : collect();

        if ($units->isEmpty()) {
            $this->insertWithIdentity('units', [[
                'id' => 1,
                'name' => 'قطعة',
                'abbreviation' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);
        } else {
            $this->insertWithIdentity('units', $units->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $this->clip((string) ($row->name_unitMain ?? $row->description ?? $row->name ?? 'وحدة '.$row->id), 80) ?: 'وحدة '.$row->id,
                'abbreviation' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }

        $types = DB::connection($this->source)->table('typmas')->orderBy('id')->get();
        $this->insertWithIdentity('item_types', $types->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $this->clip((string) ($row->description ?? 'نوع '.$row->id), 120) ?: 'نوع '.$row->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $this->issue('item_types', 'كل الأصناف تقريباً مربوطة بنوع واحد في المصدر (typmas). راجع التصنيف بعد النقل.', []);

        $sourceWarehouses = DB::connection($this->source)->table('mkazen')->orderBy('id')->get()->keyBy(fn ($row) => (int) $row->id);
        $usedWarehouseIds = collect()
            ->merge($sourceWarehouses->keys())
            ->merge(DB::connection($this->source)->table('quntity')->pluck('mak_id'))
            ->merge(DB::connection($this->source)->table('nkl')->pluck('mak1'))
            ->merge(DB::connection($this->source)->table('nkl')->pluck('mak2'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn ($id) => $id > 0)
            ->sort()
            ->values();

        $warehouseRows = [];
        foreach ($usedWarehouseIds as $id) {
            $source = $sourceWarehouses->get($id);
            if ($source) {
                $name = $this->clip(trim((string) ($source->description ?? '')), 120) ?: 'مخزن '.$id;
            } else {
                $name = 'مخزن محذوف رقم '.$id;
                $this->issue('missing_warehouse', 'مخزن مستخدم في الأرصدة أو النقل لكنه محذوف من دليل المخازن — أُنشئ بديلاً غير نشط.', ['id' => $id]);
            }

            $this->warehouseIds[$id] = true;
            $warehouseRows[] = [
                'id' => $id,
                'name' => $name,
                'warehouse_type' => 1,
                'is_active' => $source !== null && ! str_contains($name, 'عجز'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('warehouses', $warehouseRows);

        $firstActive = collect($warehouseRows)->first(fn ($row) => $row['is_active']);
        $this->defaultWarehouseId = (int) ($firstActive['id'] ?? $warehouseRows[0]['id'] ?? 1);

        if ($this->legacyHasTable('bank') && $this->legacyHasTable('hsabt')) {
            $banks = DB::connection($this->source)->table('bank')->get()->keyBy('id');
            $accounts = DB::connection($this->source)->table('hsabt')->orderBy('id')->get();
            $this->insertWithIdentity('bank_accounts', $accounts->map(function ($row) use ($banks, $now) {
                $bankName = (string) ($banks->get($row->bank_id)?->description ?? 'مصرف');

                return [
                    'id' => (int) $row->id,
                    'name' => $this->clip(trim($bankName.' '.(string) ($row->num ?? '')), 120),
                    'account_number' => $this->clip((string) ($row->num ?? ''), 64),
                    'opening_balance' => $this->money($row->balance ?? 0),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all());
        }
    }

    protected function convertParties(): void
    {
        $now = now();
        $classes = DB::connection($this->source)->table('cust')
            ->select('class')
            ->whereNotNull('class')
            ->distinct()
            ->orderBy('class')
            ->pluck('class')
            ->values();

        $typeIds = [];
        $typeRows = [];
        foreach ($classes as $index => $name) {
            $id = $index + 1;
            $label = $this->clip(trim((string) $name), 120) ?: 'عام';
            $typeIds[$label] = $id;
            $typeRows[] = [
                'id' => $id,
                'name' => $label,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('customer_types', $typeRows);

        $customers = DB::connection($this->source)->table('cust')->orderBy('id')->get();
        $customerRows = [];
        $supplierRows = [];
        $cashBoxRows = [];

        foreach ($customers as $row) {
            $id = (int) $row->id;
            $name = $this->clip(trim((string) ($row->name ?? '')), 180) ?: 'طرف '.$id;
            $class = $this->clip(trim((string) ($row->class ?? '')), 120);
            $typeId = $class !== null && isset($typeIds[$class]) ? $typeIds[$class] : null;
            $plan = $this->parseInstallmentPlan((string) ($row->name ?? ''));
            $others = trim(($class ? 'تصنيف: '.$class : '').($plan ? ' | خطة: '.$plan['amount'].'*'.$plan['count'] : ''));

            $isCashBox = $class === 'الخزائن' || str_contains($name, 'خزين');
            $isInstallment = $this->isInstallmentClass($class);

            $this->customerPaymentMethod[$id] = $isInstallment
                ? self::PAYMENT_INSTALLMENT
                : self::PAYMENT_CASH;

            $payload = [
                'id' => $id,
                'name' => $name,
                'address' => $this->clip((string) ($row->addres ?? ''), 180),
                'mdar' => $this->clip((string) ($row->tel ?? ''), 40),
                'libyana' => null,
                'card_no' => null,
                'others' => $this->clip($others, 180),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->customerIds[$id] = true;
            $customerRows[] = $payload + [
                'customer_type_id' => $typeId,
                'created_by' => null,
            ];

            if ((int) ($row->mord ?? 0) === 1) {
                $this->supplierIds[$id] = true;
                $supplierRows[] = [
                    'id' => $id,
                    'name' => $name,
                    'address' => $payload['address'],
                    'mdar' => $payload['mdar'],
                    'libyana' => null,
                    'card_no' => null,
                    'others' => $payload['others'],
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($isCashBox) {
                $cashBoxRows[] = [
                    'id' => $id,
                    'name' => $name,
                    'opening_balance' => $this->money($row->mden_end ?? 0),
                    'assigned_user_id' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $this->issue('cash_box_as_customer', 'خزينة مسجّلة كزبون في المصدر وبقيت زبوناً للحفاظ على فواتيرها.', ['id' => $id, 'name' => $name]);
            }
        }

        if ($this->legacyHasTable('msh')) {
            $purchasePartyIds = DB::connection($this->source)->table('msh')->pluck('sno_id')
                ->map(fn ($value) => (int) $value)
                ->flip();
            foreach ($customers as $row) {
                $id = (int) $row->id;
                if (! $purchasePartyIds->has($id) || isset($this->supplierIds[$id])) {
                    continue;
                }

                $this->supplierIds[$id] = true;
                $supplierRows[] = [
                    'id' => $id,
                    'name' => $this->clip(trim((string) ($row->name ?? '')), 180) ?: 'طرف '.$id,
                    'address' => $this->clip((string) ($row->addres ?? ''), 180),
                    'mdar' => $this->clip((string) ($row->tel ?? ''), 40),
                    'libyana' => null,
                    'card_no' => null,
                    'others' => 'أُضيف كمورد لأنه ظهر في فواتير الشراء',
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $this->issue('supplier_from_purchase', 'طرف ظهر في الشراء دون علامة مورد — أُضيف كمورد.', ['id' => $id, 'name' => $row->name ?? null]);
            }
        }

        $this->insertWithIdentity('customers', $customerRows);
        $this->insertWithIdentity('suppliers', $supplierRows);
        $this->insertWithIdentity('cash_boxes', $cashBoxRows);
        $this->log('Customers '.$this->count($customerRows).', suppliers '.$this->count($supplierRows).', cash boxes '.$this->count($cashBoxRows));
    }

    protected function convertItems(): void
    {
        $now = now();
        $typeIds = DB::connection($this->target)->table('item_types')->pluck('id')->flip();
        $unitIds = DB::connection($this->target)->table('units')->pluck('id')->flip();
        $defaultUnit = (int) (DB::connection($this->target)->table('units')->min('id') ?? 1);

        $items = DB::connection($this->source)->table('items')->orderBy('id')->get();
        $itemRows = [];
        $barcodeRows = [];
        $priceRows = [];
        $seenBarcodes = [];

        foreach ($items as $row) {
            $id = (int) $row->id;
            $this->itemIds[$id] = true;
            $typeId = (int) ($row->typ_id ?? 0);
            $unitId = (int) ($row->unitid ?? 0);
            $barcode = $this->clip((string) ($row->PARCODE1 ?? ''), 64) ?? '';

            $itemRows[] = [
                'id' => $id,
                'name' => $this->clip((string) ($row->item_name ?? 'صنف '.$id), 180),
                'barcode' => $barcode !== '' ? $barcode : null,
                'item_type_id' => $typeIds->has($typeId) ? $typeId : null,
                'brand_id' => null,
                'primary_unit_id' => $unitIds->has($unitId) ? $unitId : $defaultUnit,
                'secondary_unit_id' => null,
                'has_dual_unit' => false,
                'conversion_factor' => 1,
                'default_buy_price' => $this->money($row->p_tkl ?? 0),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($barcode !== '' && ! isset($seenBarcodes[$barcode])) {
                $seenBarcodes[$barcode] = $id;
                $barcodeRows[] = [
                    'item_id' => $id,
                    'barcode' => $barcode,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } elseif ($barcode !== '') {
                $this->issue('duplicate_barcode', 'باركود مكرر تم الإبقاء على أول صنف.', ['barcode' => $barcode, 'item_id' => $id, 'kept_item_id' => $seenBarcodes[$barcode]]);
            }

            $sell = $this->money($row->price ?? 0);
            $buy = $this->money($row->p_tkl ?? 0);
            foreach ([self::PAYMENT_CASH, self::PAYMENT_BANK, self::PAYMENT_INSTALLMENT] as $method) {
                $priceRows[] = [
                    'item_id' => $id,
                    'payment_method_id' => $method,
                    'price_kind' => 'sell',
                    'price_primary' => $sell,
                    'price_secondary' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $priceRows[] = [
                    'item_id' => $id,
                    'payment_method_id' => $method,
                    'price_kind' => 'buy',
                    'price_primary' => $buy,
                    'price_secondary' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertWithIdentity('items', $itemRows);
        $this->insertChunks('item_barcodes', $barcodeRows);
        $this->insertChunks('item_prices', $priceRows);
        $this->log('Items '.$this->count($itemRows));
    }

    protected function convertStock(): void
    {
        $now = now();
        $rows = DB::connection($this->source)->table('quntity')->orderBy('id')->get();
        $seen = [];
        $stock = [];

        foreach ($rows as $row) {
            $warehouseId = (int) $row->mak_id;
            $itemId = (int) $row->item_id;
            if (! isset($this->warehouseIds[$warehouseId]) || ! isset($this->itemIds[$itemId])) {
                continue;
            }

            $key = $warehouseId.':'.$itemId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $stock[] = [
                'id' => (int) $row->id,
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'quantity_primary' => $this->money($row->qunt ?? 0),
                'quantity_secondary' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('warehouse_stocks', $stock);
        $this->log('Warehouse stocks '.$this->count($stock));
    }

    protected function convertPurchases(): void
    {
        $now = now();
        $headers = DB::connection($this->source)->table('msh')->orderBy('id')->get();
        $invoiceRows = [];

        foreach ($headers as $row) {
            $supplierId = (int) ($row->sno_id ?? 0);
            if ($supplierId !== 0 && ! isset($this->supplierIds[$supplierId])) {
                $this->issue('purchase_unknown_supplier', 'فاتورة شراء لمورد غير مدرج كمورد — نُقلت بلا مورد.', ['purchase_id' => $row->id, 'sno_id' => $supplierId]);
                $supplierId = 0;
            }

            $invoiceRows[] = [
                'id' => (int) $row->id,
                'invoice_date' => $this->date($row->e_date),
                'supplier_id' => $supplierId > 0 ? $supplierId : null,
                'payment_method_id' => self::PAYMENT_CASH,
                'warehouse_id' => $this->defaultWarehouseId,
                'lines_subtotal' => $this->money($row->tottal ?? 0),
                'amount_paid' => $this->money($row->tottal ?? 0),
                'balance' => 0,
                'notes' => $this->clip(trim((string) ($row->nots ?? '').' '.(string) ($row->nots1 ?? '')), 250),
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('purchase_invoices', $invoiceRows);

        $invoiceIds = collect($invoiceRows)->pluck('id')->flip();
        $lines = DB::connection($this->source)->table('msh_s')->orderBy('id')->get();
        $lineRows = [];

        foreach ($lines as $row) {
            $invoiceId = (int) $row->msh_id;
            $itemId = (int) $row->item_id;
            if (! $invoiceIds->has($invoiceId) || ! isset($this->itemIds[$itemId])) {
                $this->issue('purchase_line_skipped', 'بند شراء بلا فاتورة أو صنف.', ['id' => $row->id, 'msh_id' => $invoiceId, 'item_id' => $itemId]);

                continue;
            }

            $qty = $this->money($row->kmea ?? 0);
            $price = $this->money($row->price ?? 0);
            $lineRows[] = [
                'id' => (int) $row->id,
                'purchase_invoice_id' => $invoiceId,
                'item_id' => $itemId,
                'barcode' => null,
                'qty_primary' => $qty,
                'qty_secondary' => 0,
                'unit_cost_primary' => $price,
                'line_cost_total' => round($qty * $price, 3),
                'remaining_qty_primary' => $qty,
                'remaining_qty_secondary' => 0,
                'purchase_return_id' => null,
                'expiry_date' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('purchase_invoice_lines', $lineRows);
        $this->log('Purchases '.$this->count($invoiceRows).' / lines '.$this->count($lineRows));
    }

    protected function convertSales(): void
    {
        $now = now();
        $headers = DB::connection($this->source)->table('mbe')->orderBy('id')->get();
        $invoiceRows = [];

        foreach ($headers as $row) {
            $customerId = (int) ($row->sno_id ?? 0);
            if ($customerId !== 0 && ! isset($this->customerIds[$customerId])) {
                $this->issue('sale_unknown_customer', 'فاتورة بيع لزبون غير موجود — نُقلت بلا زبون.', ['sale_id' => $row->id, 'sno_id' => $customerId]);
                $customerId = 0;
            }

            $method = $this->customerPaymentMethod[$customerId] ?? self::PAYMENT_CASH;
            $total = $this->money($row->tottal ?? 0);
            $discount = $this->money($row->dis ?? 0);
            $isInstallment = $method === self::PAYMENT_INSTALLMENT;

            $invoiceRows[] = [
                'id' => (int) $row->id,
                'invoice_date' => $this->date($row->e_date),
                'customer_id' => $customerId > 0 ? $customerId : null,
                'payment_method_id' => $method,
                'warehouse_id' => $this->defaultWarehouseId,
                'is_retail' => true,
                'lines_subtotal' => $total,
                'extra_cost' => 0,
                'rate_markup' => 0,
                'difference_amount' => 0,
                'discount' => $discount,
                'grand_total' => $total,
                'amount_paid' => $isInstallment ? 0 : $total,
                'balance' => $isInstallment ? $total : 0,
                'deferred_amount' => $isInstallment ? $total : 0,
                'refund_amount' => 0,
                'unpaid_date' => null,
                'notes' => $this->clip(trim((string) ($row->nots ?? '').' '.(string) ($row->nots1 ?? '')), 250),
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('sales_invoices', $invoiceRows);

        $invoiceIds = collect($invoiceRows)->pluck('id')->flip();
        $lines = DB::connection($this->source)->table('mbe_s')->orderBy('id')->get();
        $lineRows = [];

        foreach ($lines as $row) {
            $invoiceId = (int) $row->mbe_id;
            $itemId = (int) $row->item_id;
            if (! $invoiceIds->has($invoiceId) || ! isset($this->itemIds[$itemId])) {
                $this->issue('sale_line_skipped', 'بند بيع بلا فاتورة أو صنف.', ['id' => $row->id, 'mbe_id' => $invoiceId, 'item_id' => $itemId]);

                continue;
            }

            $qty = $this->money($row->kmea ?? 0);
            $price = $this->money($row->price ?? 0);
            $cost = $this->money($row->p_tkl ?? 0);
            $lineRows[] = [
                'id' => (int) $row->id,
                'sales_invoice_id' => $invoiceId,
                'item_id' => $itemId,
                'barcode' => null,
                'qty_primary' => $qty,
                'qty_secondary' => 0,
                'unit_price_primary' => $price,
                'unit_price_secondary' => 0,
                'line_total' => round($qty * $price, 3),
                'profit' => round(($price - $cost) * $qty, 3),
                'sales_return_id' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('sales_invoice_lines', $lineRows);
        $this->log('Sales '.$this->count($invoiceRows).' / lines '.$this->count($lineRows));
    }

    protected function convertSalesReturns(): void
    {
        $now = now();
        $saleIds = DB::connection($this->target)->table('sales_invoices')->pluck('id')->flip();
        $headers = DB::connection($this->source)->table('rmb')->orderBy('id')->get();
        $returnRows = [];
        $headerInvoice = [];

        foreach ($headers as $row) {
            $linked = (int) ($row->num_sale ?? 0);
            if ($linked <= 0) {
                $linked = (int) ($row->num_surce ?? 0);
            }
            if ($linked <= 0) {
                $linked = (int) ($row->num_mas ?? 0);
            }
            if ($linked <= 0 || ! $saleIds->has($linked)) {
                $this->issue('return_unlinked', 'مرتجع بيع بلا رقم فاتورة أصل في المصدر — لم يُنقل. يحتاج مراجعة المسؤول.', [
                    'rmb_id' => $row->id,
                    'e_no' => $row->e_no ?? null,
                    'customer_id' => $row->sno_id ?? null,
                    'date' => $row->e_date ?? null,
                    'total' => $row->tottal ?? null,
                ]);

                continue;
            }

            $headerInvoice[(int) $row->id] = $linked;
        }

        $lines = DB::connection($this->source)->table('rmb_s')->orderBy('id')->get();
        foreach ($lines as $row) {
            $rmbId = (int) $row->rmb_id;
            if (! isset($headerInvoice[$rmbId])) {
                continue;
            }
            $itemId = (int) $row->item_id;
            if (! isset($this->itemIds[$itemId])) {
                continue;
            }

            $qty = $this->money($row->kmea ?? 0);
            $price = $this->money($row->price ?? 0);
            $header = $headers->firstWhere('id', $rmbId);

            $returnRows[] = [
                'id' => (int) $row->id,
                'sales_invoice_id' => $headerInvoice[$rmbId],
                'sales_invoice_line_id' => null,
                'item_id' => $itemId,
                'return_date' => $this->date($header->e_date ?? null),
                'qty_primary' => $qty,
                'qty_secondary' => 0,
                'unit_price_primary' => $price,
                'line_total' => round($qty * $price, 3),
                'notes' => $this->clip((string) ($header->nots1 ?? $header->nots ?? ''), 250),
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('sales_returns', $returnRows);
        $this->log('Sales returns '.$this->count($returnRows));
    }

    protected function convertTransfers(): void
    {
        if (! Schema::connection($this->target)->hasTable('warehouse_transfers')) {
            $this->log('Skipping transfers — table missing');

            return;
        }

        $now = now();
        $headers = DB::connection($this->source)->table('nkl')->orderBy('id')->get();
        $headerRows = [];

        foreach ($headers as $row) {
            $from = (int) $row->mak1;
            $to = (int) $row->mak2;
            if (! isset($this->warehouseIds[$from], $this->warehouseIds[$to])) {
                $this->issue('transfer_bad_warehouse', 'إذن نقل بمخزن غير موجود.', ['nkl_id' => $row->id, 'from' => $from, 'to' => $to]);

                continue;
            }

            $headerRows[] = [
                'id' => (int) $row->id,
                'transfer_date' => $this->date($row->e_date),
                'warehouse_from_id' => $from,
                'warehouse_to_id' => $to,
                'destination_purchase_invoice_id' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('warehouse_transfers', $headerRows);
        $headerIds = collect($headerRows)->pluck('id')->flip();

        $lines = DB::connection($this->source)->table('nkl_s')->orderBy('id')->get();
        $lineRows = [];
        foreach ($lines as $row) {
            $transferId = (int) $row->nkl_id;
            $itemId = (int) $row->item_id;
            if (! $headerIds->has($transferId) || ! isset($this->itemIds[$itemId])) {
                continue;
            }

            $lineRows[] = [
                'id' => (int) $row->id,
                'warehouse_transfer_id' => $transferId,
                'item_id' => $itemId,
                'qty_primary' => $this->money($row->kmea ?? 0),
                'qty_secondary' => 0,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertWithIdentity('warehouse_transfer_lines', $lineRows);
        $this->log('Transfers '.$this->count($headerRows).' / lines '.$this->count($lineRows));
    }

    protected function convertInstallments(): void
    {
        if (! Schema::connection($this->target)->hasTable('installment_contracts')) {
            $this->log('Skipping installments — table missing');

            return;
        }

        $this->clearInstallmentTables();
        $now = now();

        DB::connection((string) config('erp.central_connection', 'sqlsrv'))
            ->table('company_settings')
            ->where('company', $this->target)
            ->update([
                'link_sales_to_installments' => true,
                'installment_by_payroll_bank' => false,
            ]);

        $sourceCustomers = DB::connection($this->source)->table('cust')->orderBy('id')->get()->keyBy(fn ($row) => (int) $row->id);
        $payrollByName = [];
        $payrollRows = [];
        $bankRows = [];
        $classToBank = [];
        $nextPayroll = 1;
        $nextBank = 1;

        foreach ($sourceCustomers as $customer) {
            if (! $this->isInstallmentClass($customer->class ?? null)) {
                continue;
            }

            $class = trim((string) $customer->class);
            if (isset($classToBank[$class])) {
                continue;
            }

            $payrollName = $this->payrollNameFromClass($class);
            if (! isset($payrollByName[$payrollName])) {
                $payrollByName[$payrollName] = $nextPayroll++;
                $payrollRows[] = [
                    'id' => $payrollByName[$payrollName],
                    'name' => $payrollName,
                    'account_number' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $classToBank[$class] = [
                'payroll_bank_id' => $payrollByName[$payrollName],
                'installment_bank_id' => $nextBank,
            ];
            $bankRows[] = [
                'id' => $nextBank,
                'name' => $this->clip($class, 120) ?: $payrollName,
                'payroll_bank_id' => $payrollByName[$payrollName],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $nextBank++;
        }

        $this->insertWithIdentity('payroll_banks', $payrollRows);
        $this->insertWithIdentity('installment_banks', $bankRows);
        $this->insertWithIdentity('workplaces', [[
            'id' => 1,
            'name' => 'غير محدد',
            'created_at' => $now,
            'updated_at' => $now,
        ]]);

        $invoicesByCustomer = DB::connection($this->target)
            ->table('sales_invoices')
            ->where('payment_method_id', self::PAYMENT_INSTALLMENT)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => (int) $row->customer_id);

        $contracts = [];
        $contractIdsByCustomer = [];
        $contractTotals = [];

        foreach ($sourceCustomers as $customerId => $customer) {
            if (! $this->isInstallmentClass($customer->class ?? null)) {
                continue;
            }

            $class = trim((string) $customer->class);
            $bank = $classToBank[$class] ?? ['payroll_bank_id' => null, 'installment_bank_id' => null];
            $plan = $this->parseInstallmentPlan((string) ($customer->name ?? ''));
            $custInvoices = $invoicesByCustomer->get($customerId, collect());

            if ($custInvoices->isEmpty()) {
                if ($plan === null || ($plan['amount'] ?? 0) <= 0) {
                    $this->issue('installment_no_invoice', 'زبون أقساط بلا فاتورة وبلا خطة مبلغ*عدد — لم يُنشأ عقد.', [
                        'customer_id' => $customerId,
                        'name' => $customer->name ?? null,
                        'class' => $class,
                    ]);

                    continue;
                }

                $count = (int) ($plan['count'] ?? 0);
                if ($count < 1) {
                    $count = 1;
                }
                $contractId = 900000 + $customerId;
                $total = round($plan['amount'] * $count, 3);
                $contracts[] = $this->installmentContractRow(
                    $contractId,
                    $customerId,
                    $bank,
                    $now,
                    start: now()->toDateString(),
                    total: $total,
                    count: $count,
                    amount: $plan['amount'],
                    invoiceId: null,
                    notes: 'بدون فاتورة بيع — من اسم الزبون في المصدر',
                );
                $contractIdsByCustomer[$customerId][] = $contractId;
                $contractTotals[$contractId] = $total;
                $this->issue('installment_no_invoice_with_plan', 'عقد أقساط بلا فاتورة بيع — أُنشئ من خطة الاسم.', [
                    'customer_id' => $customerId,
                    'contract_id' => $contractId,
                    'name' => $customer->name ?? null,
                    'plan' => $plan['amount'].'*'.$count,
                ]);

                continue;
            }

            $invoiceTotal = round((float) $custInvoices->sum('grand_total'), 3);
            $planAgainstSum = $this->canonicalizeInstallmentPlan($plan, $invoiceTotal) ?? ['amount' => 0.0, 'count' => 0, 'swapped' => false];
            if ($plan && ($plan['count'] ?? 0) > 0) {
                $plannedTotal = round($plan['amount'] * $plan['count'], 3);
                if (abs($plannedTotal - $invoiceTotal) > 1) {
                    $this->issue('installment_plan_mismatch', 'مجموع الفواتير لا يطابق مبلغ*عدد في اسم الزبون.', [
                        'customer_id' => $customerId,
                        'contract_id' => (int) $custInvoices->first()->id,
                        'name' => $customer->name ?? null,
                        'plan' => $plan['amount'].'*'.$plan['count'],
                        'planned_total' => $plannedTotal,
                        'invoices_total' => $invoiceTotal,
                        'invoice_ids' => $custInvoices->pluck('id')->all(),
                    ]);
                }
            } elseif ($plan === null) {
                $this->issue('installment_no_plan', 'زبون أقساط بلا نمط مبلغ*عدد في الاسم — القسط استُنتج من الفاتورة.', [
                    'customer_id' => $customerId,
                    'name' => $customer->name ?? null,
                    'invoice_ids' => $custInvoices->pluck('id')->all(),
                ]);
            }

            foreach ($custInvoices as $invoice) {
                $invoiceId = (int) $invoice->id;
                $total = $this->money($invoice->grand_total ?? 0);
                $resolved = $this->canonicalizeInstallmentPlan($plan, $total) ?? ['amount' => 0.0, 'count' => 0, 'swapped' => false];
                $swapped = (bool) ($resolved['swapped'] || $planAgainstSum['swapped']);

                if ($resolved['swapped'] ?? false) {
                    $amount = (float) $resolved['amount'];
                    $count = (int) $resolved['count'];
                } elseif ($planAgainstSum['swapped'] ?? false) {
                    $amount = (float) $planAgainstSum['amount'];
                    $count = $amount > 0.001 ? max(1, (int) round($total / $amount)) : (int) $planAgainstSum['count'];
                } else {
                    $amount = (float) ($plan['amount'] ?? 0);
                    $count = (int) ($plan['count'] ?? 0);

                    if ($amount <= 0 && $total > 0) {
                        $amount = $total;
                        $count = 1;
                    } elseif ($custInvoices->count() > 1 || $count < 1) {
                        $count = $amount > 0.001 ? max(1, (int) round($total / $amount)) : 1;
                    }
                }

                if (! $swapped && ($count > 120 || $amount <= 0)) {
                    $this->issue('installment_illogical_schedule', 'جدول أقساط غير منطقي ويحتاج مراجعة المسؤول.', [
                        'customer_id' => $customerId,
                        'contract_id' => $invoiceId,
                        'invoice_id' => $invoiceId,
                        'name' => $customer->name ?? null,
                        'amount' => $amount,
                        'count' => $count,
                        'invoice_total' => $total,
                    ]);
                }

                $contracts[] = $this->installmentContractRow(
                    $invoiceId,
                    $customerId,
                    $bank,
                    $now,
                    start: $this->date($invoice->invoice_date ?? null),
                    total: $total,
                    count: $count,
                    amount: $amount,
                    invoiceId: $invoiceId,
                    notes: $this->clip((string) ($customer->name ?? ''), 180),
                );
                $contractIdsByCustomer[$customerId][] = $invoiceId;
                $contractTotals[$invoiceId] = $total;
            }
        }

        $this->insertRows('installment_contracts', $contracts);

        $installmentCustomerIds = array_keys($contractIdsByCustomer);
        $journal = $installmentCustomerIds === []
            ? collect()
            : DB::connection($this->source)
                ->table('moaamla_s2')
                ->whereIn('cust_id1', $installmentCustomerIds)
                ->where('kema1', '>', 0)
                ->orderBy('datee')
                ->orderBy('id')
                ->get();

        $paidByContract = [];
        $sequenceByContract = [];
        $deductionRows = [];
        $surplusRows = [];
        $deductionId = 1;

        foreach ($journal as $entry) {
            $customerId = (int) $entry->cust_id1;
            $contractIds = $contractIdsByCustomer[$customerId] ?? [];
            if ($contractIds === []) {
                $this->issue('installment_orphan_payment', 'قيد سداد لأقساط بلا عقد.', [
                    'journal_id' => $entry->id,
                    'customer_id' => $customerId,
                    'amount' => $entry->kema1 ?? null,
                ]);

                continue;
            }

            $left = $this->money($entry->kema1 ?? 0);
            $destination = (string) ($entry->name2 ?? '');
            $type = str_contains($destination, 'خزين')
                ? InstallmentDeductionType::Cash->value
                : InstallmentDeductionType::Bank->value;
            $dueDate = Carbon::parse($this->date($entry->datee ?? null))->day(28)->toDateString();
            $note = $this->clip(trim($destination.' '.(string) ($entry->index_note ?? '')), 180);
            $lastDeductionId = null;
            $hostContractId = (int) end($contractIds);

            foreach ($contractIds as $contractId) {
                if ($left <= 0.001) {
                    break;
                }

                $paid = $paidByContract[$contractId] ?? 0.0;
                $room = round($contractTotals[$contractId] - $paid, 3);
                $take = min($left, max($room, 0));
                if ($take <= 0.001) {
                    continue;
                }

                $sequenceByContract[$contractId] = ($sequenceByContract[$contractId] ?? 0) + 1;
                $paidByContract[$contractId] = round($paid + $take, 3);
                $left = round($left - $take, 3);
                $lastDeductionId = $deductionId;
                $hostContractId = $contractId;

                $deductionRows[] = [
                    'id' => $deductionId++,
                    'installment_contract_id' => $contractId,
                    'sequence' => $sequenceByContract[$contractId],
                    'deducted_amount' => $take,
                    'deduction_date' => $this->date($entry->datee ?? null),
                    'installment_due_date' => $dueDate,
                    'deduction_type_id' => $type,
                    'notes' => $note,
                    'batch_id' => null,
                    'surplus_id' => null,
                    'remaining_balance' => round($contractTotals[$contractId] - $paidByContract[$contractId], 3),
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($left > 0.001) {
                $surplusRows[] = [
                    'contractable_type' => 'installment_contract',
                    'contractable_id' => $hostContractId,
                    'surplus_date' => $this->date($entry->datee ?? null),
                    'amount' => $left,
                    'status' => 1,
                    'suspended_id' => null,
                    'batch_id' => null,
                    'deduction_id' => $lastDeductionId,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertWithIdentity('installment_deductions', $deductionRows);
        $this->insertChunks('installment_surplus', $surplusRows);
        $this->log('Surplus rows '.$this->count($surplusRows));

        $recalculated = app(InstallmentContractMetricsService::class)->recalculateAll(null, $this->target);
        $this->log('Installment contracts '.$this->count($contracts).' / deductions '.$this->count($deductionRows).' / surplus '.$this->count($surplusRows).' / metrics '.$recalculated);
    }

    /**
     * @param  array{payroll_bank_id: ?int, installment_bank_id: ?int}  $bank
     */
    protected function installmentContractRow(
        int $id,
        int $customerId,
        array $bank,
        mixed $now,
        string $start,
        float $total,
        int $count,
        float $amount,
        ?int $invoiceId,
        ?string $notes,
    ): array {
        return [
            'id' => $id,
            'customer_id' => $customerId,
            'installment_bank_id' => $bank['installment_bank_id'],
            'workplace_id' => 1,
            'payroll_bank_id' => $bank['payroll_bank_id'],
            'bank_account_number' => null,
            'contract_start' => $start,
            'contract_end' => null,
            'contract_total' => $total,
            'installment_count' => $count,
            'installment_amount' => $amount,
            'total_paid' => 0,
            'balance' => $total,
            'sales_invoice_id' => $invoiceId,
            'cheques_in' => 0,
            'cheques_out' => 0,
            'last_deduction_month' => null,
            'next_installment_date' => null,
            'late_amount' => 0,
            'installments_remaining' => $count,
            'surplus_count' => 0,
            'surplus_amount' => 0,
            'suspended_count' => 0,
            'suspended_amount' => 0,
            'notes' => $notes,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function payrollNameFromClass(string $class): string
    {
        if (str_contains($class, 'وحد')) {
            return 'مصرف الوحدة';
        }
        if (str_contains($class, 'اهل') || str_contains($class, 'أهل')) {
            return 'مصرف الأهلي';
        }
        if (str_contains($class, 'تجار')) {
            return 'مصرف التجاري';
        }

        return $this->clip($class, 80) ?: 'مصرف أقساط';
    }

    protected function clearInstallmentTables(): void
    {
        $tables = [
            'installment_deductions', 'installment_deduction_archives',
            'installment_surplus', 'installment_suspended', 'installment_cheques',
            'installment_stops', 'deduction_batch_lines', 'deduction_batches',
            'installment_contracts', 'installment_contract_archives',
            'installment_banks', 'workplaces', 'payroll_banks',
        ];

        foreach ($tables as $table) {
            if (Schema::connection($this->target)->hasTable($table)) {
                DB::connection($this->target)->table($table)->delete();
            }
        }
    }

    protected function clearConvertedTables(): void
    {
        $tables = [
            'warehouse_transfer_layers', 'warehouse_transfer_lines', 'warehouse_transfers',
            'installment_deductions', 'installment_deduction_archives',
            'installment_surplus', 'installment_suspended', 'installment_cheques',
            'installment_stops', 'deduction_batch_lines', 'deduction_batches',
            'installment_contracts', 'installment_contract_archives',
            'installment_banks', 'workplaces', 'payroll_banks',
            'sales_invoice_lines', 'sales_returns', 'sales_invoices',
            'purchase_invoice_lines', 'purchase_returns', 'purchase_invoices',
            'warehouse_stocks', 'item_prices', 'item_barcodes', 'items',
            'cash_boxes', 'bank_accounts', 'suppliers', 'customers', 'customer_types',
            'warehouses', 'brands', 'item_types', 'units', 'payment_methods',
        ];

        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"');
        foreach ($tables as $table) {
            if (Schema::connection($this->target)->hasTable($table)) {
                DB::connection($this->target)->table($table)->delete();
            }
        }
        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? WITH NOCHECK CHECK CONSTRAINT ALL"');
    }

    protected function disableForeignKeys(): void
    {
        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"');
    }

    protected function enableForeignKeys(): void
    {
        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? WITH NOCHECK CHECK CONSTRAINT ALL"');
    }

    protected function isInstallmentClass(?string $class): bool
    {
        if ($class === null || $class === '') {
            return false;
        }

        return str_contains($class, 'قساط')
            || str_contains($class, 'قسط')
            || str_contains($class, 'تقسيط');
    }

    /**
     * @return array{amount: float, count: int}|null
     */
    protected function parseInstallmentPlan(string $name): ?array
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*\*\s*(\d+)/u', $name, $match)) {
            return [
                'amount' => (float) $match[1],
                'count' => (int) $match[2],
            ];
        }

        if (preg_match('/القسط\s+(\d+(?:\.\d+)?)/u', $name, $match)) {
            return [
                'amount' => (float) $match[1],
                'count' => 0,
            ];
        }

        return null;
    }

    /**
     * If name is clearly "count * installment" and the product equals the invoice total,
     * swap to amount * count so the schedule is usable without a manager review.
     *
     * @param  array{amount: float, count: int}|null  $plan
     * @return array{amount: float, count: int, swapped: bool}|null
     */
    protected function canonicalizeInstallmentPlan(?array $plan, float $referenceTotal): ?array
    {
        if ($plan === null) {
            return null;
        }

        $amount = (float) ($plan['amount'] ?? 0);
        $count = (int) ($plan['count'] ?? 0);
        $swapped = false;

        if ($amount > 0 && $count > 0 && $referenceTotal > 0.001) {
            $product = round($amount * $count, 3);
            if (abs($product - $referenceTotal) <= 1 && $count > 120 && $amount <= 120) {
                $swapped = true;
                [$amount, $count] = [(float) $count, (int) $amount];
            }
        }

        return [
            'amount' => $amount,
            'count' => $count,
            'swapped' => $swapped,
        ];
    }

    protected function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return now()->toDateString();
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    protected function money(mixed $value): float
    {
        return round((float) $value, 3);
    }

    protected function clip(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function issue(string $code, string $message, array $context): void
    {
        if (count($this->issues) >= 2000) {
            return;
        }

        $this->issues[] = compact('code', 'message', 'context');
    }

    protected function writeIssues(): void
    {
        $path = storage_path('app/almedad-conversion-issues.json');
        file_put_contents($path, json_encode([
            'source' => $this->source,
            'target' => $this->target,
            'generated_at' => now()->toDateTimeString(),
            'count' => count($this->issues),
            'issues' => $this->issues,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->persistIssuesToTable();

        $this->log('Issues '.count($this->issues).' → '.$path);
    }

    protected function persistIssuesToTable(): void
    {
        if (! Schema::connection($this->target)->hasTable('conversion_review_issues')) {
            return;
        }

        $now = now();
        DB::connection($this->target)->table('conversion_review_issues')
            ->where('issue_code', 'like', 'installment_%')
            ->delete();

        $rows = [];
        foreach ($this->issues as $issue) {
            $context = $issue['context'] ?? [];
            $code = (string) ($issue['code'] ?? 'unknown');
            $rows[] = [
                'issue_code' => $code,
                'severity' => \App\Models\ConversionReviewIssue::severityFor($code),
                'message' => $this->clip((string) ($issue['message'] ?? ''), 250) ?? $code,
                'customer_id' => isset($context['customer_id']) ? (int) $context['customer_id'] : null,
                'contract_id' => isset($context['contract_id']) ? (int) $context['contract_id'] : ($context['invoice_id'] ?? null),
                'invoice_id' => isset($context['invoice_id']) ? (int) $context['invoice_id'] : null,
                'journal_id' => isset($context['journal_id']) ? (int) $context['journal_id'] : ($context['rmb_id'] ?? $context['nkl_id'] ?? null),
                'party_name' => $this->clip((string) ($context['name'] ?? ''), 180),
                'details' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'status' => 'open',
                'manager_notes' => null,
                'resolved_by' => null,
                'resolved_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunks('conversion_review_issues', $rows);
        $this->log('Review table installment rows '.$this->count($rows));
    }

    protected function count(array $rows): int
    {
        return count($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function insertChunks(string $table, array $rows, int $size = 100): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, $size) as $chunk) {
            DB::connection($this->target)->table($table)->insert($chunk);
        }
    }

    protected function sourceQuery(string $table): Collection
    {
        return DB::connection($this->source)->table($table)->orderBy('id')->get();
    }
}
