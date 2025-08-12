<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use App\Http\Controllers\DashboardController; 
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ACSTestController;
use Illuminate\Support\Facades\Auth;


Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// 🏢 eShop Registration & Onboarding Routes
Route::prefix('register')->name('registration.')->group(function () {
    Route::get('/', [TenantRegistrationController::class, 'showRegistrationForm'])->name('form');
    Route::post('/', [TenantRegistrationController::class, 'register'])->name('submit');
    Route::get('/email-verification', [TenantRegistrationController::class, 'showEmailVerification'])->name('email-verification');
    Route::get('/verify-email/{token}', [TenantRegistrationController::class, 'verifyEmail'])->name('verify-email');
    Route::post('/check-subdomain', [TenantRegistrationController::class, 'checkSubdomain'])->name('check-subdomain');
});

// 🚀 Onboarding Process Routes  
Route::prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/welcome', [OnboardingController::class, 'welcome'])->name('welcome');
    
    Route::get('/profile', [OnboardingController::class, 'profile'])->name('profile');
    Route::post('/profile', [OnboardingController::class, 'updateProfile'])->name('profile.update');
    
    Route::get('/branding', [OnboardingController::class, 'branding'])->name('branding');
    Route::post('/branding', [OnboardingController::class, 'updateBranding'])->name('branding.update');
    
    Route::get('/api-config', [OnboardingController::class, 'apiConfig'])->name('api-config');
    Route::post('/api-config', [OnboardingController::class, 'updateApiConfig'])->name('api-config.update');
    
    Route::get('/testing', [OnboardingController::class, 'testing'])->name('testing');
    Route::post('/test-api', [OnboardingController::class, 'testApi'])->name('test-api');
    
    Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Settings Routes
    Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/business', [App\Http\Controllers\SettingsController::class, 'updateBusiness'])->name('settings.business.update');
    Route::post('/settings/courier/acs', [App\Http\Controllers\SettingsController::class, 'updateACSCredentials'])->name('settings.courier.acs.update');
    Route::post('/settings/courier/test', [App\Http\Controllers\SettingsController::class, 'testCourierConnection'])->name('settings.courier.test');
    Route::post('/settings/courier/update', [App\Http\Controllers\SettingsController::class, 'updateCourierCredentials'])->name('settings.courier.update');
    Route::delete('/settings/courier/delete', [App\Http\Controllers\SettingsController::class, 'deleteCourierCredentials'])->name('settings.courier.delete');
    Route::post('/settings/api/generate', [App\Http\Controllers\SettingsController::class, 'generateApiToken'])->name('settings.api.generate');
    Route::post('/settings/webhooks', [App\Http\Controllers\SettingsController::class, 'updateWebhooks'])->name('settings.webhooks.update');
});

Route::middleware(['auth', 'verified', 'identify.tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/courier-performance', [DashboardController::class, 'courierPerformance'])->name('courier.performance');
    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
    
    // Order Import Routes
    Route::prefix('orders')->group(function () {
        Route::get('/import', [App\Http\Controllers\OrderImportController::class, 'index'])
            ->name('orders.import.index');
        Route::post('/import/upload', [App\Http\Controllers\OrderImportController::class, 'uploadFile'])
            ->name('orders.import.upload');
        Route::post('/import/api', [App\Http\Controllers\OrderImportController::class, 'importFromApi'])
            ->name('orders.import.api');
        Route::get('/import/{importId}/status', [App\Http\Controllers\OrderImportController::class, 'getStatus'])
            ->name('orders.import.status');
        Route::get('/import/{importId}/details', [App\Http\Controllers\OrderImportController::class, 'getDetails'])
            ->name('orders.import.details');
        Route::post('/import/{importId}/cancel', [App\Http\Controllers\OrderImportController::class, 'cancel'])
            ->name('orders.import.cancel');
        Route::post('/import/{importId}/retry', [App\Http\Controllers\OrderImportController::class, 'retry'])
            ->name('orders.import.retry');
        Route::delete('/import/{importId}', [App\Http\Controllers\OrderImportController::class, 'delete'])
            ->name('orders.import.delete');
        Route::get('/import/template/{format}', [App\Http\Controllers\OrderImportController::class, 'downloadTemplate'])
            ->name('orders.import.template');
        Route::post('/import/field-mapping', [App\Http\Controllers\OrderImportController::class, 'getFieldMapping'])
            ->name('orders.import.field-mapping');
    });
    
    // Test routes for API integration
    Route::get('/test/courier-api', function() {
        // Get some sample tracking numbers for testing
        $sampleShipments = \App\Models\Shipment::with('courier:id,name,code')
            ->take(5)
            ->get(['id', 'tracking_number', 'courier_tracking_id', 'status', 'courier_id']);
            
        return \Inertia\Inertia::render('Test/CourierApi', [
            'sampleShipments' => $sampleShipments
        ]);
    })->name('test.courier-api');
    
    // ACS Credentials Test Page
    Route::get('/test/acs-credentials', function() {
        return \Inertia\Inertia::render('Test/ACSCredentials');
    })->name('test.acs-credentials');
});

// API route for testing (no CSRF protection)
Route::post('/api/test/courier-api', function(\Illuminate\Http\Request $request) {
    $trackingNumber = $request->input('tracking_number');
        
        if (!$trackingNumber) {
            return response()->json(['error' => 'Tracking number is required'], 400);
        }

        // Find shipment by tracking number
        $shipment = \App\Models\Shipment::where('tracking_number', $trackingNumber)
            ->orWhere('courier_tracking_id', $trackingNumber)
            ->with(['courier', 'statusHistory' => function($q) {
                $q->orderBy('happened_at', 'desc')->limit(10);
            }])
            ->first();

        if (!$shipment) {
            return response()->json(['error' => 'Shipment not found'], 404);
        }

        if (!$shipment->courier->api_endpoint) {
            return response()->json(['error' => 'Courier does not support API integration'], 400);
        }

        try {
            // Test the API call
            $job = new \App\Jobs\FetchCourierStatuses();
            
            // Use reflection to call private method
            $reflection = new \ReflectionClass($job);
            $method = $reflection->getMethod('fetchShipmentStatus');
            $method->setAccessible(true);
            $method->invoke($job, $shipment->courier, $shipment);

            // Refresh shipment data
            $shipment->refresh();
            $shipment->load(['statusHistory' => function($q) {
                $q->orderBy('happened_at', 'desc')->limit(10);
            }]);

            return response()->json([
                'success' => true,
                'shipment' => $shipment,
                'message' => 'API test completed successfully!'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Courier API test error: " . $e->getMessage());
            return response()->json(['error' => 'API test failed: ' . $e->getMessage()], 500);
        }
        })->name('api.test.courier-api');

// ACS Credentials API (no CSRF protection for testing)
Route::middleware('auth')->group(function () {
    Route::post('/api/acs/update-credentials', [ACSTestController::class, 'updateCredentials'])->name('api.acs.update');
    Route::get('/api/acs/get-credentials', [ACSTestController::class, 'getCredentials'])->name('api.acs.get');
});

require __DIR__.'/auth.php';
