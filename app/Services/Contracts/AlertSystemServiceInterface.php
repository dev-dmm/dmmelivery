<?php

namespace App\Services\Contracts;

use App\Models\Shipment;
use App\Models\Alert;

interface AlertSystemServiceInterface
{
    /**
     * Check all active shipments for alert conditions
     */
    public function checkAllShipments(): int;

    /**
     * Check alerts for a specific shipment
     */
    public function checkShipmentAlerts(Shipment $shipment): int;

    /**
     * Create default alert rules for a tenant
     */
    public function createDefaultAlertRules($tenant): void;
}

