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
            if (! Schema::connection($connection)->hasTable('system_operation_logs')) {
                return;
            }

            Schema::connection($connection)->table('system_operation_logs', function (Blueprint $table) use ($connection): void {
                if (! Schema::connection($connection)->hasColumn('system_operation_logs', 'customer_id')) {
                    $table->unsignedBigInteger('customer_id')->nullable()->after('record_id');
                    $table->index('customer_id');
                }

                if (! Schema::connection($connection)->hasColumn('system_operation_logs', 'item_id')) {
                    $table->unsignedBigInteger('item_id')->nullable()->after('customer_id');
                    $table->index('item_id');
                }
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('system_operation_logs')) {
                return;
            }

            Schema::connection($connection)->table('system_operation_logs', function (Blueprint $table) use ($connection): void {
                if (Schema::connection($connection)->hasColumn('system_operation_logs', 'item_id')) {
                    $table->dropIndex(['item_id']);
                    $table->dropColumn('item_id');
                }

                if (Schema::connection($connection)->hasColumn('system_operation_logs', 'customer_id')) {
                    $table->dropIndex(['customer_id']);
                    $table->dropColumn('customer_id');
                }
            });
        });
    }
};
