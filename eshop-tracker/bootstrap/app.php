<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\IdentifyTenant;
use App\Jobs\FetchCourierStatuses;
use App\Http\Middleware\TenantScope; 

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ✅ Register the alias 'tenant'
        $middleware->alias([
            'tenant.scope' => \App\Http\Middleware\TenantScope::class,
            'identify.tenant' => \App\Http\Middleware\IdentifyTenant::class,
        ]);

        // 🔓 CSRF exceptions for API testing  
        $middleware->validateCsrfTokens(except: [
            '/api/test/courier-api',
            'api/acs/update-credentials', // ACS test endpoint
            'api/acs/get-credentials', // ACS get endpoint
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // ⏰ Schedule background job to run every 10 minutes
        $schedule->job(new FetchCourierStatuses)->everyTenMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
