<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Order;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configure the database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => env('DB_CONNECTION', 'mysql'),
    'host'      => env('DB_HOST', '127.0.0.1'),
    'port'      => env('DB_PORT', '3306'),
    'database'  => env('DB_DATABASE', 'forge'),
    'username'  => env('DB_USERNAME', 'forge'),
    'password'  => env('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$externalOrderId = '32489';
$tenantId = '01995808-0fff-73f2-9438-bcab51c155b8';

echo "Restoring trashed order {$externalOrderId} for tenant {$tenantId}...\n";

// Find the trashed order
$order = Order::withTrashed()
    ->where('tenant_id', $tenantId)
    ->where('external_order_id', $externalOrderId)
    ->first();

if ($order) {
    echo "Found order: ID={$order->id}, Trashed=" . ($order->trashed() ? 'YES' : 'NO') . ", DeletedAt={$order->deleted_at}\n";
    
    if ($order->trashed()) {
        $order->restore();
        echo "Order restored successfully!\n";
        echo "Order ID: {$order->id}\n";
        echo "Status: " . ($order->trashed() ? 'TRASHED' : 'ACTIVE') . "\n";
    } else {
        echo "Order is already active (not trashed).\n";
    }
} else {
    echo "Order not found.\n";
}
