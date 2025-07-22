<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        $types = ['status_update', 'delivery_notification', 'delay_alert', 'custom'];
        $channels = ['email', 'sms'];
        $type = $this->faker->randomElement($types);
        $channel = $this->faker->randomElement($channels);
        
        $messages = [
            'status_update' => [
                'email' => 'Your package status has been updated. Current status: {status}',
                'sms' => 'Package update: Your order is now {status}. Track: {url}',
            ],
            'delivery_notification' => [
                'email' => 'Great news! Your package has been delivered successfully.',
                'sms' => 'Delivered! Your package arrived safely. Thank you for your order.',
            ],
            'delay_alert' => [
                'email' => 'We wanted to notify you about a slight delay in your delivery.',
                'sms' => 'Delivery delay: Your package is delayed but will arrive soon. Sorry for the inconvenience.',
            ],
        ];

        $recipient = $channel === 'email' 
            ? $this->faker->safeEmail() 
            : $this->faker->e164PhoneNumber();

        return [
            'type' => $type,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $channel === 'email' ? $this->faker->sentence(6) : null,
            'message' => $messages[$type][$channel] ?? $this->faker->sentence(),
            'status' => $this->faker->randomElement(['sent', 'delivered', 'pending', 'failed']),
            'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'error_message' => $this->faker->optional(0.1)->sentence(),
            'metadata' => [
                'template_id' => $this->faker->uuid(),
                'retry_count' => $this->faker->numberBetween(0, 3),
            ],
        ];
    }
}
