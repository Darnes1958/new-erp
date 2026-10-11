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
            try {
                if ($this->companyHasTables($connection, ['customers'])
                    && Schema::connection($connection)->hasColumn('customers', 'opening_balance')) {
                    Schema::connection($connection)->table('customers', function (Blueprint $table): void {
                        $table->dropColumn('opening_balance');
                    });
                }

                if ($this->companyHasTables($connection, ['suppliers'])
                    && Schema::connection($connection)->hasColumn('suppliers', 'opening_balance')) {
                    Schema::connection($connection)->table('suppliers', function (Blueprint $table): void {
                        $table->dropColumn('opening_balance');
                    });
                }
            } catch (\Throwable) {
                // Skip unreachable company databases.
            }
        });
    }

    public function down(): void
    {
        //
    }
};
