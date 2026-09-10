<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = \App\Models\User::where('email', 'admin@tedallal.test')->first();
auth()->login($u);

try {
    app(\App\Services\RestaurantContext::class)->current();
    echo "Success";
} catch(\Exception $e) {
    echo "Error: " . $e->getMessage();
}
