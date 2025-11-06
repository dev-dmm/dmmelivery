<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AnalyticsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    private AnalyticsServiceInterface $analyticsService;

    public function __construct(AnalyticsServiceInterface $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive analytics dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period', 'courier_id', 'status']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics,
            'filters' => $filters,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get performance metrics
     */
    public function performance(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics['performance'],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get trend analysis
     */
    public function trends(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics['trends'],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get predictive analytics
     */
    public function predictions(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics['predictions'],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get alert analytics
     */
    public function alerts(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics['alerts'],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get geographic analytics
     */
    public function geographic(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics['geographic'],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get customer analytics
     */
    public function customers(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics['customer'],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get courier analytics
     */
    public function couriers(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        return response()->json([
            'success' => true,
            'data' => $analytics['courier'],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Export analytics data
     */
    public function export(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period', 'format']);
        $format = $filters['format'] ?? 'json';
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        if ($format === 'csv') {
            return $this->exportToCsv($analytics);
        }
        
        return response()->json([
            'success' => true,
            'data' => $analytics,
            'export_format' => $format,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get analytics summary for dashboard widgets
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'period']);
        
        $analytics = $this->analyticsService->getTenantAnalytics($tenantId, $filters);
        
        $summary = [
            'overview' => $analytics['overview'],
            'performance_score' => $analytics['performance']['performance_score'],
            'trend_direction' => $analytics['trends']['trend_direction'],
            'alert_count' => $analytics['alerts']['total_alerts'],
            'top_courier' => $analytics['courier']['courier_performance'][0] ?? null,
            'top_customer' => $analytics['customer']['top_customers'][0] ?? null,
        ];
        
        return response()->json([
            'success' => true,
            'data' => $summary,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Export analytics to CSV
     */
    private function exportToCsv(array $analytics): JsonResponse
    {
        $csvData = [];
        
        // Flatten analytics data for CSV export
        foreach ($analytics as $category => $data) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $subKey => $subValue) {
                            $csvData[] = [
                                'category' => $category,
                                'metric' => $key . '_' . $subKey,
                                'value' => is_array($subValue) ? json_encode($subValue) : $subValue,
                            ];
                        }
                    } else {
                        $csvData[] = [
                            'category' => $category,
                            'metric' => $key,
                            'value' => $value,
                        ];
                    }
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $csvData,
            'export_format' => 'csv',
            'generated_at' => now()->toISOString(),
        ]);
    }
}
