<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tracking_number'      => $this->tracking_number,
            'order_id'             => $this->order_id,
            'status'               => $this->status ?? 'unknown',
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),
            'estimated_delivery'   => $this->estimated_delivery?->format('Y-m-d H:i:s'),
            'actual_delivery'      => $this->actual_delivery?->format('Y-m-d H:i:s'),
            'shipping_address'     => $this->shipping_address,
            'billing_address'      => $this->billing_address,
            'recipient_name'       => $this->customer?->name ?? null, // Derive from customer relationship
            'weight'               => $this->weight,
            'shipping_cost'        => $this->shipping_cost,
            'courier_tracking_id'  => $this->courier_tracking_id,

            // Related data - only when loaded
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id'    => $this->customer->id,
                    'name'  => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                ];
            }),

            'courier' => $this->whenLoaded('courier', function () {
                return [
                    'id'                     => $this->courier->id,
                    'name'                   => $this->courier->name,
                    'code'                   => $this->courier->code,
                    'tracking_url_template'  => $this->courier->tracking_url_template,
                ];
            }),

            // Optional recent updates if you ever eager-load statusHistory
            'recent_status_updates' => $this->whenLoaded('statusHistory', function () {
                return $this->statusHistory->take(5)->map(function ($status) {
                    return [
                        'status'      => $status->status,
                        'happened_at' => $status->happened_at?->format('Y-m-d H:i:s'),
                        'location'    => $status->location,
                        'notes'       => $status->notes,
                    ];
                });
            }),
        ];
    }
}
