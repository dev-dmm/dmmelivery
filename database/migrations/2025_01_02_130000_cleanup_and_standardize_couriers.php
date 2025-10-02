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
        // Get all tenants
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Delete all existing couriers for this tenant
            Courier::where('tenant_id', $tenant->id)->delete();
            
            // Create the 4 standardized couriers
            $couriers = [
                [
                    'name' => 'ACS Courier',
                    'code' => 'ACS',
                    'is_default' => true, // Make ACS the default
                    'is_active' => true,
                    'api_endpoint' => 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest',
                    'tracking_url_template' => 'https://www.acscourier.net/el/track/{tracking_number}',
                ],
                [
                    'name' => 'Speedex',
                    'code' => 'SPEEDEX',
                    'is_default' => false,
                    'is_active' => true,
                    'api_endpoint' => null,
                    'tracking_url_template' => 'https://www.speedex.gr/track/{tracking_number}',
                ],
                [
                    'name' => 'ΕΛΤΑ Courier',
                    'code' => 'ELTA',
                    'is_default' => false,
                    'is_active' => true,
                    'api_endpoint' => null,
                    'tracking_url_template' => 'https://www.elta.gr/en-us/track/{tracking_number}',
                ],
                [
                    'name' => 'Geniki Taxydromiki',
                    'code' => 'GENIKI',
                    'is_default' => false,
                    'is_active' => true,
                    'api_endpoint' => null,
                    'tracking_url_template' => 'https://www.elta.gr/en-us/track/{tracking_number}',
                ],
            ];
            
            foreach ($couriers as $courierData) {
                Courier::create([
                    'tenant_id' => $tenant->id,
                    'name' => $courierData['name'],
                    'code' => $courierData['code'],
                    'is_default' => $courierData['is_default'],
                    'is_active' => $courierData['is_active'],
                    'api_endpoint' => $courierData['api_endpoint'],
                    'tracking_url_template' => $courierData['tracking_url_template'],
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
        // Remove standardized couriers created by this migration
        Courier::whereIn('code', ['ACS', 'SPEEDEX', 'ELTA', 'GENIKI'])->delete();
    }
};
