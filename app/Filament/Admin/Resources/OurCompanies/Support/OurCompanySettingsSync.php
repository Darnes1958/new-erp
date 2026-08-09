<?php

namespace App\Filament\Admin\Resources\OurCompanies\Support;

use App\Models\CompanySetting;
use App\Support\FilamentSidebarStyle;

class OurCompanySettingsSync
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeSettingsIntoFormData(array $data): array
    {
        $connectionName = $data['connection_name'] ?? null;

        if (! is_string($connectionName) || $connectionName === '') {
            return $data;
        }

        $settings = CompanySetting::query()->find($connectionName);

        $data['sidebar_group_gap_px'] = $settings?->sidebar_group_gap_px
            ?? FilamentSidebarStyle::DEFAULT_GROUP_GAP_PX;
        $data['sidebar_item_gap_px'] = $settings?->sidebar_item_gap_px
            ?? FilamentSidebarStyle::DEFAULT_ITEM_GAP_PX;
        $data['sidebar_item_padding_y_px'] = $settings?->sidebar_item_padding_y_px
            ?? FilamentSidebarStyle::DEFAULT_ITEM_PADDING_Y_PX;
        $data['table_cell_padding_y_px'] = $settings?->table_cell_padding_y_px
            ?? FilamentSidebarStyle::DEFAULT_TABLE_CELL_PADDING_Y_PX;
        $data['table_header_padding_y_px'] = $settings?->table_header_padding_y_px
            ?? FilamentSidebarStyle::DEFAULT_TABLE_HEADER_PADDING_Y_PX;
        $data['table_font_size_px'] = $settings?->table_font_size_px
            ?? FilamentSidebarStyle::DEFAULT_TABLE_FONT_SIZE_PX;
        $data['table_header_font_size_px'] = $settings?->table_header_font_size_px
            ?? FilamentSidebarStyle::DEFAULT_TABLE_HEADER_FONT_SIZE_PX;
        $data['user_message'] = $settings?->user_message;
        $data['alert_message'] = $settings?->alert_message;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function syncFromFormData(string $connectionName, array $data): void
    {
        CompanySetting::query()->updateOrCreate(
            ['company' => $connectionName],
            [
                'sidebar_group_gap_px' => FilamentSidebarStyle::normalizePx(
                    $data['sidebar_group_gap_px'] ?? null,
                    FilamentSidebarStyle::DEFAULT_GROUP_GAP_PX,
                    0,
                    32,
                ),
                'sidebar_item_gap_px' => FilamentSidebarStyle::normalizePx(
                    $data['sidebar_item_gap_px'] ?? null,
                    FilamentSidebarStyle::DEFAULT_ITEM_GAP_PX,
                    0,
                    16,
                ),
                'sidebar_item_padding_y_px' => FilamentSidebarStyle::normalizePx(
                    $data['sidebar_item_padding_y_px'] ?? null,
                    FilamentSidebarStyle::DEFAULT_ITEM_PADDING_Y_PX,
                    2,
                    16,
                ),
                'table_cell_padding_y_px' => FilamentSidebarStyle::normalizePx(
                    $data['table_cell_padding_y_px'] ?? null,
                    FilamentSidebarStyle::DEFAULT_TABLE_CELL_PADDING_Y_PX,
                    1,
                    16,
                ),
                'table_header_padding_y_px' => FilamentSidebarStyle::normalizePx(
                    $data['table_header_padding_y_px'] ?? null,
                    FilamentSidebarStyle::DEFAULT_TABLE_HEADER_PADDING_Y_PX,
                    1,
                    20,
                ),
                'table_font_size_px' => FilamentSidebarStyle::normalizePx(
                    $data['table_font_size_px'] ?? null,
                    FilamentSidebarStyle::DEFAULT_TABLE_FONT_SIZE_PX,
                    10,
                    18,
                ),
                'table_header_font_size_px' => FilamentSidebarStyle::normalizePx(
                    $data['table_header_font_size_px'] ?? null,
                    FilamentSidebarStyle::DEFAULT_TABLE_HEADER_FONT_SIZE_PX,
                    10,
                    18,
                ),
                'user_message' => filled($data['user_message'] ?? null) ? $data['user_message'] : null,
                'alert_message' => filled($data['alert_message'] ?? null) ? $data['alert_message'] : null,
            ],
        );
    }
}
