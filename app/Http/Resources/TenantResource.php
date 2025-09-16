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
            'id'            => $this->id,
            'name'          => $this->name,
            'business_name' => $this->business_name, // Dashboard uses this fallback
            // DO NOT expose config, secrets, keys, or whole settings blobs here.
            // Add branding fields ONLY if you render them on the UI:
            // 'logo_url' => $this->logo_url,
        ];
    }
}