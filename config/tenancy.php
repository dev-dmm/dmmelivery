<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reserved Subdomains
    |--------------------------------------------------------------------------
    |
    | These subdomains are reserved and will not be used for tenant resolution.
    | Requests to these subdomains will fall back to the authenticated user's
    | tenant or other resolution methods.
    |
    */
    'reserved_subdomains' => [
        'www',
        'app',
        'api',
        'static',
        'assets',
        'cdn',
        'admin',
        'mail',
        'ftp',
        'localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | The base domain for subdomain-based tenant resolution.
    | Example: 'yourapp.com' for 'acme.yourapp.com'
    |
    */
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'oreksi.gr'),

    /*
    |--------------------------------------------------------------------------
    | Enable Override in Production
    |--------------------------------------------------------------------------
    |
    | Whether to allow super-admin tenant overrides in production.
    | Overrides always require HTTPS in production regardless of this setting.
    |
    */
    'enable_override_in_production' => env('TENANCY_ENABLE_OVERRIDE_IN_PROD', false),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Cache TTL in seconds for tenant lookups.
    | Set to null to disable caching.
    |
    */
    'cache_ttl' => env('TENANCY_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Cache Tags
    |--------------------------------------------------------------------------
    |
    | Cache tags for tenant lookups. Use Cache::tags('tenants')->flush()
    | to invalidate all tenant caches when a tenant is updated.
    | Note: Only Redis and Memcached support tags. Other drivers will
    | fall back to regular caching without tags.
    |
    */
    'cache_tags' => ['tenants'],

    /*
    |--------------------------------------------------------------------------
    | Allow Cache Flush for Non-Tagged Stores
    |--------------------------------------------------------------------------
    |
    | When using cache drivers that don't support tags (file, database, array),
    | tenant updates cannot selectively clear tenant cache. This option allows
    | flushing the entire cache when a tenant is updated.
    |
    | WARNING: Setting this to true will flush ALL cache when a tenant is updated.
    | Only enable this in development or if you're okay with full cache flushes.
    | For production, use Redis or Memcached which support tags.
    |
    */
    'allow_cache_flush' => env('TENANCY_ALLOW_CACHE_FLUSH', false),
];

