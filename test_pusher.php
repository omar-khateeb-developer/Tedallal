<?php

$config = config('broadcasting.connections.pusher');
echo 'Pusher Key: ' . $config['key'] . "\n";
echo 'Pusher App ID: ' . $config['app_id'] . "\n";
echo 'Pusher Cluster: ' . $config['options']['cluster'] . "\n";
try {
    $pusher = new \Pusher\Pusher($config['key'], $config['secret'], $config['app_id'], $config['options']);
    $response = $pusher->trigger('private-restaurant.1.orders', 'order.created', ['message' => 'test_push']);
    echo 'Pusher Trigger Response: ' . json_encode($response) . "\n";
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
