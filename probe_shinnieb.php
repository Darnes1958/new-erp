<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$central = config('erp.central_connection', config('database.default'));

$user = User::query()->where('email', 'shinnieb@shinnieb')->first();

if (! $user) {
    echo "User not found\n";
    exit(1);
}

echo 'User ID: '.$user->id."\n";
echo 'Name: '.$user->name."\n";
echo 'Company: '.$user->company."\n";
echo 'is_prog: '.json_encode($user->is_prog)."\n";
echo 'status: '.$user->status."\n";
echo 'connection: '.$user->getConnectionName()."\n\n";

echo 'hasRole(admin): '.json_encode($user->hasRole('admin'))."\n";
echo 'roles: '.json_encode($user->getRoleNames()->all(), JSON_UNESCAPED_UNICODE)."\n";
echo 'can(تقارير): '.json_encode($user->can('تقارير'))."\n";
echo 'can(ادخال مبيعات): '.json_encode($user->can('ادخال مبيعات'))."\n\n";

$roles = DB::connection($central)
    ->table('model_has_roles')
    ->where('model_type', User::class)
    ->where('model_id', $user->id)
    ->get();

echo "model_has_roles:\n";
echo json_encode($roles, JSON_PRETTY_PRINT)."\n\n";

$allRoles = DB::connection($central)->table('roles')->get(['id', 'name', 'guard_name', 'for_who']);
echo "all roles:\n";
echo json_encode($allRoles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
