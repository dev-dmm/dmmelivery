<?php
/**
 * Test the API endpoint directly
 * This simulates the API call that the frontend makes
 */

// Test data
$trackingNumber = '9703411222';
$apiUrl = 'http://localhost/api/test/courier-api'; // Adjust URL as needed

echo "🧪 Testing API Endpoint Directly\n";
echo "================================\n\n";

// Prepare the request
$data = [
    'tracking_number' => $trackingNumber
];

$options = [
    'http' => [
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        'method' => 'POST',
        'content' => json_encode($data)
    ]
];

echo "📤 Sending request to: $apiUrl\n";
echo "📦 Tracking number: $trackingNumber\n\n";

try {
    $context = stream_context_create($options);
    $result = file_get_contents($apiUrl, false, $context);
    
    if ($result === false) {
        echo "❌ Failed to make request\n";
        echo "   Error: " . error_get_last()['message'] . "\n";
    } else {
        echo "✅ Response received:\n";
        $response = json_decode($result, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "📋 Response data:\n";
            echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "📋 Raw response:\n";
            echo $result . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n🏁 Test complete!\n";
