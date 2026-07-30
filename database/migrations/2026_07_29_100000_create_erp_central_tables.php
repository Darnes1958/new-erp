<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company', 64)->nullable()->after('password');
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('company');
            $table->unsignedTinyInteger('status')->default(1)->after('warehouse_id');
            $table->boolean('is_prog')->default(false)->after('status');
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->string('company', 64)->primary();
            $table->boolean('has_expiry_dates')->default(false);
            $table->boolean('has_dual_unit')->default(false);
            $table->boolean('multi_warehouse')->default(false);
            $table->boolean('wholesale_retail')->default(false);
            $table->boolean('barcode_enabled')->default(false);
            $table->boolean('link_sales_to_installments')->default(false);
            $table->boolean('auto_price_update')->default(false);
            $table->text('user_message')->nullable();
            $table->text('alert_message')->nullable();
        });

        Schema::create('our_companies', function (Blueprint $table) {
            $table->id();
            $table->string('connection_name', 64)->unique();
            $table->string('display_name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('our_companies');
        Schema::dropIfExists('company_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company', 'warehouse_id', 'status', 'is_prog']);
        });
    }
};
