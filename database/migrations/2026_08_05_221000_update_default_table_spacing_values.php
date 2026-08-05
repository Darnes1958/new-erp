<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_settings')
            ->where('table_cell_padding_y_px', 4)
            ->update(['table_cell_padding_y_px' => 5]);

        DB::table('company_settings')
            ->where('table_header_padding_y_px', 6)
            ->update(['table_header_padding_y_px' => 7]);
    }

    public function down(): void
    {
        DB::table('company_settings')
            ->where('table_cell_padding_y_px', 5)
            ->update(['table_cell_padding_y_px' => 4]);

        DB::table('company_settings')
            ->where('table_header_padding_y_px', 7)
            ->update(['table_header_padding_y_px' => 6]);
    }
};
