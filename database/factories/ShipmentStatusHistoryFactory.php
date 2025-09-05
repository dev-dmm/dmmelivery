<?php

namespace Database\Factories;

use App\Models\ShipmentStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentStatusHistoryFactory extends Factory
{
    protected $model = ShipmentStatusHistory::class;

    public function definition(): array
    {
        $statuses = [
            'pending' => 'Order received and being prepared',
            'picked_up' => 'Package picked up by courier',
            'in_transit' => 'Package is in transit',
            'out_for_delivery' => 'Package is out for delivery',
            'delivered' => 'Package has been delivered',
            'failed' => 'Delivery attempt failed',
            'returned' => 'Package returned to sender',
        ];

        $status = $this->faker->randomElement(array_keys($statuses));
        $locations = ['Athens Distribution Center', 'Thessaloniki Hub', 'Patras Facility', 'Local Courier Van', 'Destination City'];

        return [
            'status' => $status,
            'description' => $statuses[$status],
            'location' => $this->faker->randomElement($locations),
            'happened_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'courier_response' => $this->faker->optional(0.6)->randomElement([
                ['tracking_event' => 'SCAN', 'facility' => 'Distribution Center'],
                ['tracking_event' => 'TRANSIT', 'next_stop' => 'Local Hub'],
                ['tracking_event' => 'DELIVERY_ATTEMPT', 'result' => 'successful'],
            ]),
        ];
    }
}
