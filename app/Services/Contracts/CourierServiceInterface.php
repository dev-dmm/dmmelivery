<?php

namespace App\Services\Contracts;

interface CourierServiceInterface
{
    /**
     * Get tracking details for a shipment
     */
    public function getTrackingDetails(string $voucherNumber, array $credentials = []): array;

    /**
     * Test courier API connection
     */
    public function testConnection(array $credentials): bool;

    /**
     * Create shipment via courier API
     */
    public function createShipment(array $shipmentData, array $credentials = []): array;
}

