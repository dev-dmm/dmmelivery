<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

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

echo "Checking for order with external_order_id '{$externalOrderId}' and tenant_id '{$tenantId}':\n";

// Check for any orders (including trashed)
$orders = Capsule::table('orders')
    ->where('external_order_id', $externalOrderId)
    ->where('tenant_id', $tenantId)
    ->get();

if ($orders->count() > 0) {
    echo "Found {$orders->count()} orders:\n";
    foreach ($orders as $order) {
        echo "ID: {$order->id}, External ID: {$order->external_order_id}, Tenant: {$order->tenant_id}, Deleted: " . ($order->deleted_at ? 'YES (' . $order->deleted_at . ')' : 'NO') . ", Created: {$order->created_at}\n";
    }
} else {
    echo "Found 0 orders.\n";
}

echo "\nChecking unique constraint:\n";
$pdo = Capsule::connection()->getPdo();
$stmt = $pdo->query("SHOW INDEX FROM orders WHERE Key_name = 'orders_external_order_id_tenant_id_unique';");
$indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($indexes as $index) {
    echo "Index: {$index['Key_name']}, Column: {$index['Column_name']}, Unique: " . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "\n";
}
