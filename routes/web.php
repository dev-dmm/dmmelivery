<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// -----------------------------
// Route Model Binding
// -----------------------------

// Bind {shipment} safely to current tenant to prevent IDOR
Route::bind('shipment', function ($id) {
    // Get tenant from authenticated user instead of service container
    // since middleware hasn't run yet when route model binding executes
    $user = auth()->user();
    if (!$user || !$user->tenant_id) {
        abort(403, 'Tenant not identified');
    }

    return \App\Models\Shipment::query()
        ->where('id', $id)
        ->where('tenant_id', $user->tenant_id)
        ->firstOrFail();
});

// Bind {customer} safely to current tenant to prevent IDOR
Route::bind('customer', function ($id) {
    $user = auth()->user();
    if (!$user || !$user->tenant_id) {
        abort(403, 'Tenant not identified');
    }

    return \App\Models\Customer::query()
        ->where('id', $id)
        ->where('tenant_id', $user->tenant_id)
        ->firstOrFail();
});

// -----------------------------
// Feature-based Route Files
// -----------------------------

// Public routes
require __DIR__.'/web/public.php';

// Registration routes
require __DIR__.'/web/registration.php';

// Profile routes (auth only, no tenant)
require __DIR__.'/web/profile.php';

// Onboarding routes
require __DIR__.'/web/onboarding.php';

// Dashboard routes
require __DIR__.'/web/dashboard.php';

// Analytics routes
require __DIR__.'/web/analytics.php';

// Shipment routes
require __DIR__.'/web/shipments.php';

// Customer routes
require __DIR__.'/web/customers.php';

// Order routes
require __DIR__.'/web/orders.php';

// Settings routes
require __DIR__.'/web/settings.php';

// Courier reports routes
require __DIR__.'/web/courier-reports.php';

// Predictive ETA routes
require __DIR__.'/web/predictive-eta.php';

// Alert routes
require __DIR__.'/web/alerts.php';

// Chatbot routes
require __DIR__.'/web/chatbot.php';

// Super admin routes
require __DIR__.'/web/super-admin.php';

// Help & support routes
require __DIR__.'/web/help.php';

// Notifications routes
require __DIR__.'/web/notifications.php';

// Users routes
require __DIR__.'/web/users.php';

// Debug routes (development only - consider removing in production)
if (app()->environment(['local', 'testing'])) {
    require __DIR__.'/web/debug.php';
}

// -----------------------------
// Authentication Routes
// -----------------------------
require __DIR__.'/auth.php';
