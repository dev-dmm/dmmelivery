<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    private AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Display basic analytics dashboard
     */
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        // Get basic analytics data
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return Inertia::render('Analytics/Index', [
            'analytics' => $analytics,
            'filters' => $filters,
        ]);
    }

    /**
     * Display advanced analytics dashboard
     */
    public function advanced(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        // Get comprehensive analytics data
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return Inertia::render('Analytics/AdvancedDashboard', [
            'analytics' => $analytics,
            'filters' => $filters,
        ]);
    }

    /**
     * Export analytics data
     */
    public function export(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period', 'format']);
        
        // Get analytics data for export
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return Inertia::render('Analytics/Export', [
            'analytics' => $analytics,
            'filters' => $filters,
        ]);
    }
}