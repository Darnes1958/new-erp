<?php

namespace App\Models;

class WarehouseStockReportEntry extends CompanyModel
{
    protected $table = 'warehouse_stock_report_entries';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'row_key';

    protected $keyType = 'string';
}
