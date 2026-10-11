<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Cancelled: customer/supplier opening balances are ledger movements, not a card column.
    }

    public function down(): void
    {
        //
    }
};
