<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Tenant;

echo "Creating admin@dmm.gr user...\n";

// Get the tenant
$tenant = Tenant::find('bf2396ab-9493-11f0-90ce-00505640c844');
if (!$tenant) {
    echo "Tenant not found!\n";
    exit(1);
}

// Create the user
$user = User::create([
    'first_name' => 'Admin',
    'last_name' => 'User',
    'email' => 'admin@dmm.gr',
    'password' => bcrypt('password123'),
    'role' => 'super_admin',
    'tenant_id' => $tenant->id,
    'email_verified_at' => now(),
]);

echo "User created successfully!\n";
echo "Email: admin@dmm.gr\n";
echo "Password: password123\n";
echo "User ID: " . $user->id . "\n";
echo "Tenant: " . $tenant->name . "\n";
