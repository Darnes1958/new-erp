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
            if (Schema::connection($connection)->hasTable('sales_returns')) {
                Schema::connection($connection)->table('sales_returns', function (Blueprint $table) use ($connection): void {
                    if (! Schema::connection($connection)->hasColumn('sales_returns', 'sales_invoice_line_id')) {
                        $table->foreignId('sales_invoice_line_id')->nullable()->after('sales_invoice_id')
                            ->constrained('sales_invoice_lines')->noActionOnDelete();
                    }
                    if (! Schema::connection($connection)->hasColumn('sales_returns', 'qty_primary')) {
                        $table->decimal('qty_primary', 14, 3)->default(0)->after('item_id');
                    }
                    if (! Schema::connection($connection)->hasColumn('sales_returns', 'qty_secondary')) {
                        $table->decimal('qty_secondary', 14, 3)->default(0)->after('qty_primary');
                    }
                    if (! Schema::connection($connection)->hasColumn('sales_returns', 'unit_price_primary')) {
                        $table->decimal('unit_price_primary', 14, 3)->default(0)->after('qty_secondary');
                    }
                    if (! Schema::connection($connection)->hasColumn('sales_returns', 'line_total')) {
                        $table->decimal('line_total', 14, 3)->default(0)->after('unit_price_primary');
                    }
                });
            }

            if (Schema::connection($connection)->hasTable('purchase_returns')) {
                Schema::connection($connection)->table('purchase_returns', function (Blueprint $table) use ($connection): void {
                    if (! Schema::connection($connection)->hasColumn('purchase_returns', 'purchase_invoice_line_id')) {
                        $table->foreignId('purchase_invoice_line_id')->nullable()->after('purchase_invoice_id')
                            ->constrained('purchase_invoice_lines')->noActionOnDelete();
                    }
                    if (! Schema::connection($connection)->hasColumn('purchase_returns', 'qty_primary')) {
                        $table->decimal('qty_primary', 14, 3)->default(0)->after('item_id');
                    }
                    if (! Schema::connection($connection)->hasColumn('purchase_returns', 'qty_secondary')) {
                        $table->decimal('qty_secondary', 14, 3)->default(0)->after('qty_primary');
                    }
                    if (! Schema::connection($connection)->hasColumn('purchase_returns', 'unit_cost_primary')) {
                        $table->decimal('unit_cost_primary', 14, 3)->default(0)->after('qty_secondary');
                    }
                    if (! Schema::connection($connection)->hasColumn('purchase_returns', 'line_total')) {
                        $table->decimal('line_total', 14, 3)->default(0)->after('unit_cost_primary');
                    }
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (Schema::connection($connection)->hasTable('sales_returns')) {
                Schema::connection($connection)->table('sales_returns', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('sales_invoice_line_id');
                    $table->dropColumn(['qty_primary', 'qty_secondary', 'unit_price_primary', 'line_total']);
                });
            }

            if (Schema::connection($connection)->hasTable('purchase_returns')) {
                Schema::connection($connection)->table('purchase_returns', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('purchase_invoice_line_id');
                    $table->dropColumn(['qty_primary', 'qty_secondary', 'unit_cost_primary', 'line_total']);
                });
            }
        });
    }
};
