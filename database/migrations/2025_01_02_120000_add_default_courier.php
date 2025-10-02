<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;
use App\Models\Courier;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all tenants and create real couriers for each
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Check if tenant already has couriers
            $existingCouriers = Courier::where('tenant_id', $tenant->id)->count();
            
            if ($existingCouriers == 0) {
                // Create ACS Courier
                Courier::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'ACS Courier',
                    'code' => 'ACS',
                    'is_default' => true, // Make ACS the default
                    'is_active' => true,
                    'api_endpoint' => 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest',
                    'tracking_url_template' => 'https://www.acscourier.net/el/track/{tracking_number}',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Create Geniki Taxidromiki
                Courier::create([
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
                
                // Create ELTA Hellenic Post
                Courier::create([
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
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove couriers created by this migration
        Courier::whereIn('code', ['ACS', 'GENIKI', 'ELTA'])->delete();
    }
};
