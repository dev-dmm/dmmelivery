<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShipmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the shipment.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        // User can only view shipments that belong to their tenant
        return $user->tenant_id === $shipment->tenant_id;
    }

    /**
     * Determine whether the user can view any shipments.
     */
    public function viewAny(User $user): bool
    {
        // User can view shipments if they have a tenant
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can create shipments.
     */
    public function create(User $user): bool
    {
        // User can create shipments if they have an active tenant
        return $user->tenant_id !== null && $user->tenant && $user->tenant->is_active;
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
}
