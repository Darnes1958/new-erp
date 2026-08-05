<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('table_font_size_px')
                ->default(13)
                ->after('table_header_padding_y_px');
            $table->unsignedTinyInteger('table_header_font_size_px')
                ->default(12)
                ->after('table_font_size_px');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'table_font_size_px',
                'table_header_font_size_px',
            ]);
        });
    }
};
