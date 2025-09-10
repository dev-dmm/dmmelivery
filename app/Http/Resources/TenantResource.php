<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
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
            'name' => $this->name,
            'subdomain' => $this->subdomain,
            'branding' => [
                'primary_color' => $this->branding_config['primary_color'] ?? '#3B82F6',
                'logo_url' => $this->logo_url,
                'favicon_url' => $this->favicon_url,
            ],
            // Only include onboarding info if not complete
            'onboarding' => $this->when(
                !$this->isOnboardingComplete(),
                [
                    'status' => $this->onboarding_status,
                    'progress' => $this->getOnboardingProgress(),
                    'next_step' => $this->getNextOnboardingStep(),
                ]
            ),
            // Basic feature flags (not internal settings)
            'features' => [
                'can_create_shipments' => $this->canCreateShipments(),
                'remaining_shipments' => $this->getRemainingShipments(),
            ],
        ];
    }
}