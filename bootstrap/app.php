<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aliases (optional if you use them in routes)
        $middleware->alias([
            'enforce.tenant'  => \App\Http\Middleware\EnforceTenant::class,
            'tenant.scope'    => \App\Http\Middleware\EnforceTenant::class, // Legacy alias for backward compatibility
            'identify.tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'super.admin'     => \App\Http\Middleware\SuperAdminMiddleware::class,
            'woo.hmac'        => \App\Http\Middleware\VerifyWooHmac::class,
            'woo.ratelimit.headers' => \App\Http\Middleware\AddWooRateLimitHeaders::class,
        ]);

        // CSRF exceptions (keep your own)
        $middleware->validateCsrfTokens(except: [
            '/api/test/courier-api',
            'api/acs/update-credentials',
            'api/acs/get-credentials',
            'api/woocommerce/order',
            'settings/api/generate',
            'chatbot/sessions',
            'chatbot/sessions/*',
        ]);

        // Tenant middleware is applied per-route, not globally

        // Trust proxies for accurate client IP (required for rate limiting behind Cloudflare/CDN)
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new \App\Jobs\FetchCourierStatuses)->everyTenMinutes();
        $schedule->job(new \App\Jobs\UpdatePredictiveEtas)->hourly();
        
        // Reset monthly shipment counters (runs daily, command handles the logic)
        $schedule->command('tenants:reset-monthly-shipments')
            ->dailyAt('03:00')
            ->timezone('Europe/Athens'); // Adjust to your timezone
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
