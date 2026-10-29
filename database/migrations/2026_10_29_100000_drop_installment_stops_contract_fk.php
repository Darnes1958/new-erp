<?php

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use MigratesCompanyDatabases;

    public function up(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! $this->companyHasTables($connection, ['installment_stops', 'installment_contracts'])) {
                return;
            }

            DB::connection($connection)->unprepared(<<<'SQL'
IF EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'installment_stops_contract_fk'
)
    ALTER TABLE installment_stops DROP CONSTRAINT installment_stops_contract_fk;
SQL);
        });
    }

    public function down(): void
    {
        $this->onEachCompanyConnection(function (string $connection): void {
            if (! $this->companyHasTables($connection, ['installment_stops', 'installment_contracts'])) {
                return;
            }

            DB::connection($connection)->unprepared(<<<'SQL'
IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'installment_stops_contract_fk'
)
    ALTER TABLE installment_stops
        ADD CONSTRAINT installment_stops_contract_fk
        FOREIGN KEY (installment_contract_id) REFERENCES installment_contracts (id);
SQL);
        });
    }
};
