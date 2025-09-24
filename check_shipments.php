<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

echo "Checking shipments in database..." . PHP_EOL;

// Check all shipments
$shipments = App\Models\Shipment::with('courier')->get();
echo "Total shipments: " . $shipments->count() . PHP_EOL;

if($shipments->count() > 0) {
    echo "\nFirst 5 shipments:" . PHP_EOL;
    foreach($shipments->take(5) as $shipment) {
        echo "- ID: " . $shipment->id . ", Tracking: " . $shipment->tracking_number . ", Courier: " . ($shipment->courier ? $shipment->courier->name : 'None') . PHP_EOL;
    }
}

// Check if tracking number 9703411222 exists
$specificShipment = App\Models\Shipment::where('tracking_number', '9703411222')->first();
if($specificShipment) {
    echo "\nFound shipment with tracking number 9703411222:" . PHP_EOL;
    echo "- ID: " . $specificShipment->id . PHP_EOL;
    echo "- Status: " . $specificShipment->status . PHP_EOL;
    echo "- Courier: " . ($specificShipment->courier ? $specificShipment->courier->name : 'None') . PHP_EOL;
} else {
    echo "\nNo shipment found with tracking number 9703411222" . PHP_EOL;
}


