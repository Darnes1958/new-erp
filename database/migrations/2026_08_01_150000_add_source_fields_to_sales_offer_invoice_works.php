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
            if (Schema::connection($connection)->hasTable('sales_offer_invoice_works')
                && ! Schema::connection($connection)->hasColumn('sales_offer_invoice_works', 'source_sales_offer_invoice_id')) {
                Schema::connection($connection)->table('sales_offer_invoice_works', function (Blueprint $table) {
                    $table->unsignedBigInteger('source_sales_offer_invoice_id')->nullable()->after('user_id');
                });
            }

            if (Schema::connection($connection)->hasTable('sales_offer_invoice_line_works')
                && ! Schema::connection($connection)->hasColumn('sales_offer_invoice_line_works', 'source_sales_offer_invoice_line_id')) {
                Schema::connection($connection)->table('sales_offer_invoice_line_works', function (Blueprint $table) {
                    $table->unsignedBigInteger('source_sales_offer_invoice_line_id')->nullable()->after('sales_offer_invoice_work_id');
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (Schema::connection($connection)->hasColumn('sales_offer_invoice_works', 'source_sales_offer_invoice_id')) {
                Schema::connection($connection)->table('sales_offer_invoice_works', function (Blueprint $table) {
                    $table->dropColumn('source_sales_offer_invoice_id');
                });
            }

            if (Schema::connection($connection)->hasColumn('sales_offer_invoice_line_works', 'source_sales_offer_invoice_line_id')) {
                Schema::connection($connection)->table('sales_offer_invoice_line_works', function (Blueprint $table) {
                    $table->dropColumn('source_sales_offer_invoice_line_id');
                });
            }
        });
    }
};
