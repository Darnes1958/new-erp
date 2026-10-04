<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        if (! Schema::connection($central)->hasTable('company_preferences')) {
            Schema::connection($central)->create('company_preferences', function (Blueprint $table): void {
                $table->string('company', 64)->primary();
                $table->boolean('print_after_store_sales')->default(false);
                $table->timestamps();
            });
        }

        $companies = DB::connection($central)
            ->table('our_companies')
            ->pluck('connection_name');

        $now = now();

        foreach ($companies as $company) {
            if (! is_string($company) || $company === '') {
                continue;
            }

            DB::connection($central)->table('company_preferences')->updateOrInsert(
                ['company' => $company],
                [
                    'print_after_store_sales' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        Schema::connection($central)->dropIfExists('company_preferences');
    }
};
