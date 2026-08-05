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
            if (! Schema::connection($connection)->hasTable('bank_mains')) {
                Schema::connection($connection)->create('bank_mains', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->unsignedTinyInteger('r_type')->default(1);
                    $table->decimal('ratio', 14, 3)->default(0);
                    $table->timestamps();
                });
            }

            if (
                Schema::connection($connection)->hasTable('payroll_banks')
                && ! Schema::connection($connection)->hasColumn('payroll_banks', 'bank_main_id')
            ) {
                Schema::connection($connection)->table('payroll_banks', function (Blueprint $table): void {
                    $table->foreignId('bank_main_id')
                        ->nullable()
                        ->after('account_number')
                        ->constrained('bank_mains')
                        ->noActionOnDelete();
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (
                Schema::connection($connection)->hasTable('payroll_banks')
                && Schema::connection($connection)->hasColumn('payroll_banks', 'bank_main_id')
            ) {
                Schema::connection($connection)->table('payroll_banks', function (Blueprint $table): void {
                    $table->dropForeign(['bank_main_id']);
                    $table->dropColumn('bank_main_id');
                });
            }

            Schema::connection($connection)->dropIfExists('bank_mains');
        });
    }
};
