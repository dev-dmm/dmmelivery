<?php
// Temporary script to check orphaned orders
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking for orphaned orders ===\n";

$orphanedOrders = DB::table('orders as o')
    ->leftJoin('tenants as t', 't.id', '=', 'o.tenant_id')
    ->whereNull('t.id')
    ->select('o.tenant_id', DB::raw('count(*) as orders'))
    ->groupBy('o.tenant_id')
    ->get();

echo "Found " . $orphanedOrders->count() . " orphaned tenant_ids:\n\n";

foreach ($orphanedOrders as $row) {
    echo "Tenant ID: {$row->tenant_id} has {$row->orders} orders\n";
}

echo "\n=== Existing tenants ===\n";

$existingTenants = DB::table('tenants')
    ->select('id', 'name', 'subdomain')
    ->get();

foreach ($existingTenants as $tenant) {
    echo "Tenant ID: {$tenant->id} - {$tenant->name} ({$tenant->subdomain})\n";
}

echo "\n=== Orphaned shipments ===\n";

$orphanedShipments = DB::table('shipments as s')
    ->leftJoin('tenants as t', 't.id', '=', 's.tenant_id')
    ->whereNull('t.id')
    ->select('s.tenant_id', DB::raw('count(*) as shipments'))
    ->groupBy('s.tenant_id')
    ->get();

foreach ($orphanedShipments as $row) {
    echo "Tenant ID: {$row->tenant_id} has {$row->shipments} shipments\n";
}
