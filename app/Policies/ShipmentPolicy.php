<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShipmentPolicy
{
    use HandlesAuthorization;

    /**
     * Global bypass for super admins.
     * Super admins can perform any action on any shipment.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null; // Continue to policy methods
    }

    /**
     * Determine whether the user can view any shipments.
     */
    public function viewAny(User $user): bool
    {
        // User can view shipments if they have a tenant
        return !empty($user->tenant_id);
    }

    /**
     * Determine whether the user can view the shipment.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        // User can only view shipments that belong to their tenant
        return $user->tenant_id === $shipment->tenant_id;
    }

    /**
     * Determine whether the user can create shipments.
     */
    public function create(User $user): bool
    {
        // User can create shipments if they have a tenant
        return !empty($user->tenant_id);
    }

    /**
     * Determine whether the user can update the shipment.
     */
    public function update(User $user, Shipment $shipment): bool
    {
        // User can only update shipments that belong to their tenant
        return $user->tenant_id === $shipment->tenant_id;
    }

    /**
     * Determine whether the user can delete the shipment.
     */
    public function delete(User $user, Shipment $shipment): bool
    {
        // User can only delete shipments that belong to their tenant
        return $user->tenant_id === $shipment->tenant_id;
    }

    /**
     * Determine whether the user can restore the shipment.
     */
    public function restore(User $user, Shipment $shipment): bool
    {
        // User can only restore shipments that belong to their tenant
        return $user->tenant_id === $shipment->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the shipment.
     * Usually disabled in multi-tenant environments for data integrity.
     */
    public function forceDelete(User $user, Shipment $shipment): bool
    {
        // Force delete is typically disabled in multi-tenant environments
        // Only super admins can force delete (handled by before() method)
        return false;
    }
}
