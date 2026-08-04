<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('our_companies', function (Blueprint $table): void {
            $table->string('display_name_suffix')->nullable()->after('display_name');
            $table->string('comp_code', 32)->nullable()->after('display_name_suffix');
        });
    }

    public function down(): void
    {
        Schema::table('our_companies', function (Blueprint $table): void {
            $table->dropColumn(['display_name_suffix', 'comp_code']);
        });
    }
};
