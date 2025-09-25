<?php

namespace App\Exceptions;

class ShipmentNotFoundException extends BusinessException
{
    public function __construct(string $trackingNumber = null, array $context = [])
    {
        $message = $trackingNumber 
            ? "Shipment with tracking number '{$trackingNumber}' not found"
            : 'Shipment not found';
            
        $userMessage = $trackingNumber
            ? "No shipment found with tracking number: {$trackingNumber}"
            : 'Shipment not found';
            
        parent::__construct(
            $message,
            'SHIPMENT_NOT_FOUND',
            $userMessage,
            array_merge($context, ['tracking_number' => $trackingNumber]),
            404
        );
    }
}
