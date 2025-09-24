<?php

namespace Database\Factories;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        $statuses = ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'returned'];
        $status = $this->faker->randomElement($statuses);
        
        $createdAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $estimatedDelivery = $this->faker->dateTimeBetween($createdAt, '+7 days');
        
        $actualDelivery = null;
        if (in_array($status, ['delivered', 'returned'])) {
            $actualDelivery = $this->faker->dateTimeBetween($createdAt, $estimatedDelivery);
        }

        return [
            'order_id' => 'ORD-' . Str::upper($this->faker->bothify('######')),
            'tracking_number' => $this->generateRealisticTrackingNumber(),
            'courier_tracking_id' => $this->generateCourierTrackingId(),
            'status' => $status,
            'weight' => $this->faker->randomFloat(2, 0.1, 25.0),
            'dimensions' => [
                'length' => $this->faker->numberBetween(10, 50),
                'width' => $this->faker->numberBetween(10, 30),
                'height' => $this->faker->numberBetween(5, 20),
            ],
            'shipping_address' => $this->faker->streetAddress() . ', ' . $this->faker->city() . ', ' . $this->faker->postcode(),
            'billing_address' => $this->faker->optional(0.7)->streetAddress() . ', ' . $this->faker->city(),
            'shipping_cost' => $this->faker->randomFloat(2, 2.50, 25.00),
            'estimated_delivery' => $estimatedDelivery,
            'actual_delivery' => $actualDelivery,
            'courier_response' => $this->faker->optional(0.5)->randomElement([
                ['last_update' => now()->subHours(2)->toISOString(), 'location' => 'Distribution Center'],
                ['last_update' => now()->subHours(5)->toISOString(), 'location' => 'In Transit'],
            ]),
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Generate realistic tracking number
     */
    private function generateRealisticTrackingNumber(): string
    {
        $couriers = ['ACS', 'SPX', 'ELT', 'GTX', 'DHL', 'FDX', 'UPS'];
        $courier = $this->faker->randomElement($couriers);
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 8));
        return $courier . $date . $random;
    }

    /**
     * Generate courier tracking ID
     */
    private function generateCourierTrackingId(): string
    {
        $couriers = ['ACS', 'SPX', 'ELT', 'GTX', 'DHL', 'FDX', 'UPS'];
        $courier = $this->faker->randomElement($couriers);
        return $courier . $this->faker->numerify('########');
    }
}
