<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ACSTestController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\OrderController;

// -----------------------------
// Feature flags / helpers
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

// -----------------------------
// Public landing
// -----------------------------
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Home', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
        // Don't leak versions in production
        'meta'        => app()->isLocal()
            ? ['laravel' => Application::VERSION, 'php' => PHP_VERSION]
            : null,
    ]);
});

// -----------------------------
// Registration & Email Verification (throttled + signed)
// -----------------------------
Route::prefix('register')
    ->name('registration.')
    ->middleware('throttle:20,1')
    ->group(function () {
        Route::get('/', [TenantRegistrationController::class, 'showRegistrationForm'])->name('form');
        Route::post('/', [TenantRegistrationController::class, 'register'])->name('submit');

        Route::get('/email-verification', [TenantRegistrationController::class, 'showEmailVerification'])
            ->name('email-verification');

        // Signed URL + throttle to mitigate token abuse/leaks
        Route::get('/verify-email/{token}', [TenantRegistrationController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verify-email');

        Route::post('/check-subdomain', [TenantRegistrationController::class, 'checkSubdomain'])
            ->middleware('throttle:30,1')
            ->name('check-subdomain');
    });

// -----------------------------
// Authenticated profile (no tenant required)
// -----------------------------
Route::middleware(['auth'])->group(function () {
    // Profile
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',[ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',[ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Temporary: API token generation for local development (no tenant middleware)
    Route::post('/settings/api/generate', [SettingsController::class, 'generateApiToken'])->name('settings.api.generate');
});

// -----------------------------
// Onboarding (must be authenticated, verified, and tenant-identified)
// -----------------------------
Route::prefix('onboarding')
    ->name('onboarding.')
    ->middleware(['auth', 'verified', 'identify.tenant', 'throttle:60,1'])
    ->group(function () {
        Route::get('/welcome',     [OnboardingController::class, 'welcome'])->name('welcome');

        Route::get('/profile',     [OnboardingController::class, 'profile'])->name('profile');
        Route::post('/profile',    [OnboardingController::class, 'updateProfile'])->name('onboarding.profile.update');

        Route::get('/branding',    [OnboardingController::class, 'branding'])->name('branding');
        Route::post('/branding',   [OnboardingController::class, 'updateBranding'])->name('branding.update');

        Route::get('/api-config',  [OnboardingController::class, 'apiConfig'])->name('api-config');
        Route::post('/api-config', [OnboardingController::class, 'updateApiConfig'])->name('api-config.update');

        Route::get('/testing',     [OnboardingController::class, 'testing'])->name('testing');
        Route::post('/test-api',   [OnboardingController::class, 'testApi'])->name('test-api');

        Route::post('/complete',   [OnboardingController::class, 'complete'])->name('complete');
    });

// -----------------------------
// Tenant-scoped app routes
// -----------------------------
Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Settings (keep sensitive actions POST/DELETE and CSRF-protected)
    Route::get('/settings',                                 [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/business',                       [SettingsController::class, 'updateBusiness'])->name('settings.business.update');
    Route::post('/settings/courier/acs',                    [SettingsController::class, 'updateACSCredentials'])->name('settings.courier.acs.update');
    Route::post('/settings/courier/test',                   [SettingsController::class, 'testCourierConnection'])->name('settings.courier.test');
    Route::post('/settings/courier/update',                 [SettingsController::class, 'updateCourierCredentials'])->name('settings.courier.update');
    Route::delete('/settings/courier/delete',               [SettingsController::class, 'deleteCourierCredentials'])->name('settings.courier.delete');
    // Route::post('/settings/api/generate',                   [SettingsController::class, 'generateApiToken'])->name('settings.api.generate'); // Moved to auth-only group for local dev
    Route::post('/settings/webhooks',                       [SettingsController::class, 'updateWebhooks'])->name('settings.webhooks.update');

    // Performance view
    Route::get('/courier-performance', [DashboardController::class, 'courierPerformance'])
        ->name('courier.performance');

    // Simple debug route to check basic order data (tenant scoped only)
    Route::get('/debug-orders', function (Request $request) {
        $user     = $request->user();
        $tenantId = app('tenant')->id ?? $user->tenant_id ?? null;

        // Base query - tenant scoped only (no user filtering)
        $ordersQ = \App\Models\Order::query()
            ->where('tenant_id', $tenantId);

        $orders = (clone $ordersQ)->take(5)->get([
            // include only columns that exist
            'id',
            ...(Schema::hasColumn('orders', 'shipping_city') ? ['shipping_city'] : []),
            'shipping_address',
            'created_at',
        ]);

        $totalOrders = (clone $ordersQ)->count();

        return response()->json([
            'tenant_id'     => $tenantId,
            'user_id'       => $user->id,
            'scope'         => 'tenant',  // explicitly tenant-only
            'total_orders'  => $totalOrders,
            'sample_orders' => $orders,
        ]);
    })->name('debug.orders');

    // Debug route to check shipments data (tenant + user scoped)
    Route::get('/debug-shipments', function (Request $request) {
        $tenant   = app('tenant') ?? $request->user()?->tenant;
        abort_unless($tenant, 403, 'No tenant in context.');
    
        $tenantId = $tenant->id;
    
        $shipQ = \App\Models\Shipment::query()
            ->where('tenant_id', $tenantId);
    
        $shipments = (clone $shipQ)->take(5)->get([
            'id',
            'shipping_address',
            'status',
            'created_at',
        ]);
    
        return response()->json([
            'tenant_id'        => $tenantId,
            'scope'            => 'tenant',  // explicitly tenant-only
            'total_shipments'  => (clone $shipQ)->count(),
            'sample_shipments' => $shipments,
        ]);
    })->name('debug.shipments');

    // Debug route to check areas data (derived from ORDERS, tenant scoped only)
    Route::get('/debug-areas', function (Request $request) {
        $user     = $request->user();
        $tenantId = app('tenant')->id ?? $user->tenant_id ?? null;

        // Base orders query - tenant scoped only (no user filtering)
        $ordersQ = \App\Models\Order::query()
            ->where('tenant_id', $tenantId);

        // Areas from city column (only if it exists)
        $areasFromCityField = [];
        if (Schema::hasColumn('orders', 'shipping_city')) {
            $areasFromCityField = (clone $ordersQ)
                ->whereNotNull('shipping_city')
                ->where('shipping_city', '!=', '')
                ->distinct()
                ->pluck('shipping_city')
                ->toArray();
        }

        // Areas from address parsing
        $areasFromAddress = (clone $ordersQ)
            ->whereNotNull('shipping_address')
            ->where('shipping_address', '!=', '')
            ->get(['shipping_address'])
            ->map(function ($order) {
                $address = (string) $order->shipping_address;
                $parts = array_values(array_filter(
                    array_map('trim', explode(',', $address)),
                    fn ($p) => $p !== ''
                ));

                if (count($parts) >= 2) {
                    $countries = [
                        'greece','gr','united states','usa','us',
                        'uk','united kingdom','italy','de','germany',
                        'france','es','spain'
                    ];
                    $lastLower = mb_strtolower(end($parts));

                    if (in_array($lastLower, $countries, true) && count($parts) >= 2) {
                        $city = $parts[count($parts) - 2];
                    } else {
                        $city = end($parts);
                        if (preg_match('/^\d{3,6}(-\d{2,6})?$/', $city)) {
                            $city = (count($parts) >= 2) ? $parts[count($parts) - 2] : $city;
                        }
                    }

                    $city = trim($city);
                    if ($city !== '' && !in_array(mb_strtolower($city), $countries, true)) {
                        return $city;
                    }
                }
                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Sample orders (only columns that exist)
        $sampleCols = ['shipping_address'];
        if (Schema::hasColumn('orders', 'shipping_city')) {
            $sampleCols[] = 'shipping_city';
        }

        $sampleOrders = (clone $ordersQ)->take(5)->get($sampleCols);

        return response()->json([
            'tenant_id'             => $tenantId,
            'user_id'               => $user->id,
            'scope'                 => 'tenant',  // explicitly tenant-only
            'total_orders'          => (clone $ordersQ)->count(),
            'orders_with_city'      => Schema::hasColumn('orders', 'shipping_city')
                ? (clone $ordersQ)->whereNotNull('shipping_city')->count()
                : 0,
            'areas_from_city_field' => $areasFromCityField,
            'areas_from_address'    => $areasFromAddress,
            'sample_orders'         => $sampleOrders,
        ]);
    })->name('debug.areas');

    // Orders (tenant-scoped)
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Shipments (IDOR protection via model binding + policy)
    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');

    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])
    ->middleware('can:view,shipment')  // ✅ Security maintained via policy
    ->name('shipments.show');

    // Courier Reports Import (constrain params + throttle)
    Route::prefix('courier-reports')->name('courier-reports.')->group(function () {
        Route::get('/import',                 [\App\Http\Controllers\CourierReportImportController::class, 'index'])->name('import.index');
        Route::post('/import/upload',         [\App\Http\Controllers\CourierReportImportController::class, 'uploadFile'])->name('import.upload');

        Route::get('/import/{uuid}/status',   [\App\Http\Controllers\CourierReportImportController::class, 'getStatus'])
            ->whereUuid('uuid')->name('import.status');

        Route::get('/import/{uuid}/details',  [\App\Http\Controllers\CourierReportImportController::class, 'getDetails'])
            ->whereUuid('uuid')->name('import.details');

        Route::post('/import/{uuid}/cancel',  [\App\Http\Controllers\CourierReportImportController::class, 'cancel'])
            ->whereUuid('uuid')->name('import.cancel');

        Route::delete('/import/{uuid}',       [\App\Http\Controllers\CourierReportImportController::class, 'delete'])
            ->whereUuid('uuid')->name('import.delete');

        Route::get('/import/template/{format}', [\App\Http\Controllers\CourierReportImportController::class, 'downloadTemplate'])
            ->whereIn('format', ['csv','xlsx','xls'])
            ->name('import.template');
    });

    Route::middleware('throttle:30,1')->group(function () {
            Route::get('/test/courier-api', function () {
                $sampleShipments = \App\Models\Shipment::query()
                    ->with('courier:id,name,code')
                    ->where('tenant_id', app('tenant')->id)
                    ->latest('id')
                    ->take(5)
                    ->get(['id', 'tracking_number', 'courier_tracking_id', 'status', 'courier_id']);

                return Inertia::render('Test/CourierApi', [
                    'sampleShipments' => $sampleShipments,
                ]);
            })->name('test.courier-api');

            Route::get('/test/acs-credentials', fn () => Inertia::render('Test/ACSCredentials'))
                ->name('test.acs-credentials');
        });
});

// -----------------------------
// API: test courier (protected; no reflection; tenant-scoped; throttled)
// -----------------------------
// If you already have a controller, swap the closure for it.
Route::middleware(['auth', 'verified', 'identify.tenant', 'throttle:30,1'])
    ->post('/api/test/courier-api', function (Request $request) {
        $request->validate([
            'tracking_number' => ['required', 'string', 'max:64'],
        ]);

        $tenantId = app('tenant')->id;

        $shipment = \App\Models\Shipment::query()
            ->where(function ($q) use ($request) {
                $q->where('tracking_number', $request->tracking_number)
                  ->orWhere('courier_tracking_id', $request->tracking_number);
            })
            ->where('tenant_id', $tenantId)
            ->with(['courier', 'statusHistory' => fn($q) => $q->orderByDesc('happened_at')->limit(10)])
            ->first();

        if (!$shipment) {
            return response()->json(['error' => 'Shipment not found'], 404);
        }

        // Authorization safety net (policy should also check tenant ownership)
        $user = $request->user();
        if ($user && method_exists($user, 'can')) {
            abort_unless($user->can('view', $shipment), 403);
        }

        if (!$shipment->courier || !$shipment->courier->api_endpoint) {
            return response()->json(['error' => 'Courier does not support API integration'], 400);
        }

        try {
            // Prefer a service or a queued job (sync dispatch for testing)
            if (class_exists(\App\Jobs\FetchCourierStatuses::class)) {
                \App\Jobs\FetchCourierStatuses::dispatchSync($shipment->id);
            } else {
                // Fallback: no-op to avoid reflection into private methods
                \Log::warning('FetchCourierStatuses job missing; skipping live fetch.');
            }

            $shipment->refresh()->load(['statusHistory' => fn($q) => $q->orderByDesc('happened_at')->limit(10)]);

            // Return minimal, tenant-safe payload
            return response()->json([
                'success'  => true,
                'shipment' => [
                    'id'               => $shipment->id,
                    'tracking_number'  => $shipment->tracking_number,
                    'status'           => $shipment->status,
                    'courier'          => $shipment->courier ? ['id' => $shipment->courier->id, 'name' => $shipment->courier->name, 'code' => $shipment->courier->code] : null,
                    'history'          => $shipment->statusHistory->map(fn($h) => [
                        'status' => $h->status,
                        'note'   => $h->note,
                        'at'     => $h->happened_at,
                    ]),
                ],
                'message' => 'API test completed.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Courier API test error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'API test failed'], 500);
        }
    })
    ->name('api.test.courier-api');

// -----------------------------
// ACS credentials APIs (POST only; mask values; tenant + throttle)
// -----------------------------
Route::middleware(['auth', 'verified', 'identify.tenant', 'throttle:30,1'])->group(function () {
    // DO NOT expose secrets via GET; keep POST and return masked data
    Route::post('/api/acs/get-credentials', [ACSTestController::class, 'getCredentials'])->name('api.acs.get');
    Route::post('/api/acs/update-credentials', [ACSTestController::class, 'updateCredentials'])->name('api.acs.update');
});

// -----------------------------
// Super Admin Routes
// -----------------------------
Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware(['auth', 'verified', 'super.admin', 'throttle:60,1'])
    ->group(function () {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [SuperAdminController::class, 'orders'])->name('orders');
        Route::get('/order-items', [SuperAdminController::class, 'orderItems'])->name('order-items');
        Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants');
        Route::get('/tenants/{tenant}', [SuperAdminController::class, 'tenantDetails'])->name('tenants.show');
        
        // User Management
        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.update-role');
        Route::patch('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    });

// -----------------------------
require __DIR__.'/auth.php';
