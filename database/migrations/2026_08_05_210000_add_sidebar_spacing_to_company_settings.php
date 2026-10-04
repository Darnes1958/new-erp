<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        if (! Schema::connection($central)->hasColumn('company_settings', 'sidebar_group_gap_px')) {
            Schema::connection($central)->table('company_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('sidebar_group_gap_px')
                    ->default(8)
                    ->after('alert_message');
            });
        }

        if (! Schema::connection($central)->hasColumn('company_settings', 'sidebar_item_gap_px')) {
            Schema::connection($central)->table('company_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('sidebar_item_gap_px')
                    ->default(2)
                    ->after('sidebar_group_gap_px');
            });
        }

        if (! Schema::connection($central)->hasColumn('company_settings', 'sidebar_item_padding_y_px')) {
            Schema::connection($central)->table('company_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('sidebar_item_padding_y_px')
                    ->default(4)
                    ->after('sidebar_item_gap_px');
            });
        }
    }

    public function down(): void
    {
        $central = (string) config('erp.central_connection', 'sqlsrv');

        foreach ([
            'sidebar_item_padding_y_px',
            'sidebar_item_gap_px',
            'sidebar_group_gap_px',
        ] as $column) {
            if (Schema::connection($central)->hasColumn('company_settings', $column)) {
                Schema::connection($central)->table('company_settings', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
