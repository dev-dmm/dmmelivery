<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\NotificationLog;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Get tenant from service container (should be set by IdentifyTenant middleware)
        $tenant = app()->has('tenant') ? app('tenant') : null;
        
        // Ensure we have a tenant
        if (!$tenant) {
            return redirect()->route('login')->with('error', 'Unable to identify your tenant. Please log in again.');
        }

        // Get selected time period (default to last 30 days)
        $period = $request->get('period', '30_days');
        $startDate = $this->getStartDateForPeriod($period);

        // Get dashboard statistics (scoped to tenant and time period)
        $stats = [
            'total_shipments' => Shipment::when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate);
            })->count(),
            'pending_shipments' => Shipment::where('status', 'pending')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })->count(),
            'picked_up_shipments' => Shipment::where('status', 'picked_up')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })->count(),
            'in_transit_shipments' => Shipment::where('status', 'in_transit')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })->count(),
            'out_for_delivery_shipments' => Shipment::where('status', 'out_for_delivery')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })->count(),
            'delivered_shipments' => Shipment::where('status', 'delivered')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })->count(),
            'failed_shipments' => Shipment::where('status', 'failed')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })->count(),
            'returned_shipments' => Shipment::where('status', 'returned')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })->count(),
            'total_customers' => Customer::count(),
            'total_couriers' => Courier::where('is_active', true)->count(),
        ];

        // Calculate delivery performance
        $totalCompleted = $stats['delivered_shipments'] + $stats['returned_shipments'] + $stats['failed_shipments'];
        $stats['delivery_success_rate'] = $totalCompleted > 0 ? round(($stats['delivered_shipments'] / $totalCompleted) * 100, 1) : 0;

        // Get recent shipments (scoped to tenant)
        $recentShipments = Shipment::with(['customer', 'courier'])
            ->latest()
            ->limit(10)
            ->get();

        // Get shipment trends based on period
        $weeklyStats = $this->getShipmentTrends($period);

        // Get chart data for bar chart (Greek labels)
        $chartData = [
            'labels' => ['Παραδοτέα', 'Εκρεμούν', 'Σε μεταφορά', 'Προς παράδοση', 'Αποτυχημένα', 'Επιστραφήκαν'],
            'data' => [
                $stats['delivered_shipments'],
                $stats['pending_shipments'],
                $stats['in_transit_shipments'],
                $stats['out_for_delivery_shipments'],
                $stats['failed_shipments'],
                $stats['returned_shipments'],
            ],
            'colors' => [
                '#10B981', // Green for delivered
                '#F59E0B', // Yellow for pending
                '#3B82F6', // Blue for in transit
                '#8B5CF6', // Purple for out for delivery
                '#EF4444', // Red for failed
                '#6B7280', // Gray for returned
            ]
        ];

        // Top performing couriers
        $courierStats = Courier::withCount([
            'shipments' => function ($query) use ($startDate) {
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
            },
            'shipments as delivered_count' => function ($query) use ($startDate) {
                $query->where('status', 'delivered');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
            },
            'shipments as pending_count' => function ($query) use ($startDate) {
                $query->where('status', 'pending');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
            },
            'shipments as picked_up_count' => function ($query) use ($startDate) {
                $query->where('status', 'picked_up');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
            },
            'shipments as in_transit_count' => function ($query) use ($startDate) {
                $query->where('status', 'in_transit');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
            },
            'shipments as out_for_delivery_count' => function ($query) use ($startDate) {
                $query->where('status', 'out_for_delivery');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
            },
            'shipments as failed_count' => function ($query) use ($startDate) {
                $query->whereIn('status', ['failed', 'returned']);
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
            }
        ])->get()->map(function ($courier) {
            $pendingTotal = $courier->pending_count + $courier->picked_up_count + 
                           $courier->in_transit_count + $courier->out_for_delivery_count;
            
            return [
                'name' => $courier->name,
                'code' => $courier->code,
                'total_shipments' => $courier->shipments_count,
                'delivered_shipments' => $courier->delivered_count,
                'pending_shipments' => $pendingTotal,
                'failed_shipments' => $courier->failed_count,
                'success_rate' => $courier->shipments_count > 0 
                    ? round(($courier->delivered_count / $courier->shipments_count) * 100, 1) 
                    : 0,
            ];
        })->sortByDesc('total_shipments');

        // Recent notifications (simplified for now)
        $recentNotifications = collect([]);

        // Time period options
        $periodOptions = [
            '24_hours' => 'Τελευταίες 24 Ώρες',
            '7_days' => 'Τελευταίες 7 Ημέρες',
            '30_days' => 'Τελευταίες 30 Ημέρες',
            '3_months' => 'Τελευταίους 3 Μήνες',
            '12_months' => 'Τελευταίους 12 Μήνες',
            '24_months' => 'Τελευταίους 24 Μήνες',
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentShipments' => $recentShipments,
            'weeklyStats' => $weeklyStats,
            'chartData' => $chartData,
            'courierStats' => $courierStats->values(),
            'recentNotifications' => $recentNotifications,
            'selectedPeriod' => $period,
            'periodOptions' => $periodOptions,
            'tenant' => [
                'name' => $tenant->name,
                'branding' => $tenant->branding_config,
            ],
        ]);
    }

    private function getStartDateForPeriod($period): ?Carbon
    {
        switch ($period) {
            case '24_hours':
                return Carbon::now()->subHours(24);
            case '7_days':
                return Carbon::now()->subDays(7);
            case '30_days':
                return Carbon::now()->subDays(30);
            case '3_months':
                return Carbon::now()->subMonths(3);
            case '12_months':
                return Carbon::now()->subMonths(12);
            case '24_months':
                return Carbon::now()->subMonths(24);
            default:
                return null; // All time
        }
    }

    private function getShipmentTrends($period): array
    {
        $days = match($period) {
            '24_hours' => 1,
            '7_days' => 7,
            '30_days' => 30,
            '3_months' => 90,
            '12_months' => 365,
            '24_months' => 730,
            default => 30
        };

        $trends = [];
        $step = $days > 30 ? max(1, intval($days / 15)) : 1; // Adaptive step

        for ($i = $days - 1; $i >= 0; $i -= $step) {
            $date = Carbon::now()->subDays($i);
            $trends[] = [
                'date' => $date->format($days > 30 ? 'M j' : 'M j'),
                'shipments' => Shipment::whereDate('created_at', $date)->count(),
                'delivered' => Shipment::whereDate('actual_delivery', $date)->count(),
            ];
        }

        return $trends;
    }
}