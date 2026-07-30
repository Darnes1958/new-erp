<?php

namespace Database\Seeders;

use App\Database\Migrations\Concerns\MigratesCompanyDatabases;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    use MigratesCompanyDatabases;

    public function run(): void
    {
        $methods = [
            ['id' => 1, 'name' => 'نقدي', 'code' => 'cash'],
            ['id' => 2, 'name' => 'بنكي', 'code' => 'bank'],
            ['id' => 3, 'name' => 'تقسيط', 'code' => 'installment'],
        ];

        $now = now()->format('Y-m-d H:i:s.v');

        foreach ($this->companyConnections() as $connection) {
            DB::connection($connection)->transaction(function () use ($connection, $methods, $now): void {
                DB::connection($connection)->unprepared('SET IDENTITY_INSERT payment_methods ON');

                foreach ($methods as $method) {
                    $exists = DB::connection($connection)
                        ->table('payment_methods')
                        ->where('id', $method['id'])
                        ->exists();

                    if ($exists) {
                        DB::connection($connection)
                            ->table('payment_methods')
                            ->where('id', $method['id'])
                            ->update([
                                'name' => $method['name'],
                                'code' => $method['code'],
                                'updated_at' => $now,
                            ]);
                    } else {
                        DB::connection($connection)
                            ->table('payment_methods')
                            ->insert([
                                'id' => $method['id'],
                                'name' => $method['name'],
                                'code' => $method['code'],
                                'rate' => 0,
                                'adjustment_value' => 0,
                                'adjustment_direction' => 0,
                                'is_active' => true,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                    }
                }

                DB::connection($connection)->unprepared('SET IDENTITY_INSERT payment_methods OFF');
            });
        }
    }
}
