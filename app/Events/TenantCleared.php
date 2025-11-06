<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * TenantCleared Event
 * 
 * Fired when a tenant is cleared from the application context.
 * Listeners can use this to:
 * - Reset database connections to default
 * - Clear tenant-specific caches
 * - Clean up tenant-specific resources
 */
class TenantCleared
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ?Tenant $tenant = null
    ) {
    }
}

