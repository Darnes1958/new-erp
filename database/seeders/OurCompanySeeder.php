<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OurCompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['connection_name' => 'testERP', 'display_name' => 'Test ERP'],
            ['connection_name' => 'Motafoek', 'display_name' => 'مطافئ'],
        ];

        foreach ($companies as $company) {
            DB::table('our_companies')->updateOrInsert(
                ['connection_name' => $company['connection_name']],
                array_merge($company, ['is_active' => true]),
            );
        }
    }
}
