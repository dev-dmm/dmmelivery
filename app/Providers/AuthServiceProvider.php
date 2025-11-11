<?php

namespace App\Providers;

use App\Models\Shipment;
use App\Models\Tenant;
use App\Policies\ShipmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Shipment::class => ShipmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Global bypass for super admins (defense in depth)
        // This works in conjunction with Policy::before() methods
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            return null; // Continue to policy checks
        });

        // Define Gates for frontend abilities
        Gate::define('manage-users', function ($user) {
            return $user->isAdmin();
        });
        
        Gate::define('manage-tenants', function ($user) {
            return $user->isSuperAdmin();
        });
        
        Gate::define('view-reports', function ($user) {
            return $user->isAdmin() || $user->isUser();
        });
        
        Gate::define('access-super-admin', function ($user) {
            return $user->isSuperAdmin();
        });
        
        Gate::define('manage-settings', function ($user) {
            return $user->isAdmin();
        });
        
        // Gate for viewing global customer scores (defense in depth)
        Gate::define('view-global-scores', function ($user, Tenant $tenant) {
            return (bool) $tenant->can_view_global_scores;
        });
    }
}
