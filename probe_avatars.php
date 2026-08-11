<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Storage;

foreach (User::whereNotNull('avatar_path')->get(['id', 'avatar_path']) as $user) {
    $path = $user->avatar_path;
    $exists = Storage::disk('public')->exists($path);
    echo "{$user->id} | {$path} | exists=".($exists ? 'yes' : 'no')."\n";
}
