<?php

return [
    'app_id' => env('PUSHER_APP_ID', 'your-pusher-app-id'),
    'key' => env('PUSHER_APP_KEY', 'your-pusher-app-key'),
    'secret' => env('PUSHER_APP_SECRET', 'your-pusher-app-secret'),
    'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
    'host' => env('PUSHER_HOST', 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusherapp.com'),
    'port' => env('PUSHER_PORT', 443),
    'scheme' => env('PUSHER_SCHEME', 'https'),
    'encrypted' => env('PUSHER_ENCRYPTED', true),
];
