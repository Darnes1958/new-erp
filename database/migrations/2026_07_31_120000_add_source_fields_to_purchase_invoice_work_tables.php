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
            if (! Schema::connection($connection)->hasTable('purchase_invoice_works')) {
                return;
            }

            Schema::connection($connection)->table('purchase_invoice_works', function (Blueprint $table) use ($connection): void {
                if (! Schema::connection($connection)->hasColumn('purchase_invoice_works', 'source_purchase_invoice_id')) {
                    $table->unsignedBigInteger('source_purchase_invoice_id')->nullable()->after('user_id');
                }
            });

            if (! Schema::connection($connection)->hasTable('purchase_invoice_line_works')) {
                return;
            }

            Schema::connection($connection)->table('purchase_invoice_line_works', function (Blueprint $table) use ($connection): void {
                if (! Schema::connection($connection)->hasColumn('purchase_invoice_line_works', 'source_purchase_invoice_line_id')) {
                    $table->unsignedBigInteger('source_purchase_invoice_line_id')->nullable()->after('purchase_invoice_work_id');
                }
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('purchase_invoice_works')) {
                return;
            }

            if (Schema::connection($connection)->hasTable('purchase_invoice_line_works')
                && Schema::connection($connection)->hasColumn('purchase_invoice_line_works', 'source_purchase_invoice_line_id')) {
                Schema::connection($connection)->table('purchase_invoice_line_works', function (Blueprint $table): void {
                    $table->dropColumn('source_purchase_invoice_line_id');
                });
            }

            if (Schema::connection($connection)->hasColumn('purchase_invoice_works', 'source_purchase_invoice_id')) {
                Schema::connection($connection)->table('purchase_invoice_works', function (Blueprint $table): void {
                    $table->dropColumn('source_purchase_invoice_id');
                });
            }
        });
    }
};
