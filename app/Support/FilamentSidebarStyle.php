<?php

namespace App\Support;

class FilamentSidebarStyle
{
    public const DEFAULT_GROUP_GAP_PX = 8;

    public const DEFAULT_ITEM_GAP_PX = 2;

    public const DEFAULT_ITEM_PADDING_Y_PX = 4;

    public const DEFAULT_TABLE_CELL_PADDING_Y_PX = 5;

    public const DEFAULT_TABLE_HEADER_PADDING_Y_PX = 7;

    public const DEFAULT_TABLE_FONT_SIZE_PX = 13;

    public const DEFAULT_TABLE_HEADER_FONT_SIZE_PX = 12;

    public static function groupGapPx(): int
    {
        $value = CompanySettings::current()?->sidebar_group_gap_px;

        return self::normalizePx($value, self::DEFAULT_GROUP_GAP_PX, 0, 32);
    }

    public static function itemGapPx(): int
    {
        $value = CompanySettings::current()?->sidebar_item_gap_px;

        return self::normalizePx($value, self::DEFAULT_ITEM_GAP_PX, 0, 16);
    }

    public static function itemPaddingYPx(): int
    {
        $value = CompanySettings::current()?->sidebar_item_padding_y_px;

        return self::normalizePx($value, self::DEFAULT_ITEM_PADDING_Y_PX, 2, 16);
    }

    public static function tableCellPaddingYPx(): int
    {
        $value = CompanySettings::current()?->table_cell_padding_y_px;

        return self::normalizePx($value, self::DEFAULT_TABLE_CELL_PADDING_Y_PX, 1, 16);
    }

    public static function tableHeaderPaddingYPx(): int
    {
        $value = CompanySettings::current()?->table_header_padding_y_px;

        return self::normalizePx($value, self::DEFAULT_TABLE_HEADER_PADDING_Y_PX, 1, 20);
    }

    public static function tableFontSizePx(): int
    {
        $value = CompanySettings::current()?->table_font_size_px;

        return self::normalizePx($value, self::DEFAULT_TABLE_FONT_SIZE_PX, 10, 18);
    }

    public static function tableHeaderFontSizePx(): int
    {
        $value = CompanySettings::current()?->table_header_font_size_px;

        return self::normalizePx($value, self::DEFAULT_TABLE_HEADER_FONT_SIZE_PX, 10, 18);
    }

    public static function headEndHtml(): string
    {
        $groupGap = self::groupGapPx();
        $itemGap = self::itemGapPx();
        $itemPaddingY = self::itemPaddingYPx();
        $tableCellPaddingY = self::tableCellPaddingYPx();
        $tableHeaderPaddingY = self::tableHeaderPaddingYPx();
        $tableFontSize = self::tableFontSizePx();
        $tableHeaderFontSize = self::tableHeaderFontSizePx();

        return <<<HTML
            <style>
                .fi-sidebar-nav-groups {
                    row-gap: {$groupGap}px;
                }

                .fi-sidebar-group-items,
                .fi-sidebar-sub-group-items {
                    row-gap: {$itemGap}px;
                }

                .fi-sidebar-item-btn,
                .fi-sidebar-group-btn {
                    padding-top: {$itemPaddingY}px;
                    padding-bottom: {$itemPaddingY}px;
                }

                .fi-ta-ctn .fi-ta-text:not(.fi-inline),
                .fi-ta-ctn .fi-ta-color:not(.fi-inline),
                .fi-ta-ctn .fi-ta-icon:not(.fi-inline),
                .fi-ta-ctn .fi-ta-checkbox:not(.fi-inline),
                .fi-ta-ctn .fi-ta-toggle:not(.fi-inline),
                .fi-ta-ctn .fi-ta-select:not(.fi-inline),
                .fi-ta-ctn .fi-ta-text-input:not(.fi-inline),
                .fi-ta-ctn .fi-ta-image:not(.fi-inline),
                .fi-ta-ctn .fi-ta-cell.fi-ta-summary-row-heading-cell,
                .fi-ta-ctn .fi-ta-cell.fi-ta-summary-header-cell,
                .fi-ta-ctn .fi-ta-cell.fi-ta-individual-search-cell,
                .fi-ta-ctn .fi-ta-cell:has(.fi-ta-actions),
                .fi-ta-ctn .fi-ta-cell:has(.fi-ta-record-checkbox),
                .fi-ta-ctn .fi-ta-cell.fi-ta-selection-cell,
                .fi-ta-ctn .fi-ta-summaries-value,
                .fi-ta-ctn .fi-ta-summaries-range,
                .fi-ta-ctn .fi-ta-summaries-icon-count {
                    padding-top: {$tableCellPaddingY}px !important;
                    padding-bottom: {$tableCellPaddingY}px !important;
                }

                .fi-ta-ctn .fi-ta-header-cell {
                    padding-top: {$tableHeaderPaddingY}px !important;
                    padding-bottom: {$tableHeaderPaddingY}px !important;
                }

                .fi-ta-ctn .fi-ta-text-item {
                    line-height: 1.35 !important;
                }

                .fi-ta-ctn .fi-ta-text-item,
                .fi-ta-ctn .fi-ta-placeholder,
                .fi-ta-ctn .fi-ta-summaries-value,
                .fi-ta-ctn .fi-ta-summaries-range,
                .fi-ta-ctn .fi-ta-summaries-icon-count,
                .fi-ta-ctn .fi-ta-cell.fi-ta-summary-row-heading-cell,
                .fi-ta-ctn .fi-ta-cell.fi-ta-summary-header-cell {
                    font-size: {$tableFontSize}px !important;
                }

                .fi-ta-ctn .fi-ta-header-cell,
                .fi-ta-ctn .fi-ta-header-cell .fi-ta-header-cell-sort-btn {
                    font-size: {$tableHeaderFontSize}px !important;
                }
            </style>
            HTML;
    }

    public static function normalizePx(mixed $value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
