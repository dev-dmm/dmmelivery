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
            'tenant.scope'    => \App\Http\Middleware\TenantScope::class,
            'identify.tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'super.admin'     => \App\Http\Middleware\SuperAdminMiddleware::class,
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

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new \App\Jobs\FetchCourierStatuses)->everyTenMinutes();
        $schedule->job(new \App\Jobs\UpdatePredictiveEtas)->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
