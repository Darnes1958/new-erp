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
        'BenTaher_erp',
        'Elmaleh_erp',
        'Motahedon_erp',
        'Elhrer',
        'Electro_erp'
    ],

    /*
    |--------------------------------------------------------------------------
    | Central ERP database connection (users, our_companies, company_settings)
    |--------------------------------------------------------------------------
    |
    | Must stay stable while company migrations temporarily switch the default
    | connection to a company database.
    |
    */
    'central_connection' => env('DB_CONNECTION', 'sqlsrv'),

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
    | Legacy ERP central database (InsFila — users, settings, OurCompany)
    |--------------------------------------------------------------------------
    |
    | Used by erp:convert-auth and erp:convert for the intermediate ERP schema.
    | Not used for INS conversion (INS uses ins_central_connection / useradmin).
    |
    */
    'legacy_auth_connection' => env('ERP_LEGACY_AUTH_CONNECTION', 'InsFila'),

    /*
    |--------------------------------------------------------------------------
    | Legacy admin database (useradmin — excel_setings, company_tajmeehies)
    |--------------------------------------------------------------------------
    |
    | Shared SQL Server admin DB. For INS this is also the central registry
    | (Customers, users, roles). Same connection as ins_central_connection.
    |
    */
    'legacy_admin_connection' => env('ERP_LEGACY_ADMIN_CONNECTION', 'useradmin'),

    /*
    |--------------------------------------------------------------------------
    | INS central database (useradmin — Customers, users, permissions)
    |--------------------------------------------------------------------------
    |
    | INS has no InsFila. Company registry lives in useradmin.dbo.Customers
    | (Company = connection name, e.g. BenTaher).
    |
    */
    'ins_central_connection' => env('ERP_INS_CENTRAL_CONNECTION', 'useradmin'),

    /*
    |--------------------------------------------------------------------------
    | INS / ERP conversion naming
    |--------------------------------------------------------------------------
    |
    | Legacy SQL databases keep their original names (BenTaher, Motafoek, …).
    | The new ERP target connection gets a suffix/prefix so the old name is never lost.
    |
    | Example with suffix "_erp":
    |   BenTaher (legacy INS, read-only)  ->  BenTaher_erp (new ERP target)
    |
    */
    'conversion' => [
        'target_name_mode' => env('ERP_CONVERT_TARGET_NAME_MODE', 'suffix'),
        'target_name_suffix' => env('ERP_CONVERT_TARGET_SUFFIX', '_erp'),
        'target_name_prefix' => env('ERP_CONVERT_TARGET_PREFIX', ''),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | SQL Server backup disk path
    |--------------------------------------------------------------------------
    |
    | Path must be writable by the SQL Server service account (not only PHP).
    | Defaults to storage/app like the legacy ERP backup.
    |
    */
    'backup_disk_path' => env('ERP_BACKUP_DISK_PATH', storage_path('app')),

];
