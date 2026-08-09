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
            Schema::connection($connection)->create('inventory_count_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('notes')->nullable();
                $table->unsignedSmallInteger('year');
                $table->boolean('is_active')->default(true);
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['year', 'is_active']);
            });

            Schema::connection($connection)->create('inventory_count_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('inventory_count_session_id')
                    ->constrained('inventory_count_sessions')
                    ->noActionOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->noActionOnDelete();
                $table->foreignId('item_id')->constrained('items')->noActionOnDelete();
                $table->decimal('book_balance', 14, 3)->default(0);
                $table->decimal('actual_balance', 14, 3)->default(0);
                $table->decimal('quantity_difference', 14, 3)->default(0);
                $table->decimal('value_amount', 14, 3)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['inventory_count_session_id', 'warehouse_id', 'item_id'],
                    'inventory_count_lines_session_warehouse_item_unique',
                );
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('inventory_count_lines');
            Schema::connection($connection)->dropIfExists('inventory_count_sessions');
        });
    }
};
