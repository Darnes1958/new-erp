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
            if (Schema::connection($connection)->hasTable('system_operation_logs')) {
                return;
            }

            Schema::connection($connection)->create('system_operation_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('operation', 100);
                $table->string('action', 20);
                $table->unsignedBigInteger('record_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['operation', 'record_id']);
                $table->index('user_id');
                $table->index('created_at');
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('system_operation_logs');
        });
    }
};
