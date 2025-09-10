<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class UserResource extends JsonResource
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
            // Only include email if user is viewing their own profile or is admin
            'email' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->isAdmin(),
                $this->email
            ),
            // Role as boolean abilities using Gates
            'abilities' => [
                'is_admin' => $this->isAdmin(),
                'is_super_admin' => $this->isSuperAdmin(),
                'can_manage_users' => Gate::forUser($this)->allows('manage-users'),
                'can_view_settings' => Gate::forUser($this)->allows('view-reports'),
            ],
            // Only include tenant_id if needed for routing logic
            'tenant_id' => $this->when(
                $request->user()?->isSuperAdmin() || $request->routeIs('super-admin.*'),
                $this->tenant_id
            ),
        ];
    }
}