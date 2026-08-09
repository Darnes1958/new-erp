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
            if (! Schema::connection($connection)->hasTable('inventory_count_lines')) {
                return;
            }

            Schema::connection($connection)->table('inventory_count_lines', function (Blueprint $table) use ($connection): void {
                if (! Schema::connection($connection)->hasColumn('inventory_count_lines', 'fifo_purchase_invoice_line_id')) {
                    $table->foreignId('fifo_purchase_invoice_line_id')
                        ->nullable()
                        ->after('value_amount')
                        ->constrained('purchase_invoice_lines')
                        ->noActionOnDelete();
                }

                if (! Schema::connection($connection)->hasColumn('inventory_count_lines', 'fifo_layer_created')) {
                    $table->boolean('fifo_layer_created')
                        ->default(false)
                        ->after('fifo_purchase_invoice_line_id');
                }
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('inventory_count_lines')) {
                return;
            }

            Schema::connection($connection)->table('inventory_count_lines', function (Blueprint $table) use ($connection): void {
                if (Schema::connection($connection)->hasColumn('inventory_count_lines', 'fifo_purchase_invoice_line_id')) {
                    $table->dropForeign(['fifo_purchase_invoice_line_id']);
                    $table->dropColumn('fifo_purchase_invoice_line_id');
                }

                if (Schema::connection($connection)->hasColumn('inventory_count_lines', 'fifo_layer_created')) {
                    $table->dropColumn('fifo_layer_created');
                }
            });
        });
    }
};
