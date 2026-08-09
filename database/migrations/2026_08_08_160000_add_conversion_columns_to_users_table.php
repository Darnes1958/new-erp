<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('empno')->nullable()->after('company');
            $table->unsignedBigInteger('old_user_id')->nullable()->after('empno');

            $table->index(['company', 'empno'], 'users_company_empno_index');
            $table->index('old_user_id', 'users_old_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_company_empno_index');
            $table->dropIndex('users_old_user_id_index');
            $table->dropColumn(['empno', 'old_user_id']);
        });
    }
};
