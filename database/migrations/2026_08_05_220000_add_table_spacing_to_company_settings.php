<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        if (! Schema::connection($central)->hasColumn('company_settings', 'table_cell_padding_y_px')) {
            Schema::connection($central)->table('company_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('table_cell_padding_y_px')
                    ->default(5)
                    ->after('sidebar_item_padding_y_px');
            });
        }

        if (! Schema::connection($central)->hasColumn('company_settings', 'table_header_padding_y_px')) {
            Schema::connection($central)->table('company_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('table_header_padding_y_px')
                    ->default(7)
                    ->after('table_cell_padding_y_px');
            });
        }
    }

    public function down(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        foreach (['table_header_padding_y_px', 'table_cell_padding_y_px'] as $column) {
            if (Schema::connection($central)->hasColumn('company_settings', $column)) {
                Schema::connection($central)->table('company_settings', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
