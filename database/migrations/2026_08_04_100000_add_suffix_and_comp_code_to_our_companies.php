<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        if (! Schema::connection($central)->hasColumn('our_companies', 'display_name_suffix')) {
            Schema::connection($central)->table('our_companies', function (Blueprint $table): void {
                $table->string('display_name_suffix')->nullable()->after('display_name');
            });
        }

        if (! Schema::connection($central)->hasColumn('our_companies', 'comp_code')) {
            Schema::connection($central)->table('our_companies', function (Blueprint $table): void {
                $table->string('comp_code', 32)->nullable()->after('display_name_suffix');
            });
        }
    }

    public function down(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        if (Schema::connection($central)->hasColumn('our_companies', 'comp_code')) {
            Schema::connection($central)->table('our_companies', function (Blueprint $table): void {
                $table->dropColumn('comp_code');
            });
        }

        if (Schema::connection($central)->hasColumn('our_companies', 'display_name_suffix')) {
            Schema::connection($central)->table('our_companies', function (Blueprint $table): void {
                $table->dropColumn('display_name_suffix');
            });
        }
    }
};
