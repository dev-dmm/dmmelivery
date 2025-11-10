<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for various API endpoints and features.
    | These values are used by rate limiters and can be referenced
    | throughout the application to ensure consistency.
    |
    */

    'woocommerce_per_minute' => env('WOOCOMMERCE_RATE_LIMIT', 60),
];

