<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Courier;
use App\Services\ACSCourierService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchCourierStatuses implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🚚 Starting courier status fetch job');

        // Get all active couriers that support API integration
        $couriers = Courier::where('is_active', true)
            ->whereNotNull('api_endpoint')
            ->whereNotNull('api_key')
            ->get();

        foreach ($couriers as $courier) {
            $this->fetchStatusesForCourier($courier);
        }

        Log::info('✅ Courier status fetch job completed');
    }

    /**
     * Fetch status updates for a specific courier
     */
    private function fetchStatusesForCourier(Courier $courier): void
    {
        Log::info("📡 Fetching statuses for courier: {$courier->name}");

        // Get active shipments for this courier (not delivered/cancelled)
        $activeShipments = $courier->shipments()
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->where('updated_at', '>=', now()->subDays(14)) // Only check recent shipments
            ->get();

        foreach ($activeShipments as $shipment) {
            try {
                $this->fetchShipmentStatus($courier, $shipment);
                
                // Small delay to avoid overwhelming the API
                sleep(1);
            } catch (\Exception $e) {
                Log::error("❌ Error fetching status for shipment {$shipment->tracking_number}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Fetch status for a specific shipment
     */
    private function fetchShipmentStatus(Courier $courier, Shipment $shipment): void
    {
        switch (strtolower($courier->code)) {
            case 'acs':
                $this->fetchACSStatus($courier, $shipment);
                break;
            case 'elt':
            case 'elta':
                $this->fetchELTAStatus($courier, $shipment);
                break;
            case 'spx':
            case 'speedex':
                $this->fetchSpeedexStatus($courier, $shipment);
                break;
            default:
                Log::warning("⚠️ No API implementation for courier: {$courier->code}");
        }
    }

    /**
     * Fetch status from ACS API
     */
    private function fetchACSStatus(Courier $courier, Shipment $shipment): void
    {
        $acsService = new ACSCourierService($courier);
        $voucherNumber = $shipment->courier_tracking_id ?: $shipment->tracking_number;

        Log::info("📡 Fetching ACS tracking details for: {$voucherNumber}");

        // Get tracking details from ACS
        $result = $acsService->getTrackingDetails($voucherNumber);

        if ($result['success']) {
            $this->processACSResponse($shipment, $result['data']);
        } else {
            Log::error("❌ ACS API error for {$shipment->tracking_number}: " . $result['error']);
        }
    }

    /**
     * Process ACS API response and update shipment status
     */
    private function processACSResponse(Shipment $shipment, array $data): void
    {
        // Parse tracking events using the ACS service
        $events = ACSCourierService::parseTrackingEvents($data);

        if (empty($events)) {
            Log::warning("⚠️ No tracking events found for shipment: {$shipment->tracking_number}");
            return;
        }

        Log::info("📋 Found " . count($events) . " tracking events for: {$shipment->tracking_number}");

        // Process each tracking event
        foreach ($events as $event) {
            $this->processTrackingEvent($shipment, $event);
        }
    }

    /**
     * Process individual tracking event
     */
    private function processTrackingEvent(Shipment $shipment, array $event): void
    {
        $eventDateTime = $event['datetime'] ?? null;
        $eventAction = $event['action'] ?? '';
        $eventLocation = $event['location'] ?? '';
        $eventNotes = $event['notes'] ?? '';
        $internalStatus = $event['status'] ?? 'in_transit';

        if (!$eventDateTime) {
            return;
        }

        // Parse datetime
        try {
            $happenedAt = new \DateTime($eventDateTime);
        } catch (\Exception $e) {
            Log::warning("⚠️ Invalid datetime format: {$eventDateTime}");
            return;
        }

        // Check if we already have this status update
        $existingHistory = ShipmentStatusHistory::where('shipment_id', $shipment->id)
            ->where('happened_at', $happenedAt)
            ->where('status', $internalStatus)
            ->first();

        if ($existingHistory) {
            return; // Already processed this event
        }

        // Create new status history entry
        ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'status' => $internalStatus,
            'description' => $eventAction . ($eventNotes ? " - {$eventNotes}" : ''),
            'location' => $eventLocation,
            'happened_at' => $happenedAt,
            'courier_response' => $event['raw_data'] ?? $event,
        ]);

        Log::info("📝 Created status history: {$shipment->tracking_number} - {$internalStatus} at {$eventDateTime}");

        // Update shipment status if this is the latest event
        $latestEvent = ShipmentStatusHistory::where('shipment_id', $shipment->id)
            ->orderByDesc('happened_at')
            ->first();

        if ($latestEvent && $latestEvent->status !== $shipment->status) {
            $shipment->update([
                'status' => $latestEvent->status,
                'courier_response' => $event['raw_data'] ?? $event,
            ]);

            // Set actual delivery date if delivered
            if ($latestEvent->status === 'delivered') {
                $shipment->update(['actual_delivery' => $latestEvent->happened_at]);
            }

            Log::info("📋 Updated shipment {$shipment->tracking_number} status to: {$latestEvent->status}");
        }
    }



    /**
     * Placeholder for ELTA status fetching
     */
    private function fetchELTAStatus(Courier $courier, Shipment $shipment): void
    {
        Log::info("📮 ELTA API integration coming soon for: {$shipment->tracking_number}");
        // TODO: Implement ELTA API integration
    }

    /**
     * Placeholder for Speedex status fetching  
     */
    private function fetchSpeedexStatus(Courier $courier, Shipment $shipment): void
    {
        Log::info("🏃 Speedex API integration coming soon for: {$shipment->tracking_number}");
        // TODO: Implement Speedex API integration
    }
}
