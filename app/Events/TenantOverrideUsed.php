<?php

namespace App\Events;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;

/**
 * TenantOverrideUsed Event
 * 
 * Fired when a super-admin uses the tenant override feature.
 * Listeners can use this to:
 * - Send notifications/alerts
 * - Track override usage metrics
 * - Enforce additional security checks
 */
class TenantOverrideUsed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ?User $admin,
        public Tenant $tenant,
        public Request $request
    ) {
    }
}

