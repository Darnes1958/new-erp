<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

            if (Schema::connection($connection)->hasTable('wrong_deduction_accounts')) {
                return;
            }

            Schema::connection($connection)->create('wrong_deduction_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_bank_id')->nullable()->constrained('payroll_banks')->noActionOnDelete();
                $table->foreignId('installment_bank_id')->nullable()->constrained('installment_banks')->noActionOnDelete();
                $table->string('account_number');
                $table->string('name');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['payroll_bank_id', 'account_number'], 'wrong_deduction_accounts_payroll_account_unique');
            });

            $this->backfillFromWrongDeductions($connection);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('wrong_deduction_accounts');
        });
    }

    protected function backfillFromWrongDeductions(string $connection): void
    {
        if (! Schema::connection($connection)->hasTable('wrong_deductions')) {
            return;
        }

        $rows = DB::connection($connection)
            ->table('wrong_deductions')
            ->whereNotNull('account_number')
            ->where('account_number', '!=', '')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('id')
            ->get(['payroll_bank_id', 'account_number', 'name', 'created_by']);

        $seen = [];

        foreach ($rows as $row) {
            $key = ($row->payroll_bank_id ?? 0).'|'.$row->account_number;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            DB::connection($connection)->table('wrong_deduction_accounts')->updateOrInsert(
                [
                    'payroll_bank_id' => $row->payroll_bank_id,
                    'account_number' => $row->account_number,
                ],
                [
                    'name' => $row->name,
                    'created_by' => $row->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
};
