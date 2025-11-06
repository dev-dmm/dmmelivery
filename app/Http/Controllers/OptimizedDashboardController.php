<?php

namespace App\Http\Controllers;

use App\Services\Contracts\CacheServiceInterface;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Courier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptimizedDashboardController extends Controller
{
    private CacheServiceInterface $cacheService;

    public function __construct(CacheServiceInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Get optimized dashboard statistics with caching
     */
    public function getStats(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $period = $request->get('period', 'week');
        
        // Try to get cached stats first
        $cachedStats = $this->cacheService->getCachedDashboardStats($tenantId, $period);
        if ($cachedStats) {
            return response()->json($cachedStats);
        }

        // Calculate date range
        $dateRange = $this->getDateRange($period);
        
        // Get statistics using optimized queries
        $stats = $this->calculateOptimizedStats($tenantId, $dateRange);
        
        // Cache the results
        $this->cacheService->cacheDashboardStats($tenantId, $period, $stats);
        
        return response()->json($stats);
    }

    /**
     * Get recent shipments with caching
     */
    public function getRecentShipments(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Try cache first
        $cachedShipments = $this->cacheService->getCachedRecentShipments($tenantId);
        if ($cachedShipments) {
            return response()->json($cachedShipments);
        }

        // Get recent shipments with optimized query
        $shipments = Shipment::forTenant($tenantId)
            ->withRelations()
            ->recent(7) // Last 7 days
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'status' => $shipment->status,
                    'customer' => $shipment->customer?->name,
                    'courier' => $shipment->courier?->name,
                    'created_at' => $shipment->created_at,
                ];
            });

        $this->cacheService->cacheRecentShipments($tenantId, $shipments->toArray());
        
        return response()->json($shipments);
    }

    /**
     * Get courier performance statistics
     */
    public function getCourierStats(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Try cache first
        $cachedStats = $this->cacheService->getCachedCourierStats($tenantId);
        if ($cachedStats) {
            return response()->json($cachedStats);
        }

        // Calculate courier stats using optimized query
        $courierStats = $this->calculateCourierStats($tenantId);
        
        // Cache for 1 hour
        $this->cacheService->cacheCourierStats($tenantId, $courierStats);
        
        return response()->json($courierStats);
    }

    /**
     * Calculate optimized statistics
     */
    private function calculateOptimizedStats(string $tenantId, array $dateRange): array
    {
        // Use single query with aggregations instead of multiple queries
        $stats = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                COUNT(*) as total_shipments,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_shipments,
                SUM(CASE WHEN status IN ("in_transit", "out_for_delivery") THEN 1 ELSE 0 END) as in_transit_shipments,
                SUM(CASE WHEN status = "out_for_delivery" THEN 1 ELSE 0 END) as out_for_delivery_shipments,
                AVG(CASE WHEN status = "delivered" AND actual_delivery IS NOT NULL AND estimated_delivery IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, estimated_delivery, actual_delivery) ELSE NULL END) as avg_delay_hours
            ')
            ->first();

        // Get courier and customer counts
        $additionalStats = DB::table('couriers')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $customerCount = DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->count();

        return [
            'total_shipments' => $stats->total_shipments ?? 0,
            'delivered_shipments' => $stats->delivered_shipments ?? 0,
            'in_transit_shipments' => $stats->in_transit_shipments ?? 0,
            'out_for_delivery_shipments' => $stats->out_for_delivery_shipments ?? 0,
            'total_couriers' => $additionalStats,
            'total_customers' => $customerCount,
            'delivery_success_rate' => $stats->total_shipments > 0 
                ? round(($stats->delivered_shipments / $stats->total_shipments) * 100, 1) 
                : 0,
            'avg_delay_hours' => round($stats->avg_delay_hours ?? 0, 1),
        ];
    }

    /**
     * Calculate courier performance statistics
     */
    private function calculateCourierStats(string $tenantId): array
    {
        return DB::table('shipments')
            ->join('couriers', 'shipments.courier_id', '=', 'couriers.id')
            ->where('shipments.tenant_id', $tenantId)
            ->where('shipments.created_at', '>=', now()->subDays(30))
            ->selectRaw('
                couriers.id,
                couriers.name,
                couriers.code,
                COUNT(shipments.id) as total_shipments,
                SUM(CASE WHEN shipments.status = "delivered" THEN 1 ELSE 0 END) as delivered_shipments,
                SUM(CASE WHEN shipments.status IN ("pending", "picked_up", "in_transit") THEN 1 ELSE 0 END) as pending_shipments,
                SUM(CASE WHEN shipments.status = "failed" THEN 1 ELSE 0 END) as failed_shipments
            ')
            ->groupBy('couriers.id', 'couriers.name', 'couriers.code')
            ->orderBy('total_shipments', 'desc')
            ->get()
            ->map(function ($courier) {
                return [
                    'id' => $courier->id,
                    'name' => $courier->name,
                    'code' => $courier->code,
                    'total_shipments' => $courier->total_shipments,
                    'delivered_shipments' => $courier->delivered_shipments,
                    'pending_shipments' => $courier->pending_shipments,
                    'failed_shipments' => $courier->failed_shipments,
                    'success_rate' => $courier->total_shipments > 0 
                        ? round(($courier->delivered_shipments / $courier->total_shipments) * 100, 1) 
                        : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get date range based on period
     */
    private function getDateRange(string $period): array
    {
        $now = Carbon::now();
        
        return match ($period) {
            'today' => [
                'start' => $now->startOfDay(),
                'end' => $now->endOfDay(),
            ],
            'week' => [
                'start' => $now->subWeek()->startOfDay(),
                'end' => $now->endOfDay(),
            ],
            'month' => [
                'start' => $now->subMonth()->startOfDay(),
                'end' => $now->endOfDay(),
            ],
            'quarter' => [
                'start' => $now->subQuarter()->startOfDay(),
                'end' => $now->endOfDay(),
            ],
            'year' => [
                'start' => $now->subYear()->startOfDay(),
                'end' => $now->endOfDay(),
            ],
            default => [
                'start' => $now->subWeek()->startOfDay(),
                'end' => $now->endOfDay(),
            ],
        };
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->cacheService->invalidateTenantCaches($tenantId);
        
        return response()->json(['message' => 'Cache cleared successfully']);
    }
}
