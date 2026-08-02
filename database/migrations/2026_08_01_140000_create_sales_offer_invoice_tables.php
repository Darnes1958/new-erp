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
            if (! Schema::connection($connection)->hasTable('payment_methods')) {
                return;
            }

            if (! Schema::connection($connection)->hasTable('sales_offer_invoices')) {
                Schema::connection($connection)->create('sales_offer_invoices', function (Blueprint $table) {
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
                    $table->decimal('grand_total', 14, 3)->default(0);
                    $table->string('notes')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->index(['invoice_date', 'customer_id']);
                });
            }

            if (! Schema::connection($connection)->hasTable('sales_offer_invoice_lines')) {
                Schema::connection($connection)->create('sales_offer_invoice_lines', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('sales_offer_invoice_id')->constrained('sales_offer_invoices')->cascadeOnDelete();
                    $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                    $table->string('barcode')->nullable();
                    $table->decimal('qty_primary', 14, 3)->default(0);
                    $table->decimal('qty_secondary', 14, 3)->default(0);
                    $table->decimal('unit_price_primary', 14, 3)->default(0);
                    $table->decimal('unit_price_secondary', 14, 3)->default(0);
                    $table->decimal('line_total', 14, 3)->default(0);
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('sales_offer_invoice_works')) {
                Schema::connection($connection)->create('sales_offer_invoice_works', function (Blueprint $table) {
                    $table->unsignedBigInteger('id')->primary();
                    $table->date('invoice_date')->nullable();
                    $table->foreignId('customer_id')->nullable()->constrained('customers')->noActionOnDelete();
                    $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->noActionOnDelete();
                    $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->noActionOnDelete();
                    $table->boolean('is_retail')->default(true);
                    $table->decimal('lines_subtotal', 14, 3)->default(0);
                    $table->decimal('extra_cost', 14, 3)->default(0);
                    $table->decimal('rate_markup', 14, 3)->default(0);
                    $table->decimal('difference_amount', 14, 3)->default(0);
                    $table->decimal('grand_total', 14, 3)->default(0);
                    $table->string('notes')->nullable();
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('sales_offer_invoice_line_works')) {
                Schema::connection($connection)->create('sales_offer_invoice_line_works', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('sales_offer_invoice_work_id');
                    $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                    $table->string('barcode')->nullable();
                    $table->decimal('qty_primary', 14, 3)->default(0);
                    $table->decimal('qty_secondary', 14, 3)->default(0);
                    $table->decimal('unit_price_primary', 14, 3)->default(0);
                    $table->decimal('unit_price_secondary', 14, 3)->default(0);
                    $table->decimal('line_total', 14, 3)->default(0);
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->foreign('sales_offer_invoice_work_id', 'sales_offer_line_works_work_fk')
                        ->references('id')->on('sales_offer_invoice_works')
                        ->cascadeOnDelete();
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('sales_offer_invoice_line_works');
            Schema::connection($connection)->dropIfExists('sales_offer_invoice_works');
            Schema::connection($connection)->dropIfExists('sales_offer_invoice_lines');
            Schema::connection($connection)->dropIfExists('sales_offer_invoices');
        });
    }
};
