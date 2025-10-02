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

echo "Testing lookup for order {$externalOrderId} with tenant {$tenantId}:\n\n";

// Test 1: withoutGlobalScopes() + withTrashed()
echo "1. Testing withoutGlobalScopes() + withTrashed():\n";
$order1 = Order::withoutGlobalScopes()
    ->withTrashed()
    ->where('tenant_id', $tenantId)
    ->where('external_order_id', $externalOrderId)
    ->first();

if ($order1) {
    echo "   Found: ID={$order1->id}, Trashed=" . ($order1->trashed() ? 'YES' : 'NO') . ", DeletedAt={$order1->deleted_at}\n";
} else {
    echo "   Not found\n";
}

// Test 2: withoutGlobalScope(TenantScope::class) + withTrashed()
echo "\n2. Testing withoutGlobalScope(TenantScope::class) + withTrashed():\n";
$order2 = Order::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
    ->withTrashed()
    ->where('tenant_id', $tenantId)
    ->where('external_order_id', $externalOrderId)
    ->first();

if ($order2) {
    echo "   Found: ID={$order2->id}, Trashed=" . ($order2->trashed() ? 'YES' : 'NO') . ", DeletedAt={$order2->deleted_at}\n";
} else {
    echo "   Not found\n";
}

// Test 3: Raw query
echo "\n3. Testing raw query:\n";
$order3 = Capsule::table('orders')
    ->where('external_order_id', $externalOrderId)
    ->where('tenant_id', $tenantId)
    ->first();

if ($order3) {
    echo "   Found: ID={$order3->id}, DeletedAt={$order3->deleted_at}\n";
} else {
    echo "   Not found\n";
}
