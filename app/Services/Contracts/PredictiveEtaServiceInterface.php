<?php

namespace App\Services\Contracts;

use App\Models\Shipment;
use App\Models\PredictiveEta;

interface PredictiveEtaServiceInterface
{
    /**
     * Generate predictive ETA for a shipment
     */
    public function generatePredictiveEta(Shipment $shipment): PredictiveEta;

    /**
     * Update all predictive ETAs
     */
    public function updateAllPredictiveEtas(): int;
}

