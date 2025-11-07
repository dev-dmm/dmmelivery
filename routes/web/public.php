<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| Public landing page and unauthenticated routes
|
*/

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user && $user->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }
        return redirect()->route('dashboard');
    }

    return Inertia::render('Home', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
        // Don't leak versions in production
        'meta'        => app()->isLocal()
            ? ['laravel' => Application::VERSION, 'php' => PHP_VERSION]
            : null,
    ]);
});

