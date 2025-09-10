<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'estimated_delivery' => $this->estimated_delivery?->format('Y-m-d'),
            'shipping_address' => $this->shipping_address,
            'recipient_name' => $this->recipient_name,
            
            // Related data - only load if requested
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                ];
            }),
            
            'courier' => $this->whenLoaded('courier', function () {
                return [
                    'id' => $this->courier->id,
                    'name' => $this->courier->name,
                    'code' => $this->courier->code,
                ];
            }),
            
            // Status history - only recent ones for performance
            'recent_status_updates' => $this->whenLoaded('statusHistory', function () {
                return $this->statusHistory->take(5)->map(function ($status) {
                    return [
                        'status' => $status->status,
                        'happened_at' => $status->happened_at?->format('Y-m-d H:i:s'),
                        'location' => $status->location,
                    ];
                });
            }),
        ];
    }
}