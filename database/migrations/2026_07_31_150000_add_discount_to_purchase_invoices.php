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
            if (Schema::connection($connection)->hasTable('purchase_invoices')
                && ! Schema::connection($connection)->hasColumn('purchase_invoices', 'discount')) {
                Schema::connection($connection)->table('purchase_invoices', function (Blueprint $table): void {
                    $table->decimal('discount', 14, 3)->default(0)->after('lines_subtotal');
                });
            }

            if (! Schema::connection($connection)->hasTable('purchase_invoice_works')) {
                return;
            }

            if (! Schema::connection($connection)->hasColumn('purchase_invoice_works', 'discount')) {
                Schema::connection($connection)->table('purchase_invoice_works', function (Blueprint $table): void {
                    $table->decimal('discount', 14, 3)->default(0)->after('lines_subtotal');
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (Schema::connection($connection)->hasTable('purchase_invoices')
                && Schema::connection($connection)->hasColumn('purchase_invoices', 'discount')) {
                Schema::connection($connection)->table('purchase_invoices', function (Blueprint $table): void {
                    $table->dropColumn('discount');
                });
            }

            if (Schema::connection($connection)->hasTable('purchase_invoice_works')
                && Schema::connection($connection)->hasColumn('purchase_invoice_works', 'discount')) {
                Schema::connection($connection)->table('purchase_invoice_works', function (Blueprint $table): void {
                    $table->dropColumn('discount');
                });
            }
        });
    }
};
