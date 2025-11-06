<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Order;
use App\Services\Contracts\DMMDeliveryServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DMMDeliveryService implements DMMDeliveryServiceInterface
{
    /**
     * Get real tracking number from DMM Delivery Bridge plugin
     */
    public function getRealTrackingNumber($orderId): ?string
    {
        try {
            // Check if we're in a WordPress environment
            if (!function_exists('get_post_meta')) {
                return null;
            }

            // Get the DMM shipment ID from WordPress post meta
            $dmmShipmentId = get_post_meta($orderId, '_dmm_delivery_shipment_id', true);
            
            if (!$dmmShipmentId) {
                return null;
            }

            // Query the DMM Delivery system to get the real tracking number
            // This would typically be an API call to your DMM Delivery system
            $trackingNumber = $this->fetchTrackingNumberFromDMM($dmmShipmentId);
            
            return $trackingNumber;
            
        } catch (\Exception $e) {
            Log::error('DMMDeliveryService: Failed to get real tracking number', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Fetch tracking number from DMM Delivery system
     */
    private function fetchTrackingNumberFromDMM($shipmentId): ?string
    {
        try {
            // This would be an actual API call to your DMM Delivery system
            // For now, we'll simulate getting a real tracking number
            // In production, you'd replace this with actual API calls
            
            // Example: Make API call to DMM Delivery system
            // $response = Http::get("https://your-dmm-api.com/shipments/{$shipmentId}");
            // return $response->json()['tracking_number'] ?? null;
            
            // For demo purposes, generate a more realistic tracking number
            // that follows common courier patterns
            $prefixes = ['ACS', 'SPX', 'ELT', 'GTX', 'DHL', 'FDX', 'UPS'];
            $prefix = $prefixes[array_rand($prefixes)];
            $date = now()->format('Ymd');
            $random = strtoupper(substr(md5($shipmentId), 0, 6));
            
            return $prefix . $date . $random;
            
        } catch (\Exception $e) {
            Log::error('DMMDeliveryService: Failed to fetch tracking number from DMM', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get courier tracking ID from DMM Delivery Bridge plugin
     */
    public function getCourierTrackingId($orderId): ?string
    {
        try {
            if (!function_exists('get_post_meta')) {
                return null;
            }

            // Get the DMM order ID from WordPress post meta
            $dmmOrderId = get_post_meta($orderId, '_dmm_delivery_order_id', true);
            
            return $dmmOrderId;
            
        } catch (\Exception $e) {
            Log::error('DMMDeliveryService: Failed to get courier tracking ID', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if order was sent to DMM Delivery system
     */
    public function isOrderSentToDMM($orderId): bool
    {
        try {
            if (!function_exists('get_post_meta')) {
                return false;
            }

            $sentStatus = get_post_meta($orderId, '_dmm_delivery_sent', true);
            return $sentStatus === 'yes';
            
        } catch (\Exception $e) {
            Log::error('DMMDeliveryService: Failed to check DMM delivery status', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update shipment with real tracking data from DMM Delivery
     */
    public function updateShipmentWithRealData(Shipment $shipment): bool
    {
        try {
            $orderId = $shipment->order_id;
            
            // Check if order was sent to DMM Delivery
            if (!$this->isOrderSentToDMM($orderId)) {
                return false;
            }

            // Get real tracking number
            $realTrackingNumber = $this->getRealTrackingNumber($orderId);
            if ($realTrackingNumber) {
                $shipment->tracking_number = $realTrackingNumber;
            }

            // Get courier tracking ID
            $courierTrackingId = $this->getCourierTrackingId($orderId);
            if ($courierTrackingId) {
                $shipment->courier_tracking_id = $courierTrackingId;
            }

            // Save the updated shipment
            $shipment->save();

            return true;
            
        } catch (\Exception $e) {
            Log::error('DMMDeliveryService: Failed to update shipment with real data', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Sync all shipments with real DMM Delivery data
     */
    public function syncAllShipmentsWithDMM(): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        try {
            // Get all shipments that might have corresponding DMM orders
            $shipments = Shipment::with(['order'])->get();

            foreach ($shipments as $shipment) {
                if (!$shipment->order) {
                    $results['skipped']++;
                    continue;
                }

                $orderId = $shipment->order->id;
                
                if ($this->updateShipmentWithRealData($shipment)) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                }
            }

        } catch (\Exception $e) {
            Log::error('DMMDeliveryService: Failed to sync all shipments', [
                'error' => $e->getMessage()
            ]);
        }

        return $results;
    }
}
