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
        'Motafoek',
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

];
