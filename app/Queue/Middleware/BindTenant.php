<?php

namespace App\Queue\Middleware;

use App\Events\TenantCleared;
use App\Events\TenantResolved;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Log;

/**
 * BindTenant Queue Middleware
 * 
 * Re-binds tenant context for queued jobs that were dispatched with a tenant.
 * Ensures tenant isolation in background jobs.
 * 
 * Usage: Add to your job's middleware() method:
 * 
 * public function middleware(): array
 * {
 *     return [new \App\Queue\Middleware\BindTenant()];
 * }
 */
class BindTenant
{
    /**
     * Process the queued job.
     *
     * @param Job $job
     * @param Closure $next
     * @return mixed
     */
    public function handle(Job $job, Closure $next)
    {
        $tenantId = $this->getTenantIdFromJob($job);
        
        if ($tenantId) {
            $tenant = Tenant::query()->active()->find($tenantId);
            
            if ($tenant) {
                // Bind tenant to container
                app()->instance('tenant', $tenant);
                
                // Add tenant context to logs
                Log::withContext(['tenant_id' => $tenant->getKey()]);
                
                // Fire event for listeners
                event(new TenantResolved($tenant));
            }
        }

        try {
            return $next($job);
        } finally {
            // Always clean up tenant context after job execution
            if (app()->has('tenant')) {
                $tenant = app('tenant');
                app()->forgetInstance('tenant');
                
                // Remove only tenant_id from context (preserve other context)
                Log::withContext(['tenant_id' => null]);
                
                // Fire cleanup event
                event(new TenantCleared($tenant ?? null));
            }
        }
    }

    /**
     * Extract tenant ID from job payload.
     * 
     * Supports multiple job property patterns:
     * - $job->tenantId (explicit property)
     * - $job->tenant_id (snake_case property)
     * - Payload data with tenant_id key
     * 
     * @param Job $job
     * @return string|null
     */
    protected function getTenantIdFromJob(Job $job): ?string
    {
        // Try explicit property first
        if (property_exists($job, 'tenantId') && $job->tenantId) {
            return $job->tenantId;
        }
        
        if (property_exists($job, 'tenant_id') && $job->tenant_id) {
            return $job->tenant_id;
        }
        
        // Try payload data
        $payload = $job->payload();
        if (isset($payload['data']['tenant_id'])) {
            return $payload['data']['tenant_id'];
        }
        
        // Try command data (for command jobs)
        if (isset($payload['data']['commandName'])) {
            $command = unserialize($payload['data']['command']);
            if (property_exists($command, 'tenantId')) {
                return $command->tenantId;
            }
        }
        
        return null;
    }
}

