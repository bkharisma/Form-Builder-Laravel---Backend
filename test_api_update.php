<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
$controller = app()->make(\App\Http\Controllers\Api\AdminUserController::class);

$request = \Illuminate\Http\Request::create('/api/admin-users/' . $user->id, 'PUT', [
    'name' => 'New Name',
    'email' => $user->email,
    'role' => 'admin'
]);
try {
    $response = $controller->update($request, $user);
    echo $response->getContent();
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Validation error: " . json_encode($e->errors());
}
