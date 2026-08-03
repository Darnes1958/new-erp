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
            if (! Schema::connection($connection)->hasTable('installment_contracts')) {
                return;
            }

            if (! Schema::connection($connection)->hasTable('installment_contracts_cancelled')) {
                Schema::connection($connection)->create('installment_contracts_cancelled', function (Blueprint $table): void {
                    $table->unsignedBigInteger('id')->primary();
                    $table->foreignId('customer_id')->constrained('customers')->noActionOnDelete();
                    $table->foreignId('installment_bank_id')->nullable()->constrained('installment_banks')->noActionOnDelete();
                    $table->foreignId('workplace_id')->nullable()->constrained('workplaces')->noActionOnDelete();
                    $table->foreignId('payroll_bank_id')->nullable()->constrained('payroll_banks')->noActionOnDelete();
                    $table->string('bank_account_number')->nullable();
                    $table->date('contract_start')->nullable();
                    $table->date('contract_end')->nullable();
                    $table->decimal('contract_total', 14, 3)->default(0);
                    $table->unsignedInteger('installment_count')->default(0);
                    $table->decimal('installment_amount', 14, 3)->default(0);
                    $table->decimal('total_paid', 14, 3)->default(0);
                    $table->decimal('balance', 14, 3)->default(0);
                    $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->noActionOnDelete();
                    $table->unsignedInteger('cheques_in')->default(0);
                    $table->unsignedInteger('cheques_out')->default(0);
                    $table->date('last_deduction_month')->nullable();
                    $table->date('next_installment_date')->nullable();
                    $table->decimal('late_amount', 14, 3)->default(0);
                    $table->unsignedInteger('installments_remaining')->default(0);
                    $table->unsignedInteger('surplus_count')->default(0);
                    $table->decimal('surplus_amount', 14, 3)->default(0);
                    $table->unsignedInteger('suspended_count')->default(0);
                    $table->decimal('suspended_amount', 14, 3)->default(0);
                    $table->date('cancelled_at')->nullable();
                    $table->string('notes')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('installment_deductions_cancelled')) {
                Schema::connection($connection)->create('installment_deductions_cancelled', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('installment_contract_id');
                    $table->unsignedInteger('sequence')->default(0);
                    $table->decimal('deducted_amount', 14, 3)->default(0);
                    $table->date('deduction_date')->nullable();
                    $table->date('installment_due_date')->nullable();
                    $table->unsignedTinyInteger('deduction_type_id')->default(0);
                    $table->string('notes')->nullable();
                    $table->unsignedBigInteger('batch_id')->nullable();
                    $table->unsignedBigInteger('surplus_id')->nullable();
                    $table->decimal('remaining_balance', 14, 3)->default(0);
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();

                    $table->foreign('installment_contract_id', 'installment_deductions_cancelled_contract_fk')
                        ->references('id')->on('installment_contracts_cancelled')
                        ->noActionOnDelete();

                    $table->index(['installment_contract_id', 'sequence'], 'installment_deductions_cancelled_contract_seq_idx');
                });
            }

            if (
                Schema::connection($connection)->hasTable('deduction_batches')
                && ! Schema::connection($connection)->hasColumn('deduction_batches', 'posted_cancelled_amount')
            ) {
                Schema::connection($connection)->table('deduction_batches', function (Blueprint $table): void {
                    $table->decimal('posted_cancelled_amount', 14, 3)->default(0)->after('wrong_amount');
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('installment_deductions_cancelled');
            Schema::connection($connection)->dropIfExists('installment_contracts_cancelled');

            if (
                Schema::connection($connection)->hasTable('deduction_batches')
                && Schema::connection($connection)->hasColumn('deduction_batches', 'posted_cancelled_amount')
            ) {
                Schema::connection($connection)->table('deduction_batches', function (Blueprint $table): void {
                    $table->dropColumn('posted_cancelled_amount');
                });
            }
        });
    }
};
