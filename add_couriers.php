<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tenant;
use App\Models\Courier;

echo "Adding couriers...\n";

// Get or create a tenant
$tenant = Tenant::first();
if (!$tenant) {
    echo "No tenant found. Creating default tenant...\n";
    $tenant = Tenant::create([
        'name' => 'Default Tenant',
        'domain' => 'localhost',
        'is_active' => true,
    ]);
    echo "Created tenant: {$tenant->id}\n";
}

// Check if couriers already exist
$existingCouriers = Courier::where('tenant_id', $tenant->id)->count();
if ($existingCouriers > 0) {
    echo "Couriers already exist for tenant {$tenant->id}\n";
    exit;
}

// Create ACS Courier
$acs = Courier::create([
    'tenant_id' => $tenant->id,
    'name' => 'ACS Courier',
    'code' => 'ACS',
    'is_default' => true,
    'is_active' => true,
    'api_endpoint' => 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest',
    'tracking_url_template' => 'https://www.acscourier.net/el/track/{tracking_number}',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "Created ACS Courier: {$acs->id}\n";

// Create Geniki Taxidromiki
$geniki = Courier::create([
    'tenant_id' => $tenant->id,
    'name' => 'Geniki Taxidromiki',
    'code' => 'GENIKI',
    'is_default' => false,
    'is_active' => true,
    'api_endpoint' => null,
    'tracking_url_template' => 'https://www.elta.gr/en-us/track/{tracking_number}',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "Created Geniki Taxidromiki: {$geniki->id}\n";

// Create ELTA Hellenic Post
$elta = Courier::create([
    'tenant_id' => $tenant->id,
    'name' => 'ELTA Hellenic Post',
    'code' => 'ELTA',
    'is_default' => false,
    'is_active' => true,
    'api_endpoint' => null,
    'tracking_url_template' => 'https://www.elta.gr/en-us/track/{tracking_number}',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "Created ELTA Hellenic Post: {$elta->id}\n";

echo "All couriers created successfully!\n";
