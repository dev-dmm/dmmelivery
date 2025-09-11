<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\Courier;
use App\Models\ShipmentStatusHistory;
use App\Models\NotificationLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing demo data to avoid conflicts
        if (\DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support foreign key checks, so we can just truncate
            \App\Models\NotificationLog::truncate();
            \App\Models\ShipmentStatusHistory::truncate();
            \App\Models\Shipment::truncate();
            \App\Models\Customer::truncate();
            \App\Models\Courier::truncate();
            \App\Models\User::truncate();
            \App\Models\Tenant::truncate();
        } else {
            // MySQL/PostgreSQL
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            \App\Models\NotificationLog::truncate();
            \App\Models\ShipmentStatusHistory::truncate();
            \App\Models\Shipment::truncate();
            \App\Models\Customer::truncate();
            \App\Models\Courier::truncate();
            \App\Models\User::truncate();
            \App\Models\Tenant::truncate();
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
        
        // Create demo eShop tenants
        $demoTenants = [
            [
                'name' => 'ElectroShop Athens',
                'subdomain' => 'electroshop',
                'branding_config' => [
                    'primary_color' => '#3B82F6',
                    'logo_url' => 'https://via.placeholder.com/200x80/3B82F6/FFFFFF?text=ElectroShop',
                ],
                'notification_settings' => [
                    'email_enabled' => true,
                    'sms_enabled' => true,
                    'send_on_pickup' => true,
                    'send_on_delivery' => true,
                ],
            ],
            [
                'name' => 'Fashion Boutique',
                'subdomain' => 'fashionboutique',
                'branding_config' => [
                    'primary_color' => '#EC4899',
                    'logo_url' => 'https://via.placeholder.com/200x80/EC4899/FFFFFF?text=Fashion+Boutique',
                ],
                'notification_settings' => [
                    'email_enabled' => true,
                    'sms_enabled' => false,
                    'send_on_pickup' => true,
                    'send_on_delivery' => true,
                ],
            ],
            [
                'name' => 'BookStore Plus',
                'subdomain' => 'bookstoreplus',
                'branding_config' => [
                    'primary_color' => '#059669',
                    'logo_url' => 'https://via.placeholder.com/200x80/059669/FFFFFF?text=BookStore+Plus',
                ],
                'notification_settings' => [
                    'email_enabled' => true,
                    'sms_enabled' => true,
                    'send_on_pickup' => false,
                    'send_on_delivery' => true,
                ],
            ],
        ];

        foreach ($demoTenants as $tenantData) {
            $tenant = Tenant::create($tenantData);

            // Create admin user for each tenant
            $user = User::create([
                'tenant_id' => $tenant->id,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => strtolower($tenantData['subdomain']) . '@demo.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]);

            // Create couriers for this tenant (Greek courier companies)
            $courierData = [
                ['name' => 'ACS Courier', 'code' => 'ACS'],
                ['name' => 'ELTA Courier', 'code' => 'ELT'],
                ['name' => 'Speedex', 'code' => 'SPX'],
                ['name' => 'Geniki Taxydromiki', 'code' => 'GTX'],
            ];

            $couriers = collect();
            foreach ($courierData as $courierInfo) {
                $courier = Courier::create([
                    'tenant_id' => $tenant->id,
                    'name' => $courierInfo['name'],
                    'code' => $courierInfo['code'],
                    'api_endpoint' => 'https://api.' . strtolower($courierInfo['code']) . '.gr/tracking',
                    'api_key' => fake()->uuid(),
                    'is_active' => true,
                    'tracking_url_template' => 'https://www.' . strtolower($courierInfo['code']) . '.gr/track/{tracking_number}',
                    'config' => [
                        'max_weight' => fake()->numberBetween(20, 50),
                        'delivery_time_days' => fake()->numberBetween(1, 5),
                        'coverage_areas' => ['Athens', 'Thessaloniki', 'Patras', 'All Greece'],
                    ],
                ]);
                $couriers->push($courier);
            }

            // Create customers for this tenant
            $customers = Customer::factory()->count(15)->create([
                'tenant_id' => $tenant->id,
            ]);

            // Create shipments with realistic status progression
            foreach ($customers as $customer) {
                $numShipments = fake()->numberBetween(1, 8);
                
                for ($i = 0; $i < $numShipments; $i++) {
                    $courier = $couriers->random();
                    
                    $shipment = Shipment::create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $customer->id,
                        'courier_id' => $courier->id,
                        'order_id' => 'ORD-' . strtoupper(fake()->bothify('######')),
                        'tracking_number' => strtoupper(fake()->bothify('??######')),
                        'courier_tracking_id' => fake()->numerify('############'),
                        'status' => 'pending',
                        'weight' => fake()->randomFloat(2, 0.1, 25.0),
                        'dimensions' => [
                            'length' => fake()->numberBetween(10, 50),
                            'width' => fake()->numberBetween(10, 30),
                            'height' => fake()->numberBetween(5, 20),
                        ],
                        'shipping_address' => fake()->streetAddress() . ', ' . fake()->city() . ', ' . fake()->postcode() . ', Greece',
                        'billing_address' => fake()->optional(0.7)->streetAddress() . ', ' . fake()->city(),
                        'shipping_cost' => fake()->randomFloat(2, 2.50, 25.00),
                        'estimated_delivery' => fake()->dateTimeBetween('+1 day', '+7 days'),
                        'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
                    ]);

                    // Create realistic status progression
                    $this->createStatusHistory($shipment);
                    
                    // Create notifications for status changes
                    $this->createNotifications($shipment, $customer);
                }
            }
        }

        $this->command->info('Demo data created successfully!');
        $this->command->info('Login credentials:');
        foreach ($demoTenants as $tenant) {
            $this->command->info('- ' . $tenant['name'] . ': ' . strtolower($tenant['subdomain']) . '@demo.com / password');
        }
    }

    private function createStatusHistory(Shipment $shipment): void
    {
        $statusProgression = [
            ['status' => 'pending', 'description' => 'Order received and being prepared'],
            ['status' => 'picked_up', 'description' => 'Package picked up by courier'],
            ['status' => 'in_transit', 'description' => 'Package is in transit'],
            ['status' => 'out_for_delivery', 'description' => 'Package is out for delivery'],
        ];

        $finalStatuses = [
            ['status' => 'delivered', 'description' => 'Package delivered successfully'],
            ['status' => 'returned', 'description' => 'Package returned to sender'],
            ['status' => 'failed', 'description' => 'Delivery failed - customer unavailable'],
        ];

        $createdAt = $shipment->created_at;
        $currentTime = $createdAt;
        
        // Add initial status
        foreach ($statusProgression as $index => $statusData) {
            $hours = fake()->numberBetween(2, 24);
            $endTime = (clone $currentTime)->add(new \DateInterval("PT{$hours}H"));
            $currentTime = fake()->dateTimeBetween($currentTime, $endTime);
            
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'status' => $statusData['status'],
                'description' => $statusData['description'],
                'location' => fake()->randomElement(['Athens Hub', 'Thessaloniki Center', 'Local Facility', 'Transit Point']),
                'happened_at' => $currentTime,
                'courier_response' => [
                    'tracking_event' => strtoupper($statusData['status']),
                    'facility' => fake()->randomElement(['Main Hub', 'Distribution Center', 'Local Branch']),
                ],
            ]);

            // Update shipment status
            $shipment->update(['status' => $statusData['status']]);

            // 70% chance to continue to next status
            if (fake()->boolean(70) && $index < count($statusProgression) - 1) {
                continue;
            } else {
                break;
            }
        }

        // Maybe add final status (60% chance)
        if (fake()->boolean(60)) {
            $finalStatus = fake()->randomElement($finalStatuses);
            $hours = fake()->numberBetween(1, 12);
            $endTime = (clone $currentTime)->add(new \DateInterval("PT{$hours}H"));
            $currentTime = fake()->dateTimeBetween($currentTime, $endTime);
            
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'status' => $finalStatus['status'],
                'description' => $finalStatus['description'],
                'location' => fake()->randomElement(['Customer Location', 'Delivery Point', 'Return Center']),
                'happened_at' => $currentTime,
                'courier_response' => [
                    'tracking_event' => strtoupper($finalStatus['status']),
                    'delivery_confirmed' => $finalStatus['status'] === 'delivered',
                ],
            ]);

            $shipment->update([
                'status' => $finalStatus['status'],
                'actual_delivery' => $finalStatus['status'] === 'delivered' ? $currentTime : null,
            ]);
        }
    }

    private function createNotifications(Shipment $shipment, Customer $customer): void
    {
        $statusHistories = $shipment->statusHistory;
        
        foreach ($statusHistories as $history) {
            // Skip some notifications randomly to simulate real scenarios
            if (fake()->boolean(30)) continue;

            $channel = fake()->randomElement(['email', 'sms']);
            $recipient = $channel === 'email' ? $customer->email : $customer->phone;

            if (!$recipient) continue;

            NotificationLog::create([
                'tenant_id' => $shipment->tenant_id,
                'shipment_id' => $shipment->id,
                'customer_id' => $customer->id,
                'type' => 'status_update',
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $channel === 'email' ? "Shipment Update - {$shipment->tracking_number}" : null,
                'message' => "Your package status: {$history->status}. {$history->description}",
                'status' => fake()->randomElement(['sent', 'delivered', 'pending']),
                'sent_at' => $history->happened_at,
                'metadata' => [
                    'tracking_number' => $shipment->tracking_number,
                    'status_changed_to' => $history->status,
                ],
            ]);
        }
    }
}
