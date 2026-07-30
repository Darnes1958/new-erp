<?php

namespace App\Services\Conversion;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompanyDataConverter
{
    protected string $source;

    protected string $target;

    /** @var array<string, callable> */
    protected array $steps = [];

    public function __construct(
        string $source = 'Motafoek',
        string $target = 'testERP',
    ) {
        $this->source = $source;
        $this->target = $target;

        $this->steps = [
            'payment_methods' => fn () => $this->convertPaymentMethods(),
            'master' => fn () => $this->convertMasterData(),
            'items' => fn () => $this->convertItems(),
            'purchases' => fn () => $this->convertPurchases(),
            'sales' => fn () => $this->convertSales(),
            'fifo' => fn () => $this->convertFifo(),
            'payments' => fn () => $this->convertPayments(),
            'installments' => fn () => $this->convertInstallments(),
        ];
    }

    public function availableSteps(): array
    {
        return array_keys($this->steps);
    }

    public function convert(bool $fresh = false, ?array $only = null): void
    {
        $this->assertConnections();

        if ($fresh) {
            $this->clearTarget();
        }

        $steps = $only
            ? array_intersect_key($this->steps, array_flip($only))
            : $this->steps;

        foreach ($steps as $name => $step) {
            $this->log("Converting: {$name}");
            DB::connection($this->target)->transaction($step);
            $this->log("Done: {$name}");
        }
    }

    protected function assertConnections(): void
    {
        foreach ([$this->source, $this->target] as $connection) {
            if (! config("database.connections.{$connection}")) {
                throw new RuntimeException("Database connection [{$connection}] is not configured.");
            }

            DB::connection($connection)->getPdo();
        }
    }

    protected function clearTarget(): void
    {
        $this->log('Clearing target database...');

        $tables = [
            'wrong_deductions', 'deduction_batch_lines', 'deduction_batches',
            'installment_suspended', 'installment_cheques', 'installment_stops_without_contract',
            'installment_stops', 'installment_surplus_archives', 'installment_surplus',
            'installment_deduction_archives', 'installment_deductions',
            'installment_contract_archives', 'installment_contracts',
            'workplaces', 'installment_banks', 'payroll_banks',
            'sales_quotation_lines', 'sales_quotations', 'fund_transfers',
            'supplier_payments', 'customer_receipts', 'stock_movements',
            'fifo_allocations', 'purchase_invoice_lines', 'purchase_returns',
            'purchase_invoices', 'sales_invoice_lines', 'sales_returns', 'sales_invoices',
            'warehouse_stocks', 'item_prices', 'item_barcodes', 'items',
            'bank_accounts', 'cash_boxes', 'suppliers', 'customers',
            'customer_types', 'warehouses', 'brands', 'item_types', 'units',
        ];

        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT ALL"');

        foreach ($tables as $table) {
            DB::connection($this->target)->table($table)->delete();
        }

        DB::connection($this->target)->statement('EXEC sp_MSforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT ALL"');

        $this->reseedPaymentMethods();
    }

    protected function reseedPaymentMethods(): void
    {
        DB::connection($this->target)->table('payment_methods')->delete();
    }

    protected function convertPaymentMethods(): void
    {
        $codes = [
            1 => 'cash',
            2 => 'bank',
            3 => 'installment',
            4 => 'discount',
        ];

        $rows = $this->sourceQuery('price_types')
            ->map(function ($row) use ($codes) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'code' => $codes[(int) $row->id] ?? 'other_'.$row->id,
                    'rate' => $row->rate ?? 0,
                    'adjustment_value' => $row->val ?? 0,
                    'adjustment_direction' => (int) ($row->inc_dec ?? 0),
                    'is_active' => true,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            })
            ->all();

        $this->insertWithIdentity('payment_methods', $rows);
    }

    protected function convertMasterData(): void
    {
        $this->insertWithIdentity('units', $this->sourceQuery('unitas')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'abbreviation' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        foreach ($this->sourceQuery('unitbs') as $row) {
            $exists = DB::connection($this->target)->table('units')->where('id', $row->id)->exists();
            if (! $exists) {
                $this->insertWithIdentity('units', [[
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'abbreviation' => null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]]);
            }
        }

        $this->insertWithIdentity('item_types', $this->mapSimple('item_types'));
        $this->insertWithIdentity('brands', $this->sourceQuery('companies')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('warehouses', $this->sourceQuery('places')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'warehouse_type' => (int) ($row->place_type ?? 1),
            'is_active' => true,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('customer_types', $this->mapSimple('customer_types'));

        $this->insertWithIdentity('customers', $this->sourceQuery('customers')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'address' => $row->address ?? null,
            'mdar' => $row->mdar ?? null,
            'libyana' => $row->libyana ?? null,
            'card_no' => $row->card_no ?? null,
            'others' => $row->others ?? null,
            'customer_type_id' => $row->customer_type_id ?? null,
            'created_by' => $row->user_id ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('suppliers', $this->sourceQuery('suppliers')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'address' => $row->address ?? null,
            'mdar' => $row->mdar ?? null,
            'libyana' => $row->libyana ?? null,
            'card_no' => null,
            'others' => null,
            'created_by' => $row->user_id ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('cash_boxes', $this->sourceQuery('kazenas')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'opening_balance' => $row->balance ?? 0,
            'assigned_user_id' => $row->user_id,
            'is_active' => true,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('bank_accounts', $this->sourceQuery('accs')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'account_number' => $row->acc,
            'opening_balance' => $row->raseed ?? 0,
            'is_active' => true,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());
    }

    protected function convertItems(): void
    {
        $this->insertWithIdentity('items', $this->sourceQuery('items')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'barcode' => $row->barcode,
            'item_type_id' => $row->item_type_id,
            'brand_id' => $row->company_id,
            'primary_unit_id' => $row->unita_id,
            'secondary_unit_id' => $row->unitb_id,
            'has_dual_unit' => (bool) ((int) ($row->two_unit ?? 0)),
            'conversion_factor' => $row->count ?? 1,
            'default_buy_price' => $row->price_buy ?? 0,
            'is_active' => true,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('item_barcodes', $this->sourceQuery('barcodes')->map(function ($row) {
            $item = DB::connection($this->source)->table('items')->where('id', $row->item_id)->first();

            return [
                'id' => (int) $row->id,
                'item_id' => (int) $row->item_id,
                'barcode' => (string) ($item->barcode ?? $row->item_id),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        })->all());

        $buyPrices = $this->sourceQuery('price_buys')->map(fn ($row) => [
            'item_id' => (int) $row->item_id,
            'payment_method_id' => (int) $row->price_type_id,
            'price_kind' => 'buy',
            'price_primary' => $row->price1 ?? 0,
            'price_secondary' => $row->price2 ?? 0,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();

        $sellPrices = $this->sourceQuery('price_sells')->map(fn ($row) => [
            'item_id' => (int) $row->item_id,
            'payment_method_id' => (int) $row->price_type_id,
            'price_kind' => 'sell',
            'price_primary' => $row->price1 ?? 0,
            'price_secondary' => $row->price2 ?? 0,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();

        $this->insertRows('item_prices', $buyPrices);
        $this->insertRows('item_prices', $sellPrices);

        $this->insertWithIdentity('warehouse_stocks', $this->sourceQuery('place_stocks')->map(fn ($row) => [
            'id' => (int) $row->id,
            'warehouse_id' => (int) $row->place_id,
            'item_id' => (int) $row->item_id,
            'quantity_primary' => $row->stock1 ?? 0,
            'quantity_secondary' => $row->stock2 ?? 0,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());
    }

    protected function convertPurchases(): void
    {
        $this->insertWithIdentity('purchase_returns', $this->sourceQuery('tar_buys')->map(fn ($row) => [
            'id' => (int) $row->id,
            'purchase_invoice_id' => (int) $row->buy_id,
            'item_id' => (int) $row->item_id,
            'return_date' => $row->created_at,
            'notes' => null,
            'created_by' => $row->user_id ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('purchase_invoices', $this->sourceQuery('buys')->map(fn ($row) => [
            'id' => (int) $row->id,
            'invoice_date' => $row->order_date,
            'supplier_id' => $row->supplier_id,
            'payment_method_id' => (int) $row->price_type_id,
            'warehouse_id' => (int) $row->place_id,
            'lines_subtotal' => $row->tot ?? 0,
            'amount_paid' => $row->pay ?? 0,
            'balance' => $row->baky ?? 0,
            'notes' => $row->notes,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('purchase_invoice_lines', $this->sourceQuery('buy_trans')->map(fn ($row) => [
            'id' => (int) $row->id,
            'purchase_invoice_id' => (int) $row->buy_id,
            'item_id' => (int) $row->item_id,
            'barcode' => $row->barcode_id,
            'qty_primary' => $row->q1 ?? 0,
            'qty_secondary' => $row->q2 ?? 0,
            'unit_cost_primary' => $row->price_input ?? 0,
            'line_cost_total' => $row->sub_input ?? 0,
            'remaining_qty_primary' => $row->qs1 ?? 0,
            'remaining_qty_secondary' => $row->qs2 ?? 0,
            'purchase_return_id' => $row->tar_buy_id,
            'expiry_date' => $row->exp_date ?? null,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());
    }

    protected function convertSales(): void
    {
        $this->insertWithIdentity('sales_returns', $this->sourceQuery('tar_sells')->map(fn ($row) => [
            'id' => (int) $row->id,
            'sales_invoice_id' => (int) $row->sell_id,
            'item_id' => (int) $row->item_id,
            'return_date' => $row->created_at,
            'notes' => null,
            'created_by' => $row->user_id ?? null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('sales_invoices', $this->sourceQuery('sells')->map(fn ($row) => [
            'id' => (int) $row->id,
            'invoice_date' => $row->order_date,
            'customer_id' => $row->customer_id,
            'payment_method_id' => (int) $row->price_type_id,
            'warehouse_id' => (int) $row->place_id,
            'is_retail' => (bool) ((int) ($row->single ?? 1)),
            'lines_subtotal' => $row->tot ?? 0,
            'extra_cost' => $row->cost ?? 0,
            'rate_markup' => $row->rate ?? 0,
            'difference_amount' => $row->differ ?? 0,
            'discount' => $row->ksm ?? 0,
            'grand_total' => $row->total ?? 0,
            'amount_paid' => $row->pay ?? 0,
            'balance' => $row->baky ?? 0,
            'deferred_amount' => $row->pay_after ?? 0,
            'refund_amount' => $row->morajeh ?? 0,
            'unpaid_date' => $row->not_pay_date,
            'notes' => $row->notes,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('sales_invoice_lines', $this->sourceQuery('sell_trans')->map(fn ($row) => [
            'id' => (int) $row->id,
            'sales_invoice_id' => (int) $row->sell_id,
            'item_id' => (int) $row->item_id,
            'barcode' => $row->barcode_id,
            'qty_primary' => $row->q1 ?? 0,
            'qty_secondary' => $row->q2 ?? 0,
            'unit_price_primary' => $row->price1 ?? 0,
            'unit_price_secondary' => $row->price2 ?? 0,
            'line_total' => $row->sub_tot ?? 0,
            'profit' => $row->profit ?? 0,
            'sales_return_id' => $row->tar_sell_id,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());
    }

    protected function convertFifo(): void
    {
        $this->insertWithIdentity('fifo_allocations', $this->sourceQuery('buy_sells')->map(fn ($row) => [
            'id' => (int) $row->id,
            'purchase_invoice_id' => (int) $row->buy_id,
            'sales_invoice_id' => (int) $row->sell_id,
            'sales_invoice_line_id' => (int) $row->sell_tran_id,
            'item_id' => (int) $row->item_id,
            'qty_primary' => $row->q1 ?? 0,
            'qty_secondary' => $row->q2 ?? 0,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());
    }

    protected function convertPayments(): void
    {
        $this->insertWithIdentity('customer_receipts', $this->sourceQuery('receipts')->map(fn ($row) => [
            'id' => (int) $row->id,
            'receipt_date' => $row->receipt_date,
            'customer_id' => (int) $row->customer_id,
            'sales_invoice_id' => $row->sell_id,
            'payment_method_id' => (int) $row->price_type_id,
            'transaction_kind' => (int) $row->rec_who,
            'flow_direction' => (int) ($row->imp_exp ?? 1),
            'amount' => $row->val ?? 0,
            'notes' => $row->notes,
            'sequence_no' => $row->index,
            'cash_box_id' => $row->kazena_id,
            'bank_account_id' => $row->acc_id,
            'warehouse_id' => $row->place_id,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('supplier_payments', $this->sourceQuery('recsupps')->map(fn ($row) => [
            'id' => (int) $row->id,
            'payment_date' => $row->receipt_date,
            'supplier_id' => (int) $row->supplier_id,
            'purchase_invoice_id' => $row->buy_id,
            'payment_method_id' => (int) $row->price_type_id,
            'transaction_kind' => (int) $row->rec_who,
            'flow_direction' => (int) ($row->imp_exp ?? 1),
            'amount' => $row->val ?? 0,
            'notes' => $row->notes,
            'sequence_no' => $row->index,
            'cash_box_id' => $row->kazena_id,
            'bank_account_id' => $row->acc_id,
            'warehouse_id' => $row->place_id,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('fund_transfers', $this->sourceQuery('money')->map(fn ($row) => [
            'id' => (int) $row->id,
            'transfer_date' => $row->tran_date,
            'transfer_kind' => (int) $row->rec_who,
            'from_cash_box_id' => $row->kazena_id,
            'to_cash_box_id' => $row->kazena2_id,
            'from_bank_account_id' => $row->acc_id,
            'to_bank_account_id' => $row->acc2_id,
            'payment_method_id' => $row->price_type_id,
            'amount' => $row->amount ?? 0,
            'notes' => $row->notes,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());
    }

    protected function convertInstallments(): void
    {
        $this->insertWithIdentity('payroll_banks', $this->sourceQuery('tajs')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->TajName,
            'account_number' => $row->TajAcc,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_banks', $this->sourceQuery('banks')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->BankName,
            'payroll_bank_id' => $row->taj_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('workplaces', $this->sourceQuery('jobs')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertRows('installment_contracts', $this->sourceQuery('mains')->map(fn ($row) => [
            'id' => (int) $row->id,
            'customer_id' => (int) $row->customer_id,
            'installment_bank_id' => $row->bank_id,
            'workplace_id' => $row->job_id,
            'payroll_bank_id' => $row->taj_id,
            'bank_account_number' => $row->acc,
            'contract_start' => $row->sul_begin,
            'contract_end' => $row->sul_end,
            'contract_total' => $row->sul ?? 0,
            'installment_count' => (int) ($row->kst_count ?? 0),
            'installment_amount' => $row->kst ?? 0,
            'total_paid' => $row->pay ?? 0,
            'balance' => $row->raseed ?? 0,
            'sales_invoice_id' => $row->sell_id,
            'cheques_in' => (int) ($row->chk_in ?? 0),
            'cheques_out' => (int) ($row->chk_out ?? 0),
            'last_deduction_month' => $row->LastKsm,
            'next_installment_date' => $row->NextKst,
            'late_amount' => $row->Late ?? 0,
            'installments_remaining' => (int) ($row->kst_baky ?? 0),
            'surplus_count' => (int) ($row->over_count ?? 0),
            'surplus_amount' => $row->over_kst ?? 0,
            'suspended_count' => (int) ($row->tar_count ?? 0),
            'suspended_amount' => $row->tar_kst ?? 0,
            'notes' => $row->notes,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertRows('installment_contract_archives', $this->sourceQuery('main_arcs')->map(fn ($row) => [
            'id' => (int) $row->id,
            'customer_id' => (int) $row->customer_id,
            'installment_bank_id' => $row->bank_id,
            'workplace_id' => $row->job_id,
            'payroll_bank_id' => $row->taj_id,
            'bank_account_number' => $row->acc,
            'contract_start' => $row->sul_begin,
            'contract_end' => $row->sul_end,
            'contract_total' => $row->sul ?? 0,
            'installment_count' => (int) ($row->kst_count ?? 0),
            'installment_amount' => $row->kst ?? 0,
            'total_paid' => $row->pay ?? 0,
            'balance' => $row->raseed ?? 0,
            'sales_invoice_id' => $row->sell_id,
            'cheques_in' => (int) ($row->chk_in ?? 0),
            'cheques_out' => (int) ($row->chk_out ?? 0),
            'archived_at' => $row->updated_at,
            'notes' => $row->notes,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_deductions', $this->sourceQuery('trans')->map(fn ($row) => [
            'id' => (int) $row->id,
            'installment_contract_id' => (int) $row->main_id,
            'sequence' => (int) ($row->ser ?? 0),
            'deducted_amount' => $row->ksm ?? 0,
            'deduction_date' => $row->ksm_date,
            'installment_due_date' => $row->kst_date,
            'deduction_type_id' => (int) ($row->ksm_type_id ?? 0),
            'notes' => $row->ksm_notes,
            'batch_id' => $row->haf_id,
            'surplus_id' => $row->over_id,
            'remaining_balance' => $row->baky ?? 0,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_deduction_archives', $this->sourceQuery('trans_arcs')->map(fn ($row) => [
            'id' => (int) $row->id,
            'installment_contract_id' => (int) $row->main_id,
            'sequence' => (int) ($row->ser ?? 0),
            'deducted_amount' => $row->ksm ?? 0,
            'deduction_date' => $row->ksm_date,
            'installment_due_date' => $row->kst_date,
            'deduction_type_id' => (int) ($row->ksm_type_id ?? 0),
            'notes' => $row->ksm_notes,
            'batch_id' => $row->haf_id,
            'remaining_balance' => $row->baky ?? 0,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_surplus', $this->sourceQuery('overksts')->map(fn ($row) => [
            'id' => (int) $row->id,
            'contractable_type' => $this->mapMorphType($row->overkstable_type),
            'contractable_id' => (int) $row->overkstable_id,
            'surplus_date' => $row->over_date,
            'amount' => $row->kst ?? 0,
            'status' => (int) ($row->status ?? 0),
            'suspended_id' => $row->tar_id,
            'batch_id' => $row->haf_id,
            'deduction_id' => $row->tran_id,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_surplus_archives', $this->sourceQuery('overkst_arcs')->map(fn ($row) => [
            'id' => (int) $row->id,
            'installment_contract_id' => (int) $row->main_id,
            'surplus_date' => $row->over_date,
            'amount' => $row->kst ?? 0,
            'status' => (int) ($row->status ?? 0),
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_stops', $this->sourceQuery('stops')->map(fn ($row) => [
            'id' => (int) $row->id,
            'installment_contract_id' => (int) $row->main_id,
            'stop_date' => $row->stop_date,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_stops_without_contract', $this->sourceQuery('StopsWithoutMains')->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'account_number' => $row->acc,
            'stop_date' => $row->stop_date,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_cheques', $this->sourceQuery('chks')->map(fn ($row) => [
            'id' => (int) $row->id,
            'installment_contract_id' => (int) $row->main_id,
            'cheque_count' => (int) ($row->chks_count ?? 0),
            'cheque_date' => $row->date,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('installment_suspended', $this->sourceQuery('tarksts')->map(fn ($row) => [
            'id' => (int) $row->id,
            'contractable_type' => $this->mapMorphType($row->tarkstable_type),
            'contractable_id' => (int) $row->tarkstable_id,
            'suspended_date' => $row->tar_date ?? null,
            'amount' => $row->kst ?? 0,
            'status' => (int) ($row->tar_type ?? 0),
            'batch_id' => $row->haf_id,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('deduction_batches', $this->sourceQuery('hafithas')->map(fn ($row) => [
            'id' => (int) $row->id,
            'batch_date' => $row->from_date ?? null,
            'notes' => null,
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('deduction_batch_lines', $this->sourceQuery('hafitha_trans')->map(fn ($row) => [
            'id' => (int) $row->id,
            'deduction_batch_id' => (int) $row->hafitha_id,
            'contractable_type' => $this->mapMorphType($row->hafithaable_type),
            'contractable_id' => (int) $row->hafithaable_id,
            'amount' => $row->ksm ?? 0,
            'notes' => $row->ksm_notes,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());

        $this->insertWithIdentity('wrong_deductions', $this->sourceQuery('wrongksts')->map(fn ($row) => [
            'id' => (int) $row->id,
            'payroll_bank_id' => $row->taj_id,
            'account_number' => $row->acc,
            'name' => $row->name,
            'amount' => $row->kst ?? 0,
            'status' => (int) ($row->status ?? 0),
            'created_by' => $row->user_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all());
    }

    protected function mapMorphType(?string $type): string
    {
        return match ($type) {
            'App\Models\Main' => 'installment_contract',
            'App\Models\Main_arc' => 'installment_contract_archive',
            'App\Models\Wrongkst' => 'wrong_deduction',
            default => (string) $type,
        };
    }

    protected function mapSimple(string $table): array
    {
        return $this->sourceQuery($table)->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();
    }

    protected function sourceQuery(string $table): Collection
    {
        return DB::connection($this->source)->table($table)->orderBy('id')->get();
    }

    protected function insertRows(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            DB::connection($this->target)->table($table)->insert($row);
        }
    }

    protected function insertWithIdentity(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::connection($this->target)->transaction(function () use ($table, $chunk): void {
                DB::connection($this->target)->unprepared("SET IDENTITY_INSERT [{$table}] ON");

                foreach ($chunk as $row) {
                    DB::connection($this->target)->table($table)->insert($row);
                }

                DB::connection($this->target)->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            });
        }
    }

    protected function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
