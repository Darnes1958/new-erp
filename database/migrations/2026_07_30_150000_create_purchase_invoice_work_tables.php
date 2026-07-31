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

            if (Schema::connection($connection)->hasTable('purchase_invoice_works')) {
                return;
            }

            Schema::connection($connection)->create('purchase_invoice_works', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->date('invoice_date')->nullable();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->noActionOnDelete();
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->noActionOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->noActionOnDelete();
                $table->decimal('lines_subtotal', 14, 3)->default(0);
                $table->decimal('amount_paid', 14, 3)->default(0);
                $table->decimal('balance', 14, 3)->default(0);
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('purchase_invoice_line_works', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_invoice_work_id');
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->string('barcode')->nullable();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('qty_secondary', 14, 3)->default(0);
                $table->decimal('unit_cost_primary', 14, 3)->default(0);
                $table->decimal('line_cost_total', 14, 3)->default(0);
                $table->date('expiry_date')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('purchase_invoice_work_id', 'purchase_invoice_line_works_work_fk')
                    ->references('id')->on('purchase_invoice_works')
                    ->cascadeOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('purchase_invoice_line_works');
            Schema::connection($connection)->dropIfExists('purchase_invoice_works');
        });
    }
};
