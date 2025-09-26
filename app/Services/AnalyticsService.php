<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Order;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\PredictiveEta;
use App\Models\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Get comprehensive analytics for a tenant
     */
    public function getTenantAnalytics(string $tenantId, array $filters = []): array
    {
        $cacheKey = "analytics:tenant:{$tenantId}:" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 3600, function () use ($tenantId, $filters) {
            return [
                'overview' => $this->getOverviewMetrics($tenantId, $filters),
                'performance' => $this->getPerformanceMetrics($tenantId, $filters),
                'trends' => $this->getTrendAnalysis($tenantId, $filters),
                'predictions' => $this->getPredictiveAnalytics($tenantId, $filters),
                'alerts' => $this->getAlertAnalytics($tenantId, $filters),
                'geographic' => $this->getGeographicAnalytics($tenantId, $filters),
                'customer' => $this->getCustomerAnalytics($tenantId, $filters),
                'courier' => $this->getCourierAnalytics($tenantId, $filters),
            ];
        });
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(string $tenantId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $metrics = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('
                COUNT(*) as total_shipments,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN status IN ("in_transit", "out_for_delivery") THEN 1 ELSE 0 END) as in_transit_count,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count,
                AVG(CASE WHEN status = "delivered" AND actual_delivery IS NOT NULL AND estimated_delivery IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, estimated_delivery, actual_delivery) ELSE NULL END) as avg_delay_hours,
                SUM(shipping_cost) as total_revenue
            ')
            ->first();

        $previousPeriod = $this->getPreviousPeriod($dateRange);
        $previousMetrics = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $previousPeriod)
            ->selectRaw('
                COUNT(*) as total_shipments,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_count
            ')
            ->first();

        return [
            'total_shipments' => $metrics->total_shipments ?? 0,
            'delivered_shipments' => $metrics->delivered_count ?? 0,
            'in_transit_shipments' => $metrics->in_transit_count ?? 0,
            'failed_shipments' => $metrics->failed_count ?? 0,
            'success_rate' => $metrics->total_shipments > 0 
                ? round(($metrics->delivered_count / $metrics->total_shipments) * 100, 2) 
                : 0,
            'avg_delay_hours' => round($metrics->avg_delay_hours ?? 0, 2),
            'total_revenue' => $metrics->total_revenue ?? 0,
            'growth_rate' => $this->calculateGrowthRate(
                $metrics->total_shipments ?? 0,
                $previousMetrics->total_shipments ?? 0
            ),
            'delivery_growth_rate' => $this->calculateGrowthRate(
                $metrics->delivered_count ?? 0,
                $previousMetrics->delivered_count ?? 0
            ),
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(string $tenantId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        
        // Delivery time analysis
        $deliveryTimes = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $dateRange)
            ->where('status', 'delivered')
            ->whereNotNull('actual_delivery')
            ->whereNotNull('estimated_delivery')
            ->selectRaw('
                AVG(TIMESTAMPDIFF(HOUR, created_at, actual_delivery)) as avg_delivery_time_hours,
                MIN(TIMESTAMPDIFF(HOUR, created_at, actual_delivery)) as min_delivery_time_hours,
                MAX(TIMESTAMPDIFF(HOUR, created_at, actual_delivery)) as max_delivery_time_hours
            ')
            ->first();

        // Calculate median separately using MariaDB-compatible approach
        $medianQuery = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $dateRange)
            ->where('status', 'delivered')
            ->whereNotNull('actual_delivery')
            ->whereNotNull('estimated_delivery')
            ->selectRaw('TIMESTAMPDIFF(HOUR, created_at, actual_delivery) as delivery_time')
            ->orderBy('delivery_time')
            ->get();

        $median = $this->calculateMedian($medianQuery->pluck('delivery_time')->toArray());

        // On-time delivery rate
        $onTimeRate = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $dateRange)
            ->where('status', 'delivered')
            ->whereNotNull('actual_delivery')
            ->whereNotNull('estimated_delivery')
            ->selectRaw('
                COUNT(*) as total_delivered,
                SUM(CASE WHEN actual_delivery <= estimated_delivery THEN 1 ELSE 0 END) as on_time_deliveries
            ')
            ->first();

        return [
            'delivery_times' => [
                'average' => round($deliveryTimes->avg_delivery_time_hours ?? 0, 2),
                'minimum' => round($deliveryTimes->min_delivery_time_hours ?? 0, 2),
                'maximum' => round($deliveryTimes->max_delivery_time_hours ?? 0, 2),
                'median' => round($median, 2),
            ],
            'on_time_rate' => $onTimeRate->total_delivered > 0 
                ? round(($onTimeRate->on_time_deliveries / $onTimeRate->total_delivered) * 100, 2)
                : 0,
            'performance_score' => $this->calculatePerformanceScore($tenantId, $dateRange),
        ];
    }

    /**
     * Get trend analysis
     */
    private function getTrendAnalysis(string $tenantId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        $groupBy = $this->getGroupByPeriod($filters);
        
        $trends = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw("
                DATE_FORMAT(created_at, '{$groupBy}') as period,
                COUNT(*) as shipments,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(CASE WHEN status = 'delivered' AND actual_delivery IS NOT NULL AND estimated_delivery IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, estimated_delivery, actual_delivery) ELSE NULL END) as avg_delay
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'periods' => $trends->pluck('period')->toArray(),
            'shipments' => $trends->pluck('shipments')->toArray(),
            'delivered' => $trends->pluck('delivered')->toArray(),
            'failed' => $trends->pluck('failed')->toArray(),
            'avg_delays' => $trends->pluck('avg_delay')->toArray(),
            'trend_direction' => $this->calculateTrendDirection($trends->pluck('shipments')->toArray()),
        ];
    }

    /**
     * Get predictive analytics
     */
    private function getPredictiveAnalytics(string $tenantId, array $filters): array
    {
        $predictiveEtas = PredictiveEta::whereHas('shipment', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })
        ->where('created_at', '>=', now()->subDays(30))
        ->get();

        if ($predictiveEtas->isEmpty()) {
            return [
                'accuracy_score' => 0,
                'confidence_trend' => [],
                'delay_predictions' => [],
            ];
        }

        $accuracyScores = $predictiveEtas->pluck('confidence_score')->toArray();
        
        return [
            'accuracy_score' => round(array_sum($accuracyScores) / count($accuracyScores), 2),
            'confidence_trend' => $this->getConfidenceTrend($predictiveEtas),
            'delay_predictions' => $this->getDelayPredictions($predictiveEtas),
            'model_performance' => $this->getModelPerformance($predictiveEtas),
        ];
    }

    /**
     * Get alert analytics
     */
    private function getAlertAnalytics(string $tenantId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $alerts = Alert::where('tenant_id', $tenantId)
            ->whereBetween('triggered_at', $dateRange)
            ->get();

        $alertTypes = $alerts->groupBy('alert_type');
        $severityLevels = $alerts->groupBy('severity_level');

        return [
            'total_alerts' => $alerts->count(),
            'alert_types' => $alertTypes->map->count()->toArray(),
            'severity_distribution' => $severityLevels->map->count()->toArray(),
            'avg_resolution_time' => $this->getAverageResolutionTime($alerts),
            'alert_trends' => $this->getAlertTrends($alerts),
        ];
    }

    /**
     * Get geographic analytics
     */
    private function getGeographicAnalytics(string $tenantId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $geographicData = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('
                shipping_address,
                shipping_city,
                COUNT(*) as shipment_count,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_count,
                AVG(CASE WHEN status = "delivered" AND actual_delivery IS NOT NULL AND estimated_delivery IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, estimated_delivery, actual_delivery) ELSE NULL END) as avg_delay
            ')
            ->groupBy('shipping_city', 'shipping_address')
            ->orderByDesc('shipment_count')
            ->limit(10)
            ->get();

        // Process the data to extract proper city names
        $processedData = $geographicData->map(function ($item) {
            // Use shipping_city if available, otherwise extract from shipping_address
            $city = $item->shipping_city;
            if (empty($city) && !empty($item->shipping_address)) {
                // Try to extract city from address - look for common patterns
                $address = $item->shipping_address;
                
                // Pattern 1: Look for "City, Area" pattern
                if (preg_match('/,([^,]+),?\s*\d{5}/', $address, $matches)) {
                    $city = trim($matches[1]);
                }
                // Pattern 2: Look for Greek city names (common patterns)
                elseif (preg_match('/(Θεσσαλονίκη|Αθήνα|Πάτρα|Ηράκλειο|Λάρισα|Βόλος|Ιωάννινα|Κομοτηνή|Καβάλα|Δράμα|Σέρρες|Κιλκίς|Πιερία|Χαλκιδική)/', $address, $matches)) {
                    $city = $matches[1];
                }
                // Pattern 3: If address contains comma, take the second part (usually city)
                elseif (strpos($address, ',') !== false) {
                    $parts = explode(',', $address);
                    if (count($parts) >= 2) {
                        $city = trim($parts[1]);
                    }
                }
                // Fallback: use first part but clean it up
                else {
                    $city = trim(explode(',', $address)[0]);
                }
            }
            
            return (object) [
                'shipping_address' => $item->shipping_address,
                'shipping_city' => $city ?: 'Unknown Location',
                'shipment_count' => $item->shipment_count,
                'delivered_count' => $item->delivered_count,
                'avg_delay' => $item->avg_delay,
            ];
        });

        return [
            'top_destinations' => $processedData->toArray(),
            'geographic_performance' => $this->getGeographicPerformance($processedData),
        ];
    }

    /**
     * Get customer analytics
     */
    private function getCustomerAnalytics(string $tenantId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $customerData = DB::table('shipments')
            ->join('customers', 'shipments.customer_id', '=', 'customers.id')
            ->where('shipments.tenant_id', $tenantId)
            ->whereBetween('shipments.created_at', $dateRange)
            ->selectRaw('
                customers.id,
                customers.name,
                customers.email,
                COUNT(shipments.id) as shipment_count,
                SUM(CASE WHEN shipments.status = "delivered" THEN 1 ELSE 0 END) as delivered_count,
                AVG(shipments.shipping_cost) as avg_shipping_cost
            ')
            ->groupBy('customers.id', 'customers.name', 'customers.email')
            ->orderByDesc('shipment_count')
            ->limit(10)
            ->get();

        return [
            'top_customers' => $customerData->toArray(),
            'customer_retention' => $this->getCustomerRetention($tenantId, $dateRange),
            'customer_satisfaction' => $this->getCustomerSatisfaction($customerData),
        ];
    }

    /**
     * Get courier analytics
     */
    private function getCourierAnalytics(string $tenantId, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $courierData = DB::table('shipments')
            ->join('couriers', 'shipments.courier_id', '=', 'couriers.id')
            ->where('shipments.tenant_id', $tenantId)
            ->whereBetween('shipments.created_at', $dateRange)
            ->selectRaw('
                couriers.id,
                couriers.name,
                couriers.code,
                COUNT(shipments.id) as shipment_count,
                SUM(CASE WHEN shipments.status = "delivered" THEN 1 ELSE 0 END) as delivered_count,
                AVG(CASE WHEN shipments.status = "delivered" AND actual_delivery IS NOT NULL AND estimated_delivery IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, estimated_delivery, actual_delivery) ELSE NULL END) as avg_delay
            ')
            ->groupBy('couriers.id', 'couriers.name', 'couriers.code')
            ->orderByDesc('shipment_count')
            ->get();

        return [
            'courier_performance' => $courierData->toArray(),
            'courier_rankings' => $this->getCourierRankings($courierData),
            'courier_reliability' => $this->getCourierReliability($courierData),
        ];
    }

    /**
     * Helper methods
     */
    private function getDateRange(array $filters): array
    {
        $start = $filters['start_date'] ?? now()->subDays(30);
        $end = $filters['end_date'] ?? now();
        
        return [
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->endOfDay(),
        ];
    }

    private function getPreviousPeriod(array $dateRange): array
    {
        $duration = $dateRange[1]->diffInDays($dateRange[0]);
        return [
            $dateRange[0]->copy()->subDays($duration),
            $dateRange[0]->copy()->subSecond(),
        ];
    }

    private function getGroupByPeriod(array $filters): string
    {
        $period = $filters['period'] ?? 'daily';
        
        return match ($period) {
            'hourly' => '%Y-%m-%d %H:00:00',
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    private function calculateGrowthRate(float $current, float $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function calculatePerformanceScore(string $tenantId, array $dateRange): float
    {
        // Calculate a composite performance score based on multiple factors
        $metrics = DB::table('shipments')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered,
                AVG(CASE WHEN status = "delivered" AND actual_delivery IS NOT NULL AND estimated_delivery IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, estimated_delivery, actual_delivery) ELSE NULL END) as avg_delay
            ')
            ->first();

        $successRate = $metrics->total > 0 ? ($metrics->delivered / $metrics->total) * 100 : 0;
        $delayPenalty = max(0, 100 - ($metrics->avg_delay ?? 0));
        
        return round(($successRate + $delayPenalty) / 2, 2);
    }

    private function calculateTrendDirection(array $values): string
    {
        if (count($values) < 2) return 'stable';
        
        $first = array_slice($values, 0, count($values) / 2);
        $second = array_slice($values, count($values) / 2);
        
        $firstAvg = array_sum($first) / count($first);
        $secondAvg = array_sum($second) / count($second);
        
        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;
        
        if ($change > 5) return 'increasing';
        if ($change < -5) return 'decreasing';
        return 'stable';
    }

    private function getConfidenceTrend($predictiveEtas): array
    {
        return $predictiveEtas->groupBy(function ($eta) {
            return $eta->created_at->format('Y-m-d');
        })->map(function ($group) {
            return round($group->avg('confidence_score'), 2);
        })->toArray();
    }

    private function getDelayPredictions($predictiveEtas): array
    {
        return $predictiveEtas->map(function ($eta) {
            return [
                'shipment_id' => $eta->shipment_id,
                'predicted_delay' => $eta->predicted_eta,
                'confidence' => $eta->confidence_score,
                'risk_level' => $eta->delay_risk_level ?? 'unknown',
            ];
        })->toArray();
    }

    private function getModelPerformance($predictiveEtas): array
    {
        return [
            'total_predictions' => $predictiveEtas->count(),
            'avg_confidence' => round($predictiveEtas->avg('confidence_score'), 2),
            'high_confidence_predictions' => $predictiveEtas->where('confidence_score', '>', 0.8)->count(),
            'low_confidence_predictions' => $predictiveEtas->where('confidence_score', '<', 0.5)->count(),
        ];
    }

    private function getAverageResolutionTime($alerts): float
    {
        $resolvedAlerts = $alerts->where('status', 'resolved');
        if ($resolvedAlerts->isEmpty()) return 0;
        
        $totalTime = $resolvedAlerts->sum(function ($alert) {
            return $alert->resolved_at ? $alert->resolved_at->diffInHours($alert->triggered_at) : 0;
        });
        
        return round($totalTime / $resolvedAlerts->count(), 2);
    }

    private function getAlertTrends($alerts): array
    {
        return $alerts->groupBy(function ($alert) {
            return $alert->triggered_at->format('Y-m-d');
        })->map->count()->toArray();
    }

    private function getGeographicPerformance($geographicData): array
    {
        return $geographicData->map(function ($location) {
            return [
                'location' => $location->shipping_city,
                'success_rate' => $location->shipment_count > 0 
                    ? round(($location->delivered_count / $location->shipment_count) * 100, 2)
                    : 0,
                'avg_delay' => round($location->avg_delay ?? 0, 2),
            ];
        })->toArray();
    }

    private function getCustomerRetention(string $tenantId, array $dateRange): float
    {
        // Simplified customer retention calculation
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        $activeCustomers = Customer::where('tenant_id', $tenantId)
            ->whereHas('shipments', function ($query) use ($dateRange) {
                $query->whereBetween('created_at', $dateRange);
            })
            ->count();
        
        return $totalCustomers > 0 ? round(($activeCustomers / $totalCustomers) * 100, 2) : 0;
    }

    private function getCustomerSatisfaction($customerData): array
    {
        return $customerData->map(function ($customer) {
            return [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'satisfaction_score' => $customer->shipment_count > 0 
                    ? round(($customer->delivered_count / $customer->shipment_count) * 100, 2)
                    : 0,
                'avg_order_value' => round($customer->avg_shipping_cost, 2),
            ];
        })->toArray();
    }

    private function getCourierRankings($courierData): array
    {
        return $courierData->sortByDesc(function ($courier) {
            $successRate = $courier->shipment_count > 0 
                ? ($courier->delivered_count / $courier->shipment_count) * 100 
                : 0;
            $delayPenalty = max(0, 100 - ($courier->avg_delay ?? 0));
            return ($successRate + $delayPenalty) / 2;
        })->values()->toArray();
    }

    private function getCourierReliability($courierData): array
    {
        return $courierData->map(function ($courier) {
            return [
                'courier_id' => $courier->id,
                'name' => $courier->name,
                'reliability_score' => $courier->shipment_count > 0 
                    ? round(($courier->delivered_count / $courier->shipment_count) * 100, 2)
                    : 0,
                'avg_delay' => round($courier->avg_delay ?? 0, 2),
            ];
        })->toArray();
    }

    /**
     * Calculate median value from array of numbers
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        
        if ($count % 2 === 0) {
            // Even number of elements - average of two middle values
            $middle1 = $values[($count / 2) - 1];
            $middle2 = $values[$count / 2];
            return ($middle1 + $middle2) / 2;
        } else {
            // Odd number of elements - middle value
            return $values[intval($count / 2)];
        }
    }
}
