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
            if (! Schema::connection($connection)->hasTable('customer_receipts')) {
                return;
            }

            if (Schema::connection($connection)->hasColumn('customer_receipts', 'sales_return_id')) {
                return;
            }

            Schema::connection($connection)->table('customer_receipts', function (Blueprint $table): void {
                $table->unsignedBigInteger('sales_return_id')->nullable()->after('sales_invoice_id');
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('customer_receipts')) {
                return;
            }

            if (! Schema::connection($connection)->hasColumn('customer_receipts', 'sales_return_id')) {
                return;
            }

            Schema::connection($connection)->table('customer_receipts', function (Blueprint $table): void {
                $table->dropColumn('sales_return_id');
            });
        });
    }
};
