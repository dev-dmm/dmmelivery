<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Shipment;
use App\Models\Customer;

class DeliveryScoreUpdated
{
    use Dispatchable, SerializesModels;

    public Shipment $shipment;
    public Customer $customer;
    public int $delta;
    public string $reason;
    public int $newScore;
    public string $correlationId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Shipment $shipment,
        Customer $customer,
        int $delta,
        string $reason,
        int $newScore,
        string $correlationId = ''
    ) {
        $this->shipment = $shipment;
        $this->customer = $customer;
        $this->delta = $delta;
        $this->reason = $reason;
        $this->newScore = $newScore;
        $this->correlationId = $correlationId;
    }
}
