<?php

return [
    'only' => [
        // Public routes
        'login',
        'register',
        'password.*',
        
        // Authenticated routes
        'dashboard',
        'logout',
        'profile.*',
        
        // Main app routes
        'shipments.*',
        'customers.*',
        'couriers.*',
        'settings.*',
        'onboarding.*',
        'courier-performance',
        
        // API routes (only what frontend needs)
        'api.shipments.*',
        'api.settings.*',
        
        // Super admin routes (filtered by middleware anyway)
        'super-admin.*',
    ],
    
    'except' => [
        // Internal API routes
        'api.test.*',
        'api.webhooks.*',
        'api.internal.*',
        
        // Development routes
        'debugbar.*',
        'horizon.*',
        'telescope.*',
    ],
];
