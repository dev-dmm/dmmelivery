<?php

namespace Database\Factories;

use App\Models\Courier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourierFactory extends Factory
{
    protected $model = Courier::class;

    public function definition(): array
    {
        $courierNames = ['ACS', 'ELTA Courier', 'Speedex', 'UPS', 'DHL', 'FedEx'];
        $name = $this->faker->randomElement($courierNames);
        
        return [
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'api_endpoint' => 'https://api.' . strtolower($name) . '.com/tracking',
            'api_key' => $this->faker->optional(0.7)->uuid(),
            'is_active' => $this->faker->boolean(85),
            'tracking_url_template' => 'https://tracking.' . strtolower($name) . '.com/{tracking_number}',
            'config' => [
                'max_weight' => $this->faker->numberBetween(1, 50),
                'delivery_time_days' => $this->faker->numberBetween(1, 7),
                'coverage_areas' => $this->faker->randomElements(['Athens', 'Thessaloniki', 'Patras', 'Larisa'], 3),
            ],
        ];
    }
}
