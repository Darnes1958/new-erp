<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company database connections
    |--------------------------------------------------------------------------
    |
    | Each key must match a connection name in config/database.php and the
    | users.company column. Company migrations run on every connection here.
    |
    */
    'company_connections' => [
        'testERP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment method codes (stable identifiers for business logic)
    |--------------------------------------------------------------------------
    */
    'payment_method_codes' => [
        'cash' => 1,
        'bank' => 2,
        'installment' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Number display locale (Libya — Western digits 0-9)
    |--------------------------------------------------------------------------
    |
    | Arabic locales (ar, ar_LY) render Eastern digits (٠-٩) by default.
    | en_US keeps Western digits with comma thousands and period decimals
    | (e.g. 1,234.567).
    |
    */
    'number_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Legacy central database (users, settings, permissions)
    |--------------------------------------------------------------------------
    */
    'legacy_auth_connection' => env('ERP_LEGACY_AUTH_CONNECTION', 'InsFila'),

    /*
    |--------------------------------------------------------------------------
    | Installment schedule
    |--------------------------------------------------------------------------
    |
    | Denormalized contract fields (next_installment_date, late_amount, …) are
    | recalculated by InstallmentContractMetricsService. late_amount is refreshed
    | monthly via erp:refresh-installment-late-counts.
    |
    */
    'installment_due_day' => 28,

];
