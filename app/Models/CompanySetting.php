<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'company';

    protected $keyType = 'string';

    protected $fillable = [
        'company',
        'has_expiry_dates',
        'has_dual_unit',
        'multi_warehouse',
        'wholesale_retail',
        'barcode_enabled',
        'link_sales_to_installments',
        'installment_by_payroll_bank',
        'auto_price_update',
        'user_message',
        'alert_message',
        'sidebar_group_gap_px',
        'sidebar_item_gap_px',
        'sidebar_item_padding_y_px',
        'table_cell_padding_y_px',
        'table_header_padding_y_px',
        'table_font_size_px',
        'table_header_font_size_px',
    ];

    protected function casts(): array
    {
        return [
            'has_expiry_dates' => 'boolean',
            'has_dual_unit' => 'boolean',
            'multi_warehouse' => 'boolean',
            'wholesale_retail' => 'boolean',
            'barcode_enabled' => 'boolean',
            'link_sales_to_installments' => 'boolean',
            'installment_by_payroll_bank' => 'boolean',
            'auto_price_update' => 'boolean',
        ];
    }
}
