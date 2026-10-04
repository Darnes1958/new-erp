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

        if (! Schema::connection($central)->hasColumn('users', 'empno')) {
            Schema::connection($central)->table('users', function (Blueprint $table): void {
                $table->unsignedInteger('empno')->nullable()->after('company');
            });
        }

        if (! Schema::connection($central)->hasColumn('users', 'old_user_id')) {
            Schema::connection($central)->table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('old_user_id')->nullable()->after('empno');
            });
        }

        DB::connection($central)->unprepared(<<<'SQL'
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'users_company_empno_index'
      AND object_id = OBJECT_ID(N'dbo.users')
)
AND COL_LENGTH(N'dbo.users', N'empno') IS NOT NULL
    CREATE INDEX users_company_empno_index ON dbo.users (company, empno);

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'users_old_user_id_index'
      AND object_id = OBJECT_ID(N'dbo.users')
)
AND COL_LENGTH(N'dbo.users', N'old_user_id') IS NOT NULL
    CREATE INDEX users_old_user_id_index ON dbo.users (old_user_id);
SQL);
    }

    public function down(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        DB::connection($central)->unprepared(<<<'SQL'
IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'users_company_empno_index'
      AND object_id = OBJECT_ID(N'dbo.users')
)
    DROP INDEX users_company_empno_index ON dbo.users;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'users_old_user_id_index'
      AND object_id = OBJECT_ID(N'dbo.users')
)
    DROP INDEX users_old_user_id_index ON dbo.users;
SQL);

        if (Schema::connection($central)->hasColumn('users', 'old_user_id')) {
            Schema::connection($central)->table('users', function (Blueprint $table): void {
                $table->dropColumn('old_user_id');
            });
        }

        if (Schema::connection($central)->hasColumn('users', 'empno')) {
            Schema::connection($central)->table('users', function (Blueprint $table): void {
                $table->dropColumn('empno');
            });
        }
    }
};
