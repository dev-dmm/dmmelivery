<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking for trashed orders with external_order_id '32489':\n";

// Check with soft deletes (including trashed)
$trashedOrders = \App\Models\Order::withTrashed()
    ->where('tenant_id', '01995808-0fff-73f2-9438-bcab51c155b8')
    ->where('external_order_id', '32489')
    ->get(['id', 'tenant_id', 'external_order_id', 'deleted_at', 'created_at']);

echo "Found " . $trashedOrders->count() . " orders (including trashed):\n";
foreach ($trashedOrders as $order) {
    $status = $order->trashed() ? 'TRASHED' : 'ACTIVE';
    echo "ID: {$order->id}, Tenant: {$order->tenant_id}, External ID: {$order->external_order_id}, Status: {$status}, Deleted: {$order->deleted_at}, Created: {$order->created_at}\n";
}

echo "\nChecking table structure:\n";
$tableInfo = \DB::select("SHOW CREATE TABLE orders");
echo $tableInfo[0]->{'Create Table'} . "\n";

echo "\nChecking indexes:\n";
$indexes = \DB::select("SHOW INDEX FROM orders");
foreach ($indexes as $index) {
    if ($index->Key_name !== 'PRIMARY') {
        echo "Index: {$index->Key_name}, Column: {$index->Column_name}, Unique: " . ($index->Non_unique ? 'NO' : 'YES') . "\n";
    }
}
