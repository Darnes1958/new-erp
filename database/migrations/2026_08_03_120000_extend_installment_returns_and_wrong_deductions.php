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
            if (! Schema::connection($connection)->hasTable('installment_suspended')) {
                return;
            }

            if (! Schema::connection($connection)->hasColumn('installment_suspended', 'installment_contract_id')) {
                Schema::connection($connection)->table('installment_suspended', function (Blueprint $table): void {
                    $table->unsignedBigInteger('installment_contract_id')->nullable()->after('contractable_id');
                    $table->index('installment_contract_id', 'installment_suspended_contract_idx');
                });
            }

            if (
                Schema::connection($connection)->hasTable('wrong_deductions')
                && ! Schema::connection($connection)->hasColumn('wrong_deductions', 'deduction_date')
            ) {
                Schema::connection($connection)->table('wrong_deductions', function (Blueprint $table): void {
                    $table->date('deduction_date')->nullable()->after('amount');
                    $table->unsignedBigInteger('suspended_id')->nullable()->after('batch_id');
                });
            }

            $this->backfillSuspendedContractIds($connection);
            $this->backfillWrongDeductionDates($connection);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (Schema::connection($connection)->hasColumn('wrong_deductions', 'deduction_date')) {
                Schema::connection($connection)->table('wrong_deductions', function (Blueprint $table): void {
                    $table->dropColumn(['deduction_date', 'suspended_id']);
                });
            }

            if (Schema::connection($connection)->hasColumn('installment_suspended', 'installment_contract_id')) {
                Schema::connection($connection)->table('installment_suspended', function (Blueprint $table): void {
                    $table->dropIndex('installment_suspended_contract_idx');
                    $table->dropColumn('installment_contract_id');
                });
            }
        });
    }

    protected function backfillSuspendedContractIds(string $connection): void
    {
        if (! Schema::connection($connection)->hasColumn('installment_suspended', 'installment_contract_id')) {
            return;
        }

        DB::connection($connection)->table('installment_suspended')
            ->where('contractable_type', 'installment_contract')
            ->whereNull('installment_contract_id')
            ->update([
                'installment_contract_id' => DB::raw('contractable_id'),
            ]);

        if (Schema::connection($connection)->hasTable('installment_surplus')) {
            DB::connection($connection)->table('installment_suspended as s')
                ->join('installment_surplus as o', function ($join): void {
                    $join->on('s.contractable_id', '=', 'o.id')
                        ->where('s.contractable_type', '=', 'installment_surplus');
                })
                ->whereNull('s.installment_contract_id')
                ->where('o.contractable_type', 'installment_contract')
                ->update([
                    's.installment_contract_id' => DB::raw('o.contractable_id'),
                ]);
        }
    }

    protected function backfillWrongDeductionDates(string $connection): void
    {
        if (
            ! Schema::connection($connection)->hasColumn('wrong_deductions', 'deduction_date')
            || ! Schema::connection($connection)->hasTable('deduction_batches')
        ) {
            return;
        }

        DB::connection($connection)->table('wrong_deductions as w')
            ->join('deduction_batches as b', 'w.batch_id', '=', 'b.id')
            ->whereNull('w.deduction_date')
            ->update([
                'w.deduction_date' => DB::raw('b.batch_date'),
            ]);
    }
};
