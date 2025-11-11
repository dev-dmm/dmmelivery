<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get tenant for policy checking (defense in depth)
        $tenant = $request->attributes->get('tenant') ?? $request->user()?->tenant ?? app('tenant');
        $canViewGlobal = (bool) ($tenant?->can_view_global_scores ?? false);
        
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'notes' => $this->notes,
            'delivery_score' => $this->delivery_score ?? 0,
            'has_enough_data' => $this->hasEnoughData(),
            'success_rate_range' => $this->getSuccessRangeString(),
            'success_percentage' => $this->getSuccessPercentage(),
            'score_status' => $this->getDeliveryScoreStatus(),
            'is_risky' => $this->isRisky(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
        
        // Add global score information only if tenant has permission (defense in depth)
        if ($canViewGlobal && $this->globalCustomer) {
            $gc = $this->globalCustomer;
            $data['global'] = [
                'score' => $gc->getGlobalDeliveryScore(),
                'score_status' => $gc->getGlobalDeliveryScoreStatus(),
                'success_percentage' => $gc->getGlobalSuccessPercentage(),
                'completed_shipments' => $gc->completedShipmentsCount(),
                'delivered_shipments' => $gc->deliveredShipmentsCount(),
            ];
        } else {
            $data['global'] = ['enabled' => false];
        }
        
        return $data;
    }
}
