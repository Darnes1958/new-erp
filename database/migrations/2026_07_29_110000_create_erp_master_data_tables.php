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
            Schema::connection($connection)->create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 32)->unique();
                $table->decimal('rate', 12, 3)->default(0);
                $table->decimal('adjustment_value', 12, 3)->default(0);
                $table->unsignedTinyInteger('adjustment_direction')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            Schema::connection($connection)->create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('abbreviation', 16)->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('item_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });

            Schema::connection($connection)->create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });

            Schema::connection($connection)->create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedTinyInteger('warehouse_type')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            Schema::connection($connection)->create('customer_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });

            Schema::connection($connection)->create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('address')->nullable();
                $table->string('mdar')->nullable();
                $table->string('libyana')->nullable();
                $table->string('card_no')->nullable();
                $table->string('others')->nullable();
                $table->foreignId('customer_type_id')->nullable()->constrained('customer_types')->nullOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('address')->nullable();
                $table->string('mdar')->nullable();
                $table->string('libyana')->nullable();
                $table->string('card_no')->nullable();
                $table->string('others')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('cash_boxes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('opening_balance', 14, 3)->default(0);
                $table->unsignedBigInteger('assigned_user_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            Schema::connection($connection)->create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('account_number')->nullable();
                $table->decimal('opening_balance', 14, 3)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            Schema::connection($connection)->create('items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('barcode')->nullable();
                $table->foreignId('item_type_id')->nullable()->constrained('item_types')->noActionOnDelete();
                $table->foreignId('brand_id')->nullable()->constrained('brands')->noActionOnDelete();
                $table->unsignedBigInteger('primary_unit_id')->nullable();
                $table->unsignedBigInteger('secondary_unit_id')->nullable();
                $table->boolean('has_dual_unit')->default(false);
                $table->decimal('conversion_factor', 12, 3)->default(1);
                $table->decimal('default_buy_price', 12, 3)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('barcode');
            });

            Schema::connection($connection)->create('item_barcodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->string('barcode');
                $table->timestamps();

                $table->unique('barcode');
            });

            Schema::connection($connection)->create('item_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->noActionOnDelete();
                $table->string('price_kind', 8);
                $table->decimal('price_primary', 12, 3)->default(0);
                $table->decimal('price_secondary', 12, 3)->default(0);
                $table->timestamps();

                $table->unique(['item_id', 'payment_method_id', 'price_kind']);
            });

            Schema::connection($connection)->create('warehouse_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('warehouses')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->decimal('quantity_primary', 14, 3)->default(0);
                $table->decimal('quantity_secondary', 14, 3)->default(0);
                $table->timestamps();

                $table->unique(['warehouse_id', 'item_id']);
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('warehouse_stocks');
            Schema::connection($connection)->dropIfExists('item_prices');
            Schema::connection($connection)->dropIfExists('item_barcodes');
            Schema::connection($connection)->dropIfExists('items');
            Schema::connection($connection)->dropIfExists('bank_accounts');
            Schema::connection($connection)->dropIfExists('cash_boxes');
            Schema::connection($connection)->dropIfExists('suppliers');
            Schema::connection($connection)->dropIfExists('customers');
            Schema::connection($connection)->dropIfExists('customer_types');
            Schema::connection($connection)->dropIfExists('warehouses');
            Schema::connection($connection)->dropIfExists('brands');
            Schema::connection($connection)->dropIfExists('item_types');
            Schema::connection($connection)->dropIfExists('units');
            Schema::connection($connection)->dropIfExists('payment_methods');
        });
    }
};
