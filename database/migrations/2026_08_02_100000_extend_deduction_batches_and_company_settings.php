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
        if (! Schema::hasColumn('company_settings', 'installment_by_payroll_bank')) {
            Schema::table('company_settings', function (Blueprint $table): void {
                $table->boolean('installment_by_payroll_bank')
                    ->default(true)
                    ->after('link_sales_to_installments');
            });
        }

        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('deduction_batches')) {
                return;
            }

            if (! Schema::connection($connection)->hasColumn('deduction_batches', 'status')) {
                Schema::connection($connection)->table('deduction_batches', function (Blueprint $table): void {
                    $table->foreignId('payroll_bank_id')->nullable()->after('id')->constrained('payroll_banks')->noActionOnDelete();
                    $table->foreignId('installment_bank_id')->nullable()->after('payroll_bank_id')->constrained('installment_banks')->noActionOnDelete();
                    $table->unsignedTinyInteger('status')->default(0)->after('installment_bank_id');
                    $table->date('from_date')->nullable()->after('batch_date');
                    $table->date('to_date')->nullable()->after('from_date');
                    $table->decimal('total_amount', 14, 3)->default(0)->after('to_date');
                    $table->decimal('posted_normal_amount', 14, 3)->default(0)->after('total_amount');
                    $table->decimal('posted_archive_amount', 14, 3)->default(0)->after('posted_normal_amount');
                    $table->decimal('posted_surplus_amount', 14, 3)->default(0)->after('posted_archive_amount');
                    $table->decimal('posted_partial_amount', 14, 3)->default(0)->after('posted_surplus_amount');
                    $table->decimal('wrong_amount', 14, 3)->default(0)->after('posted_partial_amount');
                });
            }

            if (
                Schema::connection($connection)->hasTable('deduction_batch_lines')
                && ! Schema::connection($connection)->hasColumn('deduction_batch_lines', 'entry_type')
            ) {
                Schema::connection($connection)->table('deduction_batch_lines', function (Blueprint $table): void {
                    $table->string('account_number')->nullable()->after('contractable_id');
                    $table->date('deduction_date')->nullable()->after('amount');
                    $table->unsignedTinyInteger('entry_type')->default(1)->after('notes');
                    $table->unsignedTinyInteger('posted_type')->nullable()->after('entry_type');
                    $table->unsignedBigInteger('created_by')->nullable()->after('posted_type');
                });
            }

            if (
                Schema::connection($connection)->hasTable('wrong_deductions')
                && ! Schema::connection($connection)->hasColumn('wrong_deductions', 'batch_id')
            ) {
                Schema::connection($connection)->table('wrong_deductions', function (Blueprint $table): void {
                    $table->unsignedBigInteger('batch_id')->nullable()->after('status');
                });
            }
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! Schema::connection($connection)->hasTable('deduction_batches')) {
                return;
            }

            if (Schema::connection($connection)->hasColumn('wrong_deductions', 'batch_id')) {
                Schema::connection($connection)->table('wrong_deductions', function (Blueprint $table): void {
                    $table->dropColumn('batch_id');
                });
            }

            if (Schema::connection($connection)->hasColumn('deduction_batch_lines', 'entry_type')) {
                Schema::connection($connection)->table('deduction_batch_lines', function (Blueprint $table): void {
                    $table->dropColumn([
                        'account_number',
                        'deduction_date',
                        'entry_type',
                        'posted_type',
                        'created_by',
                    ]);
                });
            }

            if (Schema::connection($connection)->hasColumn('deduction_batches', 'status')) {
                Schema::connection($connection)->table('deduction_batches', function (Blueprint $table): void {
                    $table->dropForeign(['payroll_bank_id']);
                    $table->dropForeign(['installment_bank_id']);
                    $table->dropColumn([
                        'payroll_bank_id',
                        'installment_bank_id',
                        'status',
                        'from_date',
                        'to_date',
                        'total_amount',
                        'posted_normal_amount',
                        'posted_archive_amount',
                        'posted_surplus_amount',
                        'posted_partial_amount',
                        'wrong_amount',
                    ]);
                });
            }
        });

        if (Schema::hasColumn('company_settings', 'installment_by_payroll_bank')) {
            Schema::table('company_settings', function (Blueprint $table): void {
                $table->dropColumn('installment_by_payroll_bank');
            });
        }
    }
};
