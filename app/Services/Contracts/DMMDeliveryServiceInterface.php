<?php

namespace App\Services\Contracts;

interface DMMDeliveryServiceInterface
{
    /**
     * Get real tracking number from DMM Delivery Bridge plugin
     */
    public function getRealTrackingNumber($orderId): ?string;

    /**
     * Get courier tracking ID from DMM Delivery Bridge plugin
     */
    public function getCourierTrackingId($orderId): ?string;

    /**
     * Update shipment with real data from DMM Delivery
     */
    public function updateShipmentWithRealData($shipment): bool;
}

