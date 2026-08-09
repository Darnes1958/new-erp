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
            if (! Schema::connection($connection)->hasTable('payroll_banks')) {
                return;
            }

            if (! Schema::connection($connection)->hasTable('bank_excel_import_settings')) {
                Schema::connection($connection)->create('bank_excel_import_settings', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->unsignedSmallInteger('heading_row')->default(1);
                    $table->string('column_account_number');
                    $table->string('column_customer_name');
                    $table->string('column_amount');
                    $table->string('column_deduction_date');
                    $table->foreignId('payroll_bank_id')->constrained('payroll_banks')->noActionOnDelete();
                });
            }

            if (! Schema::connection($connection)->hasTable('deduction_import_staging_lines')) {
                Schema::connection($connection)->create('deduction_import_staging_lines', function (Blueprint $table): void {
                    $table->id();
                    $table->uuid('import_session_id');
                    $table->foreignId('payroll_bank_id')->constrained('payroll_banks')->noActionOnDelete();
                    $table->foreignId('installment_bank_id')->nullable()->constrained('installment_banks')->noActionOnDelete();
                    $table->string('account_number');
                    $table->string('customer_name')->nullable();
                    $table->decimal('amount', 14, 3);
                    $table->date('deduction_date');
                    $table->unsignedInteger('row_number')->nullable();
                    $table->foreignId('deduction_batch_id')->nullable()->constrained('deduction_batches')->noActionOnDelete();
                    $table->string('match_status')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->index('import_session_id');
                    $table->index('deduction_batch_id');
                });
            }

            if (! Schema::connection($connection)->hasTable('deduction_import_date_ranges')) {
                Schema::connection($connection)->create('deduction_import_date_ranges', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('payroll_bank_id')->constrained('payroll_banks')->noActionOnDelete();
                    $table->date('from_date');
                    $table->date('to_date');
                    $table->foreignId('deduction_batch_id')->nullable()->constrained('deduction_batches')->noActionOnDelete();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->index(['payroll_bank_id', 'from_date', 'to_date']);
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('deduction_import_date_ranges');
            Schema::connection($connection)->dropIfExists('deduction_import_staging_lines');
            Schema::connection($connection)->dropIfExists('bank_excel_import_settings');
        });
    }
};
