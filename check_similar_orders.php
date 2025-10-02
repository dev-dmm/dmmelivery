<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking for orders with similar external_order_ids:\n";

// Check for orders with 32489 in the external_order_id
$orders = \App\Models\Order::where('external_order_id', 'like', '%32489%')->get(['id', 'tenant_id', 'external_order_id', 'order_number', 'created_at']);

echo "Found " . $orders->count() . " orders with '32489' in external_order_id:\n";
foreach ($orders as $order) {
    echo "ID: {$order->id}, Tenant: {$order->tenant_id}, External ID: {$order->external_order_id}, Order Number: {$order->order_number}, Created: {$order->created_at}\n";
}

echo "\nChecking for orders with tenant '01995808-0fff-73f2-9438-bcab51c155b8':\n";
$tenantOrders = \App\Models\Order::where('tenant_id', '01995808-0fff-73f2-9438-bcab51c155b8')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get(['id', 'tenant_id', 'external_order_id', 'order_number', 'created_at']);

echo "Found " . $tenantOrders->count() . " recent orders for this tenant:\n";
foreach ($tenantOrders as $order) {
    echo "ID: {$order->id}, Tenant: {$order->tenant_id}, External ID: {$order->external_order_id}, Order Number: {$order->order_number}, Created: {$order->created_at}\n";
}

echo "\nChecking for any orders created today:\n";
$todayOrders = \App\Models\Order::whereDate('created_at', today())
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get(['id', 'tenant_id', 'external_order_id', 'order_number', 'created_at']);

echo "Found " . $todayOrders->count() . " orders created today:\n";
foreach ($todayOrders as $order) {
    echo "ID: {$order->id}, Tenant: {$order->tenant_id}, External ID: {$order->external_order_id}, Order Number: {$order->order_number}, Created: {$order->created_at}\n";
}
