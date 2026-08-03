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
            $this->backfillFromLinkedSurplus($connection);
        });
    }

    public function down(): void
    {
        //
    }

    protected function backfillFromLinkedSurplus(string $connection): void
    {
        if (
            ! Schema::connection($connection)->hasTable('installment_suspended')
            || ! Schema::connection($connection)->hasColumn('installment_suspended', 'installment_contract_id')
            || ! Schema::connection($connection)->hasTable('installment_surplus')
        ) {
            return;
        }

        $suspendedTypes = ['installment_surplus', 'App\\Models\\Overkst'];
        $surplusContractTypes = [
            'installment_contract',
            'installment_contract_archive',
            'App\\Models\\Main',
            'App\\Models\\Main_arc',
        ];

        DB::connection($connection)->table('installment_suspended as s')
            ->join('installment_surplus as o', 's.contractable_id', '=', 'o.id')
            ->whereIn('s.contractable_type', $suspendedTypes)
            ->whereNull('s.installment_contract_id')
            ->whereIn('o.contractable_type', $surplusContractTypes)
            ->update([
                's.installment_contract_id' => DB::raw('o.contractable_id'),
            ]);
    }
};
