<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->create('sales_invoices', function (Blueprint $table) {
                $table->id();
                $table->date('invoice_date');
                $table->foreignId('customer_id')->nullable()->constrained('customers')->noActionOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->noActionOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->noActionOnDelete();
                $table->boolean('is_retail')->default(true);
                $table->decimal('lines_subtotal', 14, 3)->default(0);
                $table->decimal('extra_cost', 14, 3)->default(0);
                $table->decimal('rate_markup', 14, 3)->default(0);
                $table->decimal('difference_amount', 14, 3)->default(0);
                $table->decimal('discount', 14, 3)->default(0);
                $table->decimal('grand_total', 14, 3)->default(0);
                $table->decimal('amount_paid', 14, 3)->default(0);
                $table->decimal('balance', 14, 3)->default(0);
                $table->decimal('deferred_amount', 14, 3)->default(0);
                $table->decimal('refund_amount', 14, 3)->default(0);
                $table->date('unpaid_date')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['invoice_date', 'customer_id']);
            });

            Schema::connection($connection)->create('sales_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->date('return_date')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('sales_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->string('barcode')->nullable();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('qty_secondary', 14, 3)->default(0);
                $table->decimal('unit_price_primary', 14, 3)->default(0);
                $table->decimal('unit_price_secondary', 14, 3)->default(0);
                $table->decimal('line_total', 14, 3)->default(0);
                $table->decimal('profit', 14, 3)->default(0);
                $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->noActionOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('purchase_invoices', function (Blueprint $table) {
                $table->id();
                $table->date('invoice_date');
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->noActionOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->noActionOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->noActionOnDelete();
                $table->decimal('lines_subtotal', 14, 3)->default(0);
                $table->decimal('amount_paid', 14, 3)->default(0);
                $table->decimal('balance', 14, 3)->default(0);
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['invoice_date', 'supplier_id']);
            });

            Schema::connection($connection)->create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->date('return_date')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('purchase_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->string('barcode')->nullable();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('qty_secondary', 14, 3)->default(0);
                $table->decimal('unit_cost_primary', 14, 3)->default(0);
                $table->decimal('line_cost_total', 14, 3)->default(0);
                $table->decimal('remaining_qty_primary', 14, 3)->default(0);
                $table->decimal('remaining_qty_secondary', 14, 3)->default(0);
                $table->foreignId('purchase_return_id')->nullable()->constrained('purchase_returns')->noActionOnDelete();
                $table->date('expiry_date')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('fifo_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->noActionOnDelete();
                $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->noActionOnDelete();
                $table->foreignId('sales_invoice_line_id')->constrained('sales_invoice_lines')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('qty_secondary', 14, 3)->default(0);
                $table->timestamps();

                $table->index(['sales_invoice_line_id', 'item_id']);
            });

            Schema::connection($connection)->create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('warehouses')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->string('movement_type', 32);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('qty_secondary', 14, 3)->default(0);
                $table->decimal('unit_cost', 14, 3)->nullable();
                $table->string('notes')->nullable();
                $table->dateTime('movement_date');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['reference_type', 'reference_id']);
                $table->index(['warehouse_id', 'item_id', 'movement_date']);
            });

            Schema::connection($connection)->create('customer_receipts', function (Blueprint $table) {
                $table->id();
                $table->date('receipt_date');
                $table->foreignId('customer_id')->constrained('customers')->noActionOnDelete();
                $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->noActionOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->noActionOnDelete();
                $table->unsignedTinyInteger('transaction_kind');
                $table->unsignedTinyInteger('flow_direction')->default(1);
                $table->decimal('amount', 14, 3)->default(0);
                $table->string('notes')->nullable();
                $table->unsignedInteger('sequence_no')->nullable();
                $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->noActionOnDelete();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->noActionOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->noActionOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('supplier_payments', function (Blueprint $table) {
                $table->id();
                $table->date('payment_date');
                $table->foreignId('supplier_id')->constrained('suppliers')->noActionOnDelete();
                $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->noActionOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->noActionOnDelete();
                $table->unsignedTinyInteger('transaction_kind');
                $table->unsignedTinyInteger('flow_direction')->default(1);
                $table->decimal('amount', 14, 3)->default(0);
                $table->string('notes')->nullable();
                $table->unsignedInteger('sequence_no')->nullable();
                $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->noActionOnDelete();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->noActionOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->noActionOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('fund_transfers', function (Blueprint $table) {
                $table->id();
                $table->date('transfer_date');
                $table->unsignedTinyInteger('transfer_kind');
                $table->foreignId('from_cash_box_id')->nullable()->constrained('cash_boxes')->noActionOnDelete();
                $table->foreignId('to_cash_box_id')->nullable()->constrained('cash_boxes')->noActionOnDelete();
                $table->foreignId('from_bank_account_id')->nullable()->constrained('bank_accounts')->noActionOnDelete();
                $table->foreignId('to_bank_account_id')->nullable()->constrained('bank_accounts')->noActionOnDelete();
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->noActionOnDelete();
                $table->decimal('amount', 14, 3)->default(0);
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('sales_quotations', function (Blueprint $table) {
                $table->id();
                $table->date('quotation_date');
                $table->foreignId('customer_id')->nullable()->constrained('customers')->noActionOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->noActionOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->noActionOnDelete();
                $table->boolean('is_retail')->default(true);
                $table->decimal('lines_subtotal', 14, 3)->default(0);
                $table->decimal('extra_cost', 14, 3)->default(0);
                $table->decimal('rate_markup', 14, 3)->default(0);
                $table->decimal('difference_amount', 14, 3)->default(0);
                $table->decimal('discount', 14, 3)->default(0);
                $table->decimal('grand_total', 14, 3)->default(0);
                $table->decimal('amount_paid', 14, 3)->default(0);
                $table->decimal('deferred_amount', 14, 3)->default(0);
                $table->decimal('refund_amount', 14, 3)->default(0);
                $table->decimal('balance', 14, 3)->default(0);
                $table->date('unpaid_date')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('sales_quotation_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_quotation_id')->constrained('sales_quotations')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->string('barcode')->nullable();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('qty_secondary', 14, 3)->default(0);
                $table->decimal('unit_price_primary', 14, 3)->default(0);
                $table->decimal('unit_price_secondary', 14, 3)->default(0);
                $table->decimal('line_total', 14, 3)->default(0);
                $table->decimal('profit', 14, 3)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('sales_quotation_lines');
            Schema::connection($connection)->dropIfExists('sales_quotations');
            Schema::connection($connection)->dropIfExists('fund_transfers');
            Schema::connection($connection)->dropIfExists('supplier_payments');
            Schema::connection($connection)->dropIfExists('customer_receipts');
            Schema::connection($connection)->dropIfExists('stock_movements');
            Schema::connection($connection)->dropIfExists('fifo_allocations');
            Schema::connection($connection)->dropIfExists('purchase_invoice_lines');
            Schema::connection($connection)->dropIfExists('purchase_returns');
            Schema::connection($connection)->dropIfExists('purchase_invoices');
            Schema::connection($connection)->dropIfExists('sales_invoice_lines');
            Schema::connection($connection)->dropIfExists('sales_returns');
            Schema::connection($connection)->dropIfExists('sales_invoices');
        });
    }
};
