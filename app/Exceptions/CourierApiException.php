<?php

namespace App\Exceptions;

class CourierApiException extends BusinessException
{
    public function __construct(
        string $message = 'Courier API error',
        string $courierName = null,
        array $context = [],
        int $code = 502
    ) {
        $userMessage = $courierName 
            ? "Failed to communicate with {$courierName} courier service"
            : 'Courier service temporarily unavailable';
            
        parent::__construct(
            $message,
            'COURIER_API_ERROR',
            $userMessage,
            array_merge($context, ['courier' => $courierName]),
            $code
        );
    }
}
