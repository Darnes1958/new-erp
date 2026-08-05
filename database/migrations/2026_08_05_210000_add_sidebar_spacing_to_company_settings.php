<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('sidebar_group_gap_px')
                ->default(8)
                ->after('alert_message');
            $table->unsignedTinyInteger('sidebar_item_gap_px')
                ->default(2)
                ->after('sidebar_group_gap_px');
            $table->unsignedTinyInteger('sidebar_item_padding_y_px')
                ->default(4)
                ->after('sidebar_item_gap_px');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sidebar_group_gap_px',
                'sidebar_item_gap_px',
                'sidebar_item_padding_y_px',
            ]);
        });
    }
};
