<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'external_order_id' => 'WC-' . $this->faker->numerify('####'),
            'order_number' => strtoupper($this->faker->bothify('???-####')),
            'import_source' => 'woocommerce',
            'status' => $this->faker->randomElement(['pending', 'processing', 'ready_to_ship', 'shipped', 'delivered', 'cancelled']),
            
            // Customer Information
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'customer_phone' => $this->faker->phoneNumber(),
            
            // Shipping Address
            'shipping_address' => $this->faker->streetAddress(),
            'shipping_city' => $this->faker->city(),
            'shipping_postal_code' => $this->faker->postcode(),
            'shipping_country' => 'GR',
            
            // Billing Address (optional)
            'billing_address' => $this->faker->optional()->streetAddress(),
            'billing_city' => $this->faker->optional()->city(),
            'billing_postal_code' => $this->faker->optional()->postcode(),
            'billing_country' => 'GR',
            
            // Order Totals
            'subtotal' => $subtotal = $this->faker->randomFloat(2, 20, 150),
            'tax_amount' => round($subtotal * 0.24, 2), // 24% VAT
            'shipping_cost' => $this->faker->randomFloat(2, 3, 8),
            'discount_amount' => $this->faker->optional(0.3)->randomFloat(2, 0, 10),
            'total_amount' => $subtotal + round($subtotal * 0.24, 2) + $this->faker->randomFloat(2, 3, 8),
            'currency' => 'EUR',
            
            // Payment Information
            'payment_status' => $this->faker->randomElement(['paid', 'pending', 'failed']),
            'payment_method' => $this->faker->randomElement(['credit_card', 'bank_transfer', 'cash_on_delivery']),
            
            // Shipping Preferences
            'preferred_courier' => null,
            'shipping_method' => 'standard',
            'requires_signature' => $this->faker->boolean(20),
            'fragile_items' => $this->faker->boolean(10),
            'total_weight' => $this->faker->randomFloat(3, 0.1, 25.0),
            
            // Order Dates
            'order_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'expected_ship_date' => $this->faker->optional()->dateTimeBetween('now', '+7 days'),
        ];
    }
}
