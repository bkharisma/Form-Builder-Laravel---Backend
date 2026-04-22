<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
echo "Before: " . $user->name . " / " . $user->email . " / " . $user->role . "\n";
$user->name = $user->name . " X";
$user->save();

$user2 = \App\Models\User::first();
echo "After: " . $user2->name . "\n";
