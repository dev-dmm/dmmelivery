<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Courier;
use App\Http\Resources\TenantResource;
use App\Services\Contracts\AnalyticsServiceInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $tenant = $user->tenant;
        if (!$tenant || !$tenant->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Your account is inactive or tenant not found.');
        }

        if (!app()->bound('tenant')) {
            app()->instance('tenant', $tenant);
        }

        $tenantId  = $tenant->id;
        $period    = $request->get('period', '30_days');
        $startDate = $this->getStartDateForPeriod($period, $request);
        $endDate   = $this->getEndDateForPeriod($period, $request);

        $stats = [
            'total_shipments' => Shipment::where('tenant_id', $tenantId)
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
                ->count(),

            'pending_shipments' => Shipment::where('tenant_id', $tenantId)->where('status', 'pending')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))->count(),

            'picked_up_shipments' => Shipment::where('tenant_id', $tenantId)->where('status', 'picked_up')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))->count(),

            'in_transit_shipments' => Shipment::where('tenant_id', $tenantId)->where('status', 'in_transit')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))->count(),

            'out_for_delivery_shipments' => Shipment::where('tenant_id', $tenantId)->where('status', 'out_for_delivery')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))->count(),

            'delivered_shipments' => Shipment::where('tenant_id', $tenantId)->where('status', 'delivered')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))->count(),

            'failed_shipments' => Shipment::where('tenant_id', $tenantId)->where('status', 'failed')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))->count(),

            'returned_shipments' => Shipment::where('tenant_id', $tenantId)->where('status', 'returned')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))->count(),

            'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
            'total_couriers'  => Courier::where('tenant_id', $tenantId)->where('is_active', true)->count(),
        ];

        $totalCompleted = $stats['delivered_shipments'] + $stats['returned_shipments'] + $stats['failed_shipments'];
        $stats['delivery_success_rate'] = $totalCompleted > 0
            ? round(($stats['delivered_shipments'] / $totalCompleted) * 100, 1)
            : 0;

        $recentShipments = Shipment::with(['customer:id,name', 'courier:id,name,code'])
            ->where('tenant_id', $tenantId)
            ->latest()->limit(10)->get();

        $periodOptions = [
            '24_hours' => 'Τελευταίες 24 Ώρες',
            '7_days'   => 'Τελευταίες 7 Ημέρες',
            '30_days'  => 'Τελευταίες 30 Ημέρες',
            '3_months' => 'Τελευταίους 3 Μήνες',
            '12_months'=> 'Τελευταίους 12 Μήνες',
            '24_months'=> 'Τελευταίους 24 Μήνες',
            'custom'   => 'Προσαρμοσμένη Περίοδος',
        ];

        $lazyWeekly        = fn() => $this->getShipmentTrends($period, $tenantId, $startDate, $endDate);
        $lazyChart         = fn() => $this->buildChartData($stats);
        $lazyCouriers      = fn() => $this->courierLeaderboard($tenantId, $startDate, $endDate);
        $lazyNotifications = fn() => [];

        return Inertia::render('Dashboard', [
            'tenant'          => new TenantResource($tenant),
            'selectedPeriod'  => $period,
            'periodOptions'   => $periodOptions,
            'stats'           => $stats,
            'recentShipments' => $recentShipments,
            'weeklyStats'         => Inertia::lazy($lazyWeekly),
            'chartData'           => Inertia::lazy($lazyChart),
            'courierStats'        => Inertia::lazy($lazyCouriers),
            'recentNotifications' => Inertia::lazy($lazyNotifications),
            'customStart' => $request->get('start'),
            'customEnd'   => $request->get('end'),
        ]);
    }

    public function courierPerformance(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $tenant = $user->tenant;
        if (!$tenant || !$tenant->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error', 'Your account is inactive or tenant not found.');
        }

        if (!app()->bound('tenant')) app()->instance('tenant', $tenant);

        $tenantId     = $tenant->id;
        $period       = $request->get('period', '30_days');
        $startDate    = $this->getStartDateForPeriod($period, $request);
        $endDate      = $this->getEndDateForPeriod($period, $request);
        $selectedArea = $request->get('area', 'all');

        $periodOptions = [
            '24_hours' => 'Τελευταίες 24 Ώρες',
            '7_days'   => 'Τελευταίες 7 Ημέρες',
            '30_days'  => 'Τελευταίες 30 Ημέρες',
            '3_months' => 'Τελευταίους 3 Μήνες',
            '12_months'=> 'Τελευταίους 12 Μήνες',
            '24_months'=> 'Τελευταίους 24 Μήνες',
            'custom'   => 'Προσαρμοσμένη Περίοδος',
        ];

        // Does the DB have a shipping_city column?
        $hasCity = Schema::hasColumn('shipments', 'shipping_city');

        // Reusable area filter closure
        $applyArea = function ($q) use ($selectedArea, $hasCity) {
            if ($selectedArea === 'all') return;
            $q->where(function ($qq) use ($selectedArea, $hasCity) {
                if ($hasCity) {
                    $qq->where('shipping_city', $selectedArea);
                }
                // Match ", City," inside the address to avoid partials, plus a loose fallback
                $qq->orWhere('shipping_address', 'LIKE', '%,' . $selectedArea . ',%')
                   ->orWhere('shipping_address', 'LIKE', '%' . $selectedArea . '%');
            });
        };

        // Detailed courier performance data
        $courierStats = $this->courierLeaderboard($tenantId, $startDate, $endDate, $selectedArea, $applyArea, $hasCity);

        // Overall stats with area filter
        $totalShipments = Shipment::where('tenant_id', $tenantId)
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->tap($applyArea)
            ->count();

        $deliveredShipments = Shipment::where('tenant_id', $tenantId)
            ->where('status', 'delivered')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->tap($applyArea)
            ->count();

        $inTransitShipments = Shipment::where('tenant_id', $tenantId)
            ->whereIn('status', ['in_transit', 'out_for_delivery'])
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->tap($applyArea)
            ->count();

        $pendingShipments = Shipment::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'picked_up'])
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->tap($applyArea)
            ->count();

        $delayedShipments = Shipment::where('tenant_id', $tenantId)
            ->whereIn('status', ['failed', 'returned'])
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->tap($applyArea)
            ->count();

        $stats = [
            'delivered_shipments'    => $deliveredShipments,
            'delivered_percentage'   => $totalShipments > 0 ? round(($deliveredShipments / $totalShipments) * 100, 1) : 0,
            'in_transit_shipments'   => $inTransitShipments,
            'in_transit_percentage'  => $totalShipments > 0 ? round(($inTransitShipments / $totalShipments) * 100, 1) : 0,
            'pending_shipments'      => $pendingShipments,
            'pending_percentage'     => $totalShipments > 0 ? round(($pendingShipments / $totalShipments) * 100, 1) : 0,
            'delayed_shipments'      => $delayedShipments,
            'delayed_percentage'     => $totalShipments > 0 ? round(($delayedShipments / $totalShipments) * 100, 1) : 0,
        ];

        // Build Areas list (cities)
        $areasQuery = Shipment::where('tenant_id', $tenantId)
            ->where(function ($q) use ($hasCity) {
                if ($hasCity) {
                    $q->whereNotNull('shipping_city');
                }
                $q->orWhere(function ($qq) {
                    $qq->whereNotNull('shipping_address')
                       ->where('shipping_address', '!=', '');
                });
            });

        $select = ['shipping_address'];
        if ($hasCity) $select[] = 'shipping_city';

        $areas = $areasQuery->get($select)
            ->map(function ($s) use ($hasCity) {
                // Prefer explicit city column if present and non-empty
                if ($hasCity && !empty($s->shipping_city)) {
                    return trim($s->shipping_city);
                }

                // Fallback: parse from "Street, City, ZIP, Country"
                $addr  = $s->shipping_address ?? '';
                $parts = array_values(array_filter(array_map('trim', explode(',', $addr)), fn($p) => $p !== ''));

                if (count($parts) >= 2) {
                    $countries = ['greece','gr','united states','usa','us','uk','united kingdom','italy','de','germany','france','es','spain'];
                    $lastLower = mb_strtolower(end($parts));

                    if (in_array($lastLower, $countries, true) && count($parts) >= 2) {
                        $city = $parts[count($parts) - 2];
                    } else {
                        $city = end($parts);
                        if (preg_match('/^\d{3,6}(-\d{2,6})?$/', $city)) {
                            $city = (count($parts) >= 2) ? $parts[count($parts) - 2] : $city;
                        }
                    }

                    $city = trim($city);
                    if ($city !== '' && !in_array(mb_strtolower($city), $countries, true)) {
                        return $city;
                    }
                }
                return null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($areas)) {
            $areas = ['Athens', 'Thessaloniki', 'Patras', 'Larisa', 'Heraklion'];
        }

        return Inertia::render('CourierPerformance', [
            'tenant'         => new TenantResource($tenant),
            'selectedPeriod' => $period,
            'selectedArea'   => $selectedArea,
            'periodOptions'  => $periodOptions,
            'areas'          => $areas,
            'stats'          => $stats,
            'courierStats'   => $courierStats->map(function ($courier) {
                $totalShipments      = $courier['total_shipments'];
                $deliveredPercentage = $totalShipments > 0 ? round(($courier['delivered_shipments'] / $totalShipments) * 100, 1) : 0;
                $failedPercentage    = $totalShipments > 0 ? round(($courier['failed_shipments'] / $totalShipments) * 100, 1) : 0;
                $otherPercentage     = max(0, 100 - $deliveredPercentage - $failedPercentage);

                $grade = match (true) {
                    $deliveredPercentage >= 95 => 'A+',
                    $deliveredPercentage >= 90 => 'A',
                    $deliveredPercentage >= 85 => 'B+',
                    $deliveredPercentage >= 80 => 'B',
                    $deliveredPercentage >= 70 => 'C+',
                    default => 'C'
                };

                return [
                    'id'                   => $courier['code'],
                    'name'                 => $courier['name'],
                    'total_shipments'      => $totalShipments,
                    'delivered_percentage' => $deliveredPercentage,
                    'returned_percentage'  => $failedPercentage,
                    'other_percentage'     => $otherPercentage,
                    'avg_delivery_time'    => '2-3 ημέρες',
                    'grade'                => $grade,
                ];
            }),
            'customStart'    => $request->get('start'),
            'customEnd'      => $request->get('end'),
        ]);
    }

    private function buildChartData(array $stats): array
    {
        return [
            'labels' => ['Παραδοτέα','Εκρεμούν','Σε μεταφορά','Προς παράδοση','Αποτυχημένα','Επιστραφήκαν'],
            'data'   => [
                $stats['delivered_shipments'],
                $stats['pending_shipments'],
                $stats['in_transit_shipments'],
                $stats['out_for_delivery_shipments'],
                $stats['failed_shipments'],
                $stats['returned_shipments'],
            ],
            'colors' => ['#10B981','#F59E0B','#3B82F6','#8B5CF6','#EF4444','#6B7280'],
        ];
    }

    private function courierLeaderboard(string|int $tenantId, ?Carbon $startDate, ?Carbon $endDate = null, string $area = 'all', ?\Closure $applyArea = null, bool $hasCity = false)
    {
        // Safe default area filter if not provided
        $applyArea ??= function ($q) use ($area, $hasCity) {
            if ($area === 'all') return;
            $q->where(function ($qq) use ($area, $hasCity) {
                if ($hasCity) {
                    $qq->where('shipping_city', $area);
                }
                $qq->orWhere('shipping_address', 'LIKE', '%,' . $area . ',%')
                   ->orWhere('shipping_address', 'LIKE', '%' . $area . '%');
            });
        };

        return Courier::where('tenant_id', $tenantId)
            ->withCount([
                'shipments' => function ($q) use ($tenantId, $startDate, $endDate, $applyArea) {
                    $q->where('tenant_id', $tenantId);
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                    if ($endDate)   $q->where('created_at', '<=', $endDate);
                    $applyArea($q);
                },
                'shipments as delivered_count' => function ($q) use ($tenantId, $startDate, $endDate, $applyArea) {
                    $q->where('tenant_id', $tenantId)->where('status', 'delivered');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                    if ($endDate)   $q->where('created_at', '<=', $endDate);
                    $applyArea($q);
                },
                'shipments as pending_count' => function ($q) use ($tenantId, $startDate, $endDate, $applyArea) {
                    $q->where('tenant_id', $tenantId)->where('status', 'pending');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                    if ($endDate)   $q->where('created_at', '<=', $endDate);
                    $applyArea($q);
                },
                'shipments as picked_up_count' => function ($q) use ($tenantId, $startDate, $endDate, $applyArea) {
                    $q->where('tenant_id', $tenantId)->where('status', 'picked_up');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                    if ($endDate)   $q->where('created_at', '<=', $endDate);
                    $applyArea($q);
                },
                'shipments as in_transit_count' => function ($q) use ($tenantId, $startDate, $endDate, $applyArea) {
                    $q->where('tenant_id', $tenantId)->where('status', 'in_transit');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                    if ($endDate)   $q->where('created_at', '<=', $endDate);
                    $applyArea($q);
                },
                'shipments as out_for_delivery_count' => function ($q) use ($tenantId, $startDate, $endDate, $applyArea) {
                    $q->where('tenant_id', $tenantId)->where('status', 'out_for_delivery');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                    if ($endDate)   $q->where('created_at', '<=', $endDate);
                    $applyArea($q);
                },
                'shipments as failed_count' => function ($q) use ($tenantId, $startDate, $endDate, $applyArea) {
                    $q->where('tenant_id', $tenantId)->whereIn('status', ['failed','returned']);
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                    if ($endDate)   $q->where('created_at', '<=', $endDate);
                    $applyArea($q);
                },
            ])
            ->get()
            ->map(function ($c) {
                $pending = $c->pending_count + $c->picked_up_count + $c->in_transit_count + $c->out_for_delivery_count;
                return [
                    'name'                => $c->name,
                    'code'                => $c->code,
                    'total_shipments'     => $c->shipments_count,
                    'delivered_shipments' => $c->delivered_count,
                    'pending_shipments'   => $pending,
                    'failed_shipments'    => $c->failed_count,
                    'success_rate'        => $c->shipments_count > 0
                        ? round(($c->delivered_count / $c->shipments_count) * 100, 1)
                        : 0,
                ];
            })
            ->sortByDesc('total_shipments')
            ->values();
    }

    private function getStartDateForPeriod($period, Request $request = null): ?Carbon
    {
        if ($period === 'custom' && $request && $request->has('start')) {
            return Carbon::parse($request->get('start'));
        }

        return match ($period) {
            '24_hours' => Carbon::now()->subHours(24),
            '7_days'   => Carbon::now()->subDays(7),
            '30_days'  => Carbon::now()->subDays(30),
            '3_months' => Carbon::now()->subMonths(3),
            '12_months'=> Carbon::now()->subMonths(12),
            '24_months'=> Carbon::now()->subMonths(24),
            'custom'   => null,
            default    => null,
        };
    }

    private function getEndDateForPeriod($period, Request $request = null): ?Carbon
    {
        if ($period === 'custom' && $request && $request->has('end')) {
            return Carbon::parse($request->get('end'));
        }

        return match ($period) {
            '24_hours', '7_days', '30_days', '3_months', '12_months', '24_months' => Carbon::now(),
            'custom'   => null,
            default    => null,
        };
    }

    private function getShipmentTrends(string $period, string|int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        if ($period === 'custom' && $startDate && $endDate) {
            $trends = [];
            $totalDays = $startDate->diffInDays($endDate) + 1;
            $step = $totalDays > 30 ? max(1, (int)($totalDays / 15)) : 1;

            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $trends[] = [
                    'date'      => $currentDate->format('M j'),
                    'shipments' => Shipment::where('tenant_id', $tenantId)->whereDate('created_at', $currentDate)->count(),
                    'delivered' => Shipment::where('tenant_id', $tenantId)->whereDate('actual_delivery', $currentDate)->count(),
                ];
                $currentDate->addDays($step);
            }

            return $trends;
        }

        $days = match ($period) {
            '24_hours' => 1,
            '7_days'   => 7,
            '30_days'  => 30,
            '3_months' => 90,
            '12_months'=> 365,
            '24_months'=> 730,
            default    => 30,
        };

        $trends = [];
        $step = $days > 30 ? max(1, (int)($days / 15)) : 1;

        for ($i = $days - 1; $i >= 0; $i -= $step) {
            $date = Carbon::now()->subDays($i);
            $trends[] = [
                'date'      => $date->format('M j'),
                'shipments' => Shipment::where('tenant_id', $tenantId)->whereDate('created_at', $date)->count(),
                'delivered' => Shipment::where('tenant_id', $tenantId)->whereDate('actual_delivery', $date)->count(),
            ];
        }

        return $trends;
    }

    /**
     * Display real-time dashboard
     */
    public function realtime(Request $request): InertiaResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;
        
        // Get basic stats for real-time dashboard
        $stats = [
            'total_shipments' => Shipment::where('tenant_id', $tenant->id)->count(),
            'delivered_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'delivered')->count(),
            'in_transit_shipments' => Shipment::where('tenant_id', $tenant->id)->whereIn('status', ['in_transit', 'out_for_delivery'])->count(),
            'pending_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
        ];

        return Inertia::render('RealtimeDashboard', [
            'tenantId' => $tenant->id,
            'userId' => $user->id,
            'initialStats' => $stats,
        ]);
    }
}
