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
            if (! Schema::connection($connection)->hasTable('fifo_allocations')) {
                return;
            }

            Schema::connection($connection)->table('fifo_allocations', function (Blueprint $table) use ($connection): void {
                if (! Schema::connection($connection)->hasColumn('fifo_allocations', 'purchase_invoice_line_id')) {
                    $table->unsignedBigInteger('purchase_invoice_line_id')->nullable()->after('purchase_invoice_id');
                }

                if (! Schema::connection($connection)->hasColumn('fifo_allocations', 'unit_cost')) {
                    $table->decimal('unit_cost', 14, 3)->nullable()->after('qty_secondary');
                }
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('fifo_allocations')) {
                return;
            }

            Schema::connection($connection)->table('fifo_allocations', function (Blueprint $table) use ($connection): void {
                if (Schema::connection($connection)->hasColumn('fifo_allocations', 'unit_cost')) {
                    $table->dropColumn('unit_cost');
                }

                if (Schema::connection($connection)->hasColumn('fifo_allocations', 'purchase_invoice_line_id')) {
                    $table->dropColumn('purchase_invoice_line_id');
                }
            });
        });
    }
};
