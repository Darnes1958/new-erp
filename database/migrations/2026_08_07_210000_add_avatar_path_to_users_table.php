<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        if (! Schema::connection($central)->hasColumn('users', 'avatar_path')) {
            Schema::connection($central)->table('users', function (Blueprint $table): void {
                $table->string('avatar_path')->nullable()->after('is_prog');
            });
        }
    }

    public function down(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        if (Schema::connection($central)->hasColumn('users', 'avatar_path')) {
            Schema::connection($central)->table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_path');
            });
        }
    }
};
