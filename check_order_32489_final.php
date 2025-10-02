<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking for order 32489:\n";

$orders = \App\Models\Order::where('external_order_id', '32489')->get(['id', 'tenant_id', 'external_order_id', 'order_number', 'created_at']);

echo "Found " . $orders->count() . " orders:\n";
foreach ($orders as $order) {
    echo "ID: {$order->id}, Tenant: {$order->tenant_id}, External ID: {$order->external_order_id}, Order Number: {$order->order_number}, Created: {$order->created_at}\n";
}

echo "\nChecking for tenant '01995808-0fff-73f2-9438-bcab51c155b8' with external_order_id '32489':\n";
$tenantOrders = \App\Models\Order::where('tenant_id', '01995808-0fff-73f2-9438-bcab51c155b8')
    ->where('external_order_id', '32489')
    ->get(['id', 'tenant_id', 'external_order_id', 'order_number', 'created_at']);

echo "Found " . $tenantOrders->count() . " orders for this tenant:\n";
foreach ($tenantOrders as $order) {
    echo "ID: {$order->id}, Tenant: {$order->tenant_id}, External ID: {$order->external_order_id}, Order Number: {$order->order_number}, Created: {$order->created_at}\n";
}

echo "\nChecking for any orders created in the last 10 minutes:\n";
$recentOrders = \App\Models\Order::where('created_at', '>=', now()->subMinutes(10))
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get(['id', 'tenant_id', 'external_order_id', 'order_number', 'created_at']);

echo "Found " . $recentOrders->count() . " recent orders:\n";
foreach ($recentOrders as $order) {
    echo "ID: {$order->id}, Tenant: {$order->tenant_id}, External ID: {$order->external_order_id}, Order Number: {$order->order_number}, Created: {$order->created_at}\n";
}
