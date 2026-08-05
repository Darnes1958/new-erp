<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('table_cell_padding_y_px')
                ->default(5)
                ->after('sidebar_item_padding_y_px');
            $table->unsignedTinyInteger('table_header_padding_y_px')
                ->default(7)
                ->after('table_cell_padding_y_px');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'table_cell_padding_y_px',
                'table_header_padding_y_px',
            ]);
        });
    }
};
