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
use App\Http\Controllers\OrderImportController;

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

    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
        // Don’t leak versions in production
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
        Route::post('/profile',    [OnboardingController::class, 'updateProfile'])->name('profile.update');

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
    Route::post('/settings/api/generate',                   [SettingsController::class, 'generateApiToken'])->name('settings.api.generate');
    Route::post('/settings/webhooks',                       [SettingsController::class, 'updateWebhooks'])->name('settings.webhooks.update');

    // Performance view
    Route::get('/courier-performance', [DashboardController::class, 'courierPerformance'])
        ->name('courier.performance');

    // Shipments (IDOR protection via model binding + policy)
    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');

    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])
    ->middleware('can:view,shipment')  // ✅ Security maintained via policy
    ->name('shipments.show');
    // Orders Import (constrain params + throttle)
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/import',                 [OrderImportController::class, 'index'])->name('import.index');
        Route::post('/import/upload',         [OrderImportController::class, 'uploadFile'])->name('import.upload');
        Route::post('/import/api',            [OrderImportController::class, 'importFromApi'])->name('import.api');

        Route::get('/import/{importId}/status',  [OrderImportController::class, 'getStatus'])
            ->whereUuid('importId')->name('import.status');

        Route::get('/import/{importId}/details', [OrderImportController::class, 'getDetails'])
            ->whereUuid('importId')->name('import.details');

        Route::post('/import/{importId}/cancel', [OrderImportController::class, 'cancel'])
            ->whereUuid('importId')->name('import.cancel');

        Route::post('/import/{importId}/retry',  [OrderImportController::class, 'retry'])
            ->whereUuid('importId')->name('import.retry');

        Route::delete('/import/{importId}',      [OrderImportController::class, 'delete'])
            ->whereUuid('importId')->name('import.delete');

        Route::get('/import/template/{format}',  [OrderImportController::class, 'downloadTemplate'])
            ->whereIn('format', ['csv','xlsx','excel','xml','json']) // ← add these
            ->name('import.template');

        Route::post('/import/field-mapping',     [OrderImportController::class, 'getFieldMapping'])
            ->name('import.field-mapping');
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
require __DIR__.'/auth.php';
