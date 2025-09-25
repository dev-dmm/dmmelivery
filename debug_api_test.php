<?php
/**
 * Debug script for API test issues
 * Run this on your server to test the API endpoint directly
 */

require_once 'vendor/autoload.php';

use App\Models\Courier;
use App\Services\ACSCourierService;
use Illuminate\Support\Facades\Log;

echo "🔍 Debugging API Test Issues\n";
echo "============================\n\n";

// Test 1: Check if we can get tenant
echo "1. Testing tenant access...\n";
try {
    $tenant = app('tenant');
    echo "✅ Tenant found: " . ($tenant->business_name ?? $tenant->name) . "\n";
    echo "   Tenant ID: " . $tenant->id . "\n";
} catch (Exception $e) {
    echo "❌ Tenant error: " . $e->getMessage() . "\n";
}

// Test 2: Check active couriers
echo "\n2. Testing active couriers...\n";
try {
    $activeCouriers = Courier::where('tenant_id', $tenant->id)
        ->where('is_active', true)
        ->whereNotNull('api_endpoint')
        ->get();
    
    echo "✅ Found " . $activeCouriers->count() . " active couriers:\n";
    foreach ($activeCouriers as $courier) {
        echo "   - {$courier->name} ({$courier->code}) - {$courier->api_endpoint}\n";
    }
} catch (Exception $e) {
    echo "❌ Couriers error: " . $e->getMessage() . "\n";
}

// Test 3: Check ACS credentials
echo "\n3. Testing ACS credentials...\n";
try {
    $hasCredentials = $tenant->hasACSCredentials();
    echo "✅ ACS credentials configured: " . ($hasCredentials ? 'Yes' : 'No') . "\n";
    
    if ($hasCredentials) {
        echo "   Company ID: " . $tenant->acs_company_id . "\n";
        echo "   User ID: " . $tenant->acs_user_id . "\n";
        echo "   API Key: " . (strlen($tenant->acs_api_key) > 0 ? 'Set (' . strlen($tenant->acs_api_key) . ' chars)' : 'Not set') . "\n";
    }
} catch (Exception $e) {
    echo "❌ Credentials error: " . $e->getMessage() . "\n";
}

// Test 4: Test ACS API call directly
echo "\n4. Testing ACS API call...\n";
try {
    $acsCourier = $activeCouriers->where('code', 'ACS')->first();
    if ($acsCourier) {
        echo "✅ Found ACS courier, testing API call...\n";
        
        $acsService = new ACSCourierService($acsCourier);
        $result = $acsService->getTrackingDetails('9703411222');
        
        echo "   API Call Result:\n";
        echo "   - Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        if (!$result['success']) {
            echo "   - Error: " . $result['error'] . "\n";
        } else {
            echo "   - Data received: " . (isset($result['data']) ? 'Yes' : 'No') . "\n";
            if (isset($result['data'])) {
                echo "   - Response structure: " . json_encode(array_keys($result['data'])) . "\n";
            }
        }
    } else {
        echo "❌ No ACS courier found\n";
    }
} catch (Exception $e) {
    echo "❌ ACS API error: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test 5: Check recent logs
echo "\n5. Checking recent logs...\n";
try {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $recentLogs = array_slice(explode("\n", $logs), -20);
        echo "✅ Recent log entries:\n";
        foreach ($recentLogs as $log) {
            if (strpos($log, 'ACS') !== false || strpos($log, 'courier') !== false) {
                echo "   " . $log . "\n";
            }
        }
    } else {
        echo "❌ Log file not found\n";
    }
} catch (Exception $e) {
    echo "❌ Log check error: " . $e->getMessage() . "\n";
}

echo "\n🏁 Debug complete!\n";
