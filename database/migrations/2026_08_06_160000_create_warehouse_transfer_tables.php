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
            Schema::connection($connection)->create('warehouse_transfers', function (Blueprint $table) {
                $table->id();
                $table->date('transfer_date');
                $table->foreignId('warehouse_from_id')->constrained('warehouses')->noActionOnDelete();
                $table->foreignId('warehouse_to_id')->constrained('warehouses')->noActionOnDelete();
                $table->foreignId('destination_purchase_invoice_id')
                    ->nullable()
                    ->constrained('purchase_invoices')
                    ->noActionOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['transfer_date', 'warehouse_from_id']);
            });

            Schema::connection($connection)->create('warehouse_transfer_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('qty_secondary', 14, 3)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('warehouse_transfer_layers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_transfer_line_id')->constrained('warehouse_transfer_lines')->cascadeOnDelete();
                $table->foreignId('source_purchase_invoice_line_id')->constrained('purchase_invoice_lines')->noActionOnDelete();
                $table->foreignId('destination_purchase_invoice_line_id')->constrained('purchase_invoice_lines')->noActionOnDelete();
                $table->decimal('qty_primary', 14, 3)->default(0);
                $table->decimal('unit_cost', 14, 3)->default(0);
                $table->timestamps();
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('warehouse_transfer_layers');
            Schema::connection($connection)->dropIfExists('warehouse_transfer_lines');
            Schema::connection($connection)->dropIfExists('warehouse_transfers');
        });
    }
};
