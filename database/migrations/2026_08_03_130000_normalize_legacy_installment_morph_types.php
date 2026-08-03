<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    /**
     * @var array<string, string>
     */
    protected array $legacyMorphMap = [
        'App\Models\Main' => 'installment_contract',
        'App\Models\Main_arc' => 'installment_contract_archive',
        'App\Models\Overkst' => 'installment_surplus',
        'App\Models\Wrongkst' => 'wrong_deduction',
    ];

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            $this->normalizeMorphTypes($connection, 'installment_suspended');
            $this->normalizeMorphTypes($connection, 'installment_surplus');
            $this->normalizeMorphTypes($connection, 'deduction_batch_lines');
            $this->backfillSuspendedContractIdsFromSurplus($connection);
        });
    }

    public function down(): void
    {
        // Legacy types cannot be restored reliably.
    }

    protected function normalizeMorphTypes(string $connection, string $table): void
    {
        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        foreach ($this->legacyMorphMap as $legacyType => $newType) {
            DB::connection($connection)
                ->table($table)
                ->where('contractable_type', $legacyType)
                ->update(['contractable_type' => $newType]);
        }
    }

    protected function backfillSuspendedContractIdsFromSurplus(string $connection): void
    {
        if (
            ! Schema::connection($connection)->hasTable('installment_suspended')
            || ! Schema::connection($connection)->hasColumn('installment_suspended', 'installment_contract_id')
            || ! Schema::connection($connection)->hasTable('installment_surplus')
        ) {
            return;
        }

        DB::connection($connection)->table('installment_suspended as s')
            ->join('installment_surplus as o', 's.contractable_id', '=', 'o.id')
            ->where('s.contractable_type', 'installment_surplus')
            ->whereNull('s.installment_contract_id')
            ->where('o.contractable_type', 'installment_contract')
            ->update([
                's.installment_contract_id' => DB::raw('o.contractable_id'),
            ]);
    }
};
