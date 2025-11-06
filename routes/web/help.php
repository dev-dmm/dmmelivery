<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Help & Support Routes
|--------------------------------------------------------------------------
|
| Help documentation and support pages
|
*/

Route::middleware(['auth', 'verified', 'identify.tenant'])
    ->prefix('help')
    ->name('help.')
    ->group(function () {
        Route::get('/', function () {
            return Inertia::render('Help/Index');
        })->name('index');
        
        Route::get('/getting-started', function () {
            return Inertia::render('Help/GettingStarted');
        })->name('getting-started');
        
        Route::get('/shipments', function () {
            return Inertia::render('Help/Shipments');
        })->name('shipments');
        
        Route::get('/analytics', function () {
            return Inertia::render('Help/Analytics');
        })->name('analytics');
        
        Route::get('/notifications', function () {
            return Inertia::render('Help/Notifications');
        })->name('notifications');
        
        Route::get('/shipments/create-first', function () {
            return Inertia::render('Help/ShipmentsCreateFirst');
        })->name('shipments.create-first');
        
        Route::get('/notifications/setup', function () {
            return Inertia::render('Help/NotificationsSetup');
        })->name('notifications.setup');
        
        Route::get('/dashboard/overview', function () {
            return Inertia::render('Help/DashboardOverview');
        })->name('dashboard.overview');
    });

