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
            Schema::connection($connection)->create('payroll_banks', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('account_number')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('installment_banks', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('payroll_bank_id')->nullable()->constrained('payroll_banks')->noActionOnDelete();
                $table->timestamps();
            });

            Schema::connection($connection)->create('workplaces', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });

            Schema::connection($connection)->create('installment_contracts', function (Blueprint $table) {
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
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('installment_contract_archives', function (Blueprint $table) {
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
                $table->date('archived_at')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('installment_deductions', function (Blueprint $table) {
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

                $table->foreign('installment_contract_id', 'installment_deductions_contract_fk')
                    ->references('id')->on('installment_contracts')
                    ->noActionOnDelete();
                $table->index(['installment_contract_id', 'sequence']);
            });

            Schema::connection($connection)->create('installment_deduction_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('installment_contract_id');
                $table->unsignedInteger('sequence')->default(0);
                $table->decimal('deducted_amount', 14, 3)->default(0);
                $table->date('deduction_date')->nullable();
                $table->date('installment_due_date')->nullable();
                $table->unsignedTinyInteger('deduction_type_id')->default(0);
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->decimal('remaining_balance', 14, 3)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('installment_contract_id', 'installment_deduction_archives_contract_fk')
                    ->references('id')->on('installment_contract_archives')
                    ->noActionOnDelete();
            });

            Schema::connection($connection)->create('installment_surplus', function (Blueprint $table) {
                $table->id();
                $table->string('contractable_type');
                $table->unsignedBigInteger('contractable_id');
                $table->date('surplus_date')->nullable();
                $table->decimal('amount', 14, 3)->default(0);
                $table->unsignedTinyInteger('status')->default(0);
                $table->unsignedBigInteger('suspended_id')->nullable();
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->unsignedBigInteger('deduction_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['contractable_type', 'contractable_id'], 'installment_surplus_contractable_idx');
            });

            Schema::connection($connection)->create('installment_surplus_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('installment_contract_id');
                $table->date('surplus_date')->nullable();
                $table->decimal('amount', 14, 3)->default(0);
                $table->unsignedTinyInteger('status')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('installment_contract_id', 'installment_surplus_archives_contract_fk')
                    ->references('id')->on('installment_contract_archives')
                    ->noActionOnDelete();
            });

            Schema::connection($connection)->create('installment_stops', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('installment_contract_id');
                $table->date('stop_date');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('installment_contract_id', 'installment_stops_contract_fk')
                    ->references('id')->on('installment_contracts')
                    ->noActionOnDelete();
            });

            Schema::connection($connection)->create('installment_stops_without_contract', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('account_number')->nullable();
                $table->date('stop_date');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('installment_cheques', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('installment_contract_id');
                $table->unsignedInteger('cheque_count')->default(0);
                $table->date('cheque_date')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('installment_contract_id', 'installment_cheques_contract_fk')
                    ->references('id')->on('installment_contracts')
                    ->noActionOnDelete();
            });

            Schema::connection($connection)->create('installment_suspended', function (Blueprint $table) {
                $table->id();
                $table->string('contractable_type');
                $table->unsignedBigInteger('contractable_id');
                $table->date('suspended_date')->nullable();
                $table->decimal('amount', 14, 3)->default(0);
                $table->unsignedTinyInteger('status')->default(0);
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['contractable_type', 'contractable_id'], 'installment_suspended_contractable_idx');
            });

            Schema::connection($connection)->create('deduction_batches', function (Blueprint $table) {
                $table->id();
                $table->date('batch_date')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::connection($connection)->create('deduction_batch_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deduction_batch_id')->constrained('deduction_batches')->noActionOnDelete();
                $table->string('contractable_type');
                $table->unsignedBigInteger('contractable_id');
                $table->decimal('amount', 14, 3)->default(0);
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index(['contractable_type', 'contractable_id'], 'deduction_batch_lines_contractable_idx');
            });

            Schema::connection($connection)->create('wrong_deductions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_bank_id')->nullable()->constrained('payroll_banks')->noActionOnDelete();
                $table->string('account_number')->nullable();
                $table->string('name')->nullable();
                $table->decimal('amount', 14, 3)->default(0);
                $table->unsignedTinyInteger('status')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('wrong_deductions');
            Schema::connection($connection)->dropIfExists('deduction_batch_lines');
            Schema::connection($connection)->dropIfExists('deduction_batches');
            Schema::connection($connection)->dropIfExists('installment_suspended');
            Schema::connection($connection)->dropIfExists('installment_cheques');
            Schema::connection($connection)->dropIfExists('installment_stops_without_contract');
            Schema::connection($connection)->dropIfExists('installment_stops');
            Schema::connection($connection)->dropIfExists('installment_surplus_archives');
            Schema::connection($connection)->dropIfExists('installment_surplus');
            Schema::connection($connection)->dropIfExists('installment_deduction_archives');
            Schema::connection($connection)->dropIfExists('installment_deductions');
            Schema::connection($connection)->dropIfExists('installment_contract_archives');
            Schema::connection($connection)->dropIfExists('installment_contracts');
            Schema::connection($connection)->dropIfExists('workplaces');
            Schema::connection($connection)->dropIfExists('installment_banks');
            Schema::connection($connection)->dropIfExists('payroll_banks');
        });
    }
};
