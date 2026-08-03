<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (
                ! Schema::connection($connection)->hasTable('wrong_deductions')
                || ! Schema::connection($connection)->hasTable('wrongksts')
            ) {
                return;
            }

            if (Schema::connection($connection)->hasColumn('wrong_deductions', 'deduction_date')
                && Schema::connection($connection)->hasColumn('wrongksts', 'wrong_date')) {
                DB::connection($connection)->table('wrong_deductions as w')
                    ->join('wrongksts as s', 'w.id', '=', 's.id')
                    ->whereNotNull('s.wrong_date')
                    ->update([
                        'w.deduction_date' => DB::raw('s.wrong_date'),
                    ]);
            }

            if (Schema::connection($connection)->hasColumn('wrong_deductions', 'batch_id')
                && Schema::connection($connection)->hasColumn('wrongksts', 'haf_id')) {
                DB::connection($connection)->table('wrong_deductions as w')
                    ->join('wrongksts as s', 'w.id', '=', 's.id')
                    ->whereNull('w.batch_id')
                    ->whereNotNull('s.haf_id')
                    ->where('s.haf_id', '>', 0)
                    ->update([
                        'w.batch_id' => DB::raw('s.haf_id'),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Legacy backfill is not safely reversible.
    }
};
