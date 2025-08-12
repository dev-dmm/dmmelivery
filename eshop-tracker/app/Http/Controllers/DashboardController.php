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

    public function courierPerformance(Request $request)
    {
        $selectedPeriod = $request->get('period', 'all');
        $selectedArea = $request->get('area', 'all');
        
        $startDate = $this->getStartDateForPeriod($selectedPeriod);
        
        // Get overall shipment statistics
        $stats = [
            'delivered_shipments' => Shipment::where('status', 'delivered')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })
                ->when($selectedArea !== 'all', function ($query) use ($selectedArea) {
                    return $query->where('shipping_address', 'like', "%{$selectedArea}%");
                })
                ->count(),
            'in_transit_shipments' => Shipment::where('status', 'in_transit')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })
                ->when($selectedArea !== 'all', function ($query) use ($selectedArea) {
                    return $query->where('shipping_address', 'like', "%{$selectedArea}%");
                })
                ->count(),
            'pending_shipments' => Shipment::where('status', 'pending')
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })
                ->when($selectedArea !== 'all', function ($query) use ($selectedArea) {
                    return $query->where('shipping_address', 'like', "%{$selectedArea}%");
                })
                ->count(),
            'delayed_shipments' => Shipment::whereIn('status', ['failed', 'returned'])
                ->when($startDate, function ($query) use ($startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })
                ->when($selectedArea !== 'all', function ($query) use ($selectedArea) {
                    return $query->where('shipping_address', 'like', "%{$selectedArea}%");
                })
                ->count(),
        ];
        
        $totalShipments = array_sum($stats);
        
        // Calculate percentages
        $stats['delivered_percentage'] = $totalShipments > 0 ? round(($stats['delivered_shipments'] / $totalShipments) * 100, 1) : 0;
        $stats['in_transit_percentage'] = $totalShipments > 0 ? round(($stats['in_transit_shipments'] / $totalShipments) * 100, 1) : 0;
        $stats['pending_percentage'] = $totalShipments > 0 ? round(($stats['pending_shipments'] / $totalShipments) * 100, 1) : 0;
        $stats['delayed_percentage'] = $totalShipments > 0 ? round(($stats['delayed_shipments'] / $totalShipments) * 100, 1) : 0;
        
        // Get courier performance metrics
        $courierStats = Courier::withCount([
            'shipments as total_shipments' => function ($query) use ($startDate, $selectedArea) {
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
                if ($selectedArea !== 'all') {
                    $query->where('shipping_address', 'like', "%{$selectedArea}%");
                }
            },
            'shipments as delivered_count' => function ($query) use ($startDate, $selectedArea) {
                $query->where('status', 'delivered');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
                if ($selectedArea !== 'all') {
                    $query->where('shipping_address', 'like', "%{$selectedArea}%");
                }
            },
            'shipments as returned_count' => function ($query) use ($startDate, $selectedArea) {
                $query->where('status', 'returned');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
                if ($selectedArea !== 'all') {
                    $query->where('shipping_address', 'like', "%{$selectedArea}%");
                }
            },
            'shipments as failed_count' => function ($query) use ($startDate, $selectedArea) {
                $query->where('status', 'failed');
                if ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }
                if ($selectedArea !== 'all') {
                    $query->where('shipping_address', 'like', "%{$selectedArea}%");
                }
            }
        ])->get();
        

        
        $courierStats = $courierStats->filter(function ($courier) {
            return $courier->total_shipments > 0;
        })->map(function ($courier) {
            $total = $courier->total_shipments;
            $delivered = $courier->delivered_count;
            $returned = $courier->returned_count;
            $failed = $courier->failed_count;
            
            $deliveredPercentage = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;
            $returnedPercentage = $total > 0 ? round(($returned / $total) * 100, 1) : 0;
            $otherPercentage = $total > 0 ? round((($total - $delivered - $returned) / $total) * 100, 1) : 0;
            
            // Calculate average delivery time
            $avgDeliveryTime = Shipment::where('courier_id', $courier->id)
                ->where('status', 'delivered')
                ->whereNotNull('actual_delivery')
                ->whereNotNull('created_at')
                ->get()
                ->avg(function ($shipment) {
                    return $shipment->created_at->diffInDays($shipment->actual_delivery);
                });
            
            // Calculate performance grade
            $grade = $this->calculatePerformanceGrade($deliveredPercentage, $avgDeliveryTime);
            
            return [
                'id' => $courier->id,
                'name' => $courier->name,
                'code' => $courier->code,
                'total_shipments' => $total,
                'delivered_count' => $delivered,
                'returned_count' => $returned,
                'failed_count' => $failed,
                'delivered_percentage' => $deliveredPercentage,
                'returned_percentage' => $returnedPercentage,
                'other_percentage' => $otherPercentage,
                'avg_delivery_time' => round($avgDeliveryTime, 1),
                'grade' => $grade,
            ];
        })->sortByDesc('total_shipments');
        
        // Get available areas for filtering
        $areas = Shipment::distinct()
            ->whereNotNull('shipping_address')
            ->pluck('shipping_address')
            ->map(function ($address) {
                // Extract city/area from address
                $parts = explode(',', $address);
                return trim($parts[0] ?? $address);
            })
            ->unique()
            ->filter()
            ->values();
        

        
        $periodOptions = [
            'all' => 'Όλη η ιστορία',
            '7' => 'Τελευταία 7 ημέρες',
            '30' => 'Τελευταία 30 ημέρες',
            '90' => 'Τελευταία 90 ημέρες',
            '365' => 'Τελευταία χρόνος',
        ];
        
        return Inertia::render('CourierPerformance', [
            'stats' => $stats,
            'courierStats' => $courierStats,
            'areas' => $areas,
            'selectedPeriod' => $selectedPeriod,
            'selectedArea' => $selectedArea,
            'periodOptions' => $periodOptions,
        ]);
    }
    
    private function calculatePerformanceGrade($deliveredPercentage, $avgDeliveryTime)
    {
        if ($deliveredPercentage >= 95 && $avgDeliveryTime <= 2) {
            return 'A+';
        } elseif ($deliveredPercentage >= 90 && $avgDeliveryTime <= 3) {
            return 'A';
        } elseif ($deliveredPercentage >= 85 && $avgDeliveryTime <= 4) {
            return 'B+';
        } elseif ($deliveredPercentage >= 80 && $avgDeliveryTime <= 5) {
            return 'B';
        } elseif ($deliveredPercentage >= 75) {
            return 'C+';
        } else {
            return 'C';
        }
    }

    private function getStartDateForPeriod($period): ?Carbon
    {
        switch ($period) {
            case '24_hours':
                return Carbon::now()->subHours(24);
            case '7':
            case '7_days':
                return Carbon::now()->subDays(7);
            case '30':
            case '30_days':
                return Carbon::now()->subDays(30);
            case '90':
            case '3_months':
                return Carbon::now()->subMonths(3);
            case '365':
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