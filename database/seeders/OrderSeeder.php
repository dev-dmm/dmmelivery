<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\Customer;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Get customers for this tenant
            $customers = Customer::where('tenant_id', $tenant->id)->get();
            
            if ($customers->isEmpty()) {
                // Create some customers if none exist
                $customers = collect([
                    Customer::create([
                        'tenant_id' => $tenant->id,
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'phone' => '+30 210 123 4567',
                        'address' => 'Patission 76, Athens 10434, Greece',
                    ]),
                    Customer::create([
                        'tenant_id' => $tenant->id,
                        'name' => 'Maria Papadopoulou',
                        'email' => 'maria@example.com',
                        'phone' => '+30 210 987 6543',
                        'address' => 'Ermou 25, Thessaloniki 54623, Greece',
                    ]),
                ]);
            }

            // Create 10-15 orders per tenant
            $orderCount = rand(10, 15);
            
            for ($i = 1; $i <= $orderCount; $i++) {
                $customer = $customers->random();
                $orderDate = now()->subDays(rand(0, 30));
                
                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'external_order_id' => 'WC-' . rand(1000, 9999),
                    'order_number' => strtoupper($tenant->subdomain) . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                    'import_source' => 'woocommerce',
                    'status' => $this->getRandomStatus(),
                    
                    // Customer Information
                    'customer_name' => $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    
                    // Shipping Address
                    'shipping_address' => $customer->address,
                    'shipping_city' => $this->getCityFromAddress($customer->address),
                    'shipping_postal_code' => $this->getPostalCodeFromAddress($customer->address),
                    'shipping_country' => 'GR',
                    
                    // Order Totals
                    'subtotal' => $subtotal = rand(2000, 15000) / 100, // €20-150
                    'tax_amount' => round($subtotal * 0.24, 2), // 24% VAT
                    'shipping_cost' => rand(300, 800) / 100, // €3-8
                    'discount_amount' => rand(0, 1000) / 100, // €0-10
                    'total_amount' => $subtotal + round($subtotal * 0.24, 2) + rand(300, 800) / 100 - rand(0, 1000) / 100,
                    'currency' => 'EUR',
                    
                    // Payment Information
                    'payment_status' => rand(0, 1) ? 'paid' : 'pending',
                    'payment_method' => $this->getRandomPaymentMethod(),
                    
                    // Shipping Preferences
                    'preferred_courier' => null,
                    'shipping_method' => 'standard',
                    'requires_signature' => rand(0, 1),
                    'fragile_items' => rand(0, 1),
                    'total_weight' => rand(50, 2000) / 100, // 0.5-20kg
                    
                    // Order Dates
                    'order_date' => $orderDate,
                    'expected_ship_date' => $orderDate->copy()->addDays(rand(1, 3)),
                    
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                // Create 1-5 order items
                $itemCount = rand(1, 5);
                for ($j = 1; $j <= $itemCount; $j++) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'tenant_id' => $tenant->id,
                        'product_sku' => 'SKU-' . rand(1000, 9999),
                        'product_name' => $this->getRandomProductName(),
                        'quantity' => rand(1, 3),
                        'unit_price' => rand(1000, 8000) / 100, // €10-80
                        'final_unit_price' => rand(1000, 8000) / 100,
                        'total_price' => rand(1000, 8000) / 100 * rand(1, 3),
                        'weight' => rand(10, 500) / 100, // 0.1-5kg
                        'is_digital' => false,
                        'is_fragile' => rand(0, 1),
                    ]);
                }
            }
        }

        $this->command->info('Sample orders created successfully!');
    }

    private function getRandomStatus(): string
    {
        $statuses = ['pending', 'processing', 'ready_to_ship', 'shipped', 'delivered', 'cancelled'];
        $weights = [30, 25, 15, 15, 10, 5]; // Higher chance for pending/processing
        
        $rand = rand(1, 100);
        $cumulative = 0;
        
        foreach ($weights as $index => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $statuses[$index];
            }
        }
        
        return 'pending';
    }

    private function getRandomPaymentMethod(): string
    {
        $methods = ['credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery'];
        return $methods[array_rand($methods)];
    }

    private function getRandomProductName(): string
    {
        $products = [
            'Wireless Bluetooth Headphones',
            'Smartphone Case',
            'USB-C Cable',
            'Portable Power Bank',
            'Wireless Charger',
            'Bluetooth Speaker',
            'Phone Stand',
            'Screen Protector',
            'Car Charger',
            'Memory Card',
            'Laptop Bag',
            'Mouse Pad',
            'Keyboard',
            'Computer Mouse',
            'Webcam',
        ];
        
        return $products[array_rand($products)];
    }

    private function getCityFromAddress(string $address): string
    {
        if (strpos($address, 'Athens') !== false) return 'Athens';
        if (strpos($address, 'Thessaloniki') !== false) return 'Thessaloniki';
        return 'Athens';
    }

    private function getPostalCodeFromAddress(string $address): string
    {
        if (preg_match('/(\d{5})/', $address, $matches)) {
            return $matches[1];
        }
        return '10434';
    }
}