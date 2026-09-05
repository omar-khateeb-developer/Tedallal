<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\User::where('email', 'super@tedallal.test')->first();

echo "--- Users API ---\n";
$request1 = \Illuminate\Http\Request::create('/api/super-admin/users', 'GET');
$request1->setUserResolver(fn() => $admin);
$controller1 = app(\App\Http\Controllers\Api\SuperAdmin\UserController::class);
try {
    $response1 = $controller1->index();
    echo $response1->getContent() . "\n";
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

echo "--- Restaurants API ---\n";
$request2 = \Illuminate\Http\Request::create('/api/super-admin/restaurants', 'GET');
$request2->setUserResolver(fn() => $admin);
$controller2 = app(\App\Http\Controllers\Api\SuperAdmin\RestaurantController::class);
try {
    $response2 = $controller2->index();
    echo $response2->getContent() . "\n";
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
