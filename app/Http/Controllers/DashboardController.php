<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Courier;
use App\Models\NotificationLog;
use App\Http\Resources\TenantResource;
use App\Http\Resources\ShipmentResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Tenant
        $tenant = app()->has('tenant') ? app('tenant') : null;
        if (!$tenant) {
            return redirect()->route('login')->with('error', 'Unable to identify your tenant. Please log in again.');
        }

        // Period
        $period    = $request->get('period', '30_days');
        $startDate = $this->getStartDateForPeriod($period);

        // Stats (scoped)
        $stats = [
            'total_shipments' => Shipment::where('tenant_id', $tenant->id)
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'pending_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'pending')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'picked_up_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'picked_up')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'in_transit_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'in_transit')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'out_for_delivery_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'out_for_delivery')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'delivered_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'delivered')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'failed_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'failed')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'returned_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'returned')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->count(),

            'total_customers' => Customer::where('tenant_id', $tenant->id)->count(),
            'total_couriers'  => Courier::where('tenant_id', $tenant->id)->where('is_active', true)->count(),
        ];

        $totalCompleted = $stats['delivered_shipments'] + $stats['returned_shipments'] + $stats['failed_shipments'];
        $stats['delivery_success_rate'] = $totalCompleted > 0
            ? round(($stats['delivered_shipments'] / $totalCompleted) * 100, 1)
            : 0;

        $recentShipments = Shipment::with(['customer', 'courier'])
            ->where('tenant_id', $tenant->id)
            ->latest()->limit(10)->get();

        $weeklyStats = $this->getShipmentTrends($period);

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
            'colors' => ['#10B981','#F59E0B','#3B82F6','#8B5CF6','#EF4444','#6B7280']
        ];

        // Simple courier leaderboard (kept from your original index)
        $courierStats = Courier::where('tenant_id', $tenant->id)
            ->withCount([
                'shipments' => function ($q) use ($startDate, $tenant) {
                    $q->where('tenant_id', $tenant->id);
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                },
                'shipments as delivered_count' => function ($q) use ($startDate, $tenant) {
                    $q->where('tenant_id', $tenant->id)->where('status', 'delivered');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                },
                'shipments as pending_count' => function ($q) use ($startDate, $tenant) {
                    $q->where('tenant_id', $tenant->id)->where('status', 'pending');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                },
                'shipments as picked_up_count' => function ($q) use ($startDate, $tenant) {
                    $q->where('tenant_id', $tenant->id)->where('status', 'picked_up');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                },
                'shipments as in_transit_count' => function ($q) use ($startDate, $tenant) {
                    $q->where('tenant_id', $tenant->id)->where('status', 'in_transit');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                },
                'shipments as out_for_delivery_count' => function ($q) use ($startDate, $tenant) {
                    $q->where('tenant_id', $tenant->id)->where('status', 'out_for_delivery');
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                },
                'shipments as failed_count' => function ($q) use ($startDate, $tenant) {
                    $q->where('tenant_id', $tenant->id)->whereIn('status', ['failed','returned']);
                    if ($startDate) $q->where('created_at', '>=', $startDate);
                },
            ])->get()->map(function ($courier) {
                $pendingTotal = $courier->pending_count + $courier->picked_up_count
                    + $courier->in_transit_count + $courier->out_for_delivery_count;

                return [
                    'name'              => $courier->name,
                    'code'              => $courier->code,
                    'total_shipments'   => $courier->shipments_count,
                    'delivered_shipments'=> $courier->delivered_count,
                    'pending_shipments' => $pendingTotal,
                    'failed_shipments'  => $courier->failed_count,
                    'success_rate'      => $courier->shipments_count > 0
                        ? round(($courier->delivered_count / $courier->shipments_count) * 100, 1)
                        : 0,
                ];
            })->sortByDesc('total_shipments');

        $recentNotifications = collect([]);

        $periodOptions = [
            '24_hours' => 'Τελευταίες 24 Ώρες',
            '7_days'   => 'Τελευταίες 7 Ημέρες',
            '30_days'  => 'Τελευταίες 30 Ημέρες',
            '3_months' => 'Τελευταίους 3 Μήνες',
            '12_months'=> 'Τελευταίους 12 Μήνες',
            '24_months'=> 'Τελευταίους 24 Μήνες',
        ];

        return Inertia::render('Dashboard', [
            'stats'               => $stats,
            'recentShipments'     => $recentShipments,
            'weeklyStats'         => $weeklyStats,
            'chartData'           => $chartData,
            'courierStats'        => $courierStats->values(),
            'recentNotifications' => $recentNotifications,
            'selectedPeriod'      => $period,
            'periodOptions'       => $periodOptions,
            'tenant'              => new TenantResource($tenant),
        ]);
    }

    public function courierPerformance(Request $request)
    {
        // ---- Tenant (required)
        $tenant = app()->has('tenant') ? app('tenant') : null;
        if (!$tenant) {
            return redirect()->route('login')->with('error', 'Unable to identify your tenant. Please log in again.');
        }

        // ---- Filters
        $selectedPeriod = $request->get('period', 'all');
        $selectedArea   = $request->get('area', 'all');
        $startDate      = $this->getStartDateForPeriod($selectedPeriod);

        // Common base scope for shipments
        $base = DB::table('shipments as s')
            ->where('s.tenant_id', $tenant->id)
            ->when($startDate, fn($q) => $q->where('s.created_at', '>=', $startDate))
            ->when($selectedArea !== 'all', fn($q) => $q->where('s.shipping_address', 'like', "%{$selectedArea}%"));

        // ---- Overall stats (single round-trip)
        $totals = (clone $base)->selectRaw("
            SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) as delivered_shipments,
            SUM(CASE WHEN s.status = 'in_transit' THEN 1 ELSE 0 END) as in_transit_shipments,
            SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending_shipments,
            SUM(CASE WHEN s.status IN ('failed','returned') THEN 1 ELSE 0 END) as delayed_shipments,
            COUNT(*) as total_shipments
        ")->first();

        $stats = [
            'delivered_shipments'  => (int)($totals->delivered_shipments ?? 0),
            'in_transit_shipments' => (int)($totals->in_transit_shipments ?? 0),
            'pending_shipments'    => (int)($totals->pending_shipments ?? 0),
            'delayed_shipments'    => (int)($totals->delayed_shipments ?? 0),
        ];
        $total = (int)($totals->total_shipments ?? 0);
        $stats['delivered_percentage']  = $total ? round(100 * $stats['delivered_shipments']  / $total, 1) : 0;
        $stats['in_transit_percentage'] = $total ? round(100 * $stats['in_transit_shipments'] / $total, 1) : 0;
        $stats['pending_percentage']    = $total ? round(100 * $stats['pending_shipments']    / $total, 1) : 0;
        $stats['delayed_percentage']    = $total ? round(100 * $stats['delayed_shipments']    / $total, 1) : 0;

        // ---- Courier stats from shipments; LEFT JOIN couriers just for name (no grade/code required)
        $courierRows = (clone $base)
            ->leftJoin('couriers as c', 'c.id', '=', 's.courier_id')
            ->selectRaw("
                s.courier_id as id,
                COALESCE(c.name, CONCAT('Courier ', LEFT(COALESCE(s.courier_id, ''), 6))) as name,

                COUNT(*) as total_shipments,
                SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN s.status = 'returned'  THEN 1 ELSE 0 END) as returned_count,
                SUM(CASE WHEN s.status = 'failed'    THEN 1 ELSE 0 END) as failed_count,

                AVG(CASE 
                    WHEN s.status = 'delivered' AND s.actual_delivery IS NOT NULL AND s.created_at IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, s.created_at, s.actual_delivery)
                    ELSE NULL
                END) as avg_delivery_time_hours
            ")
            ->groupBy('s.courier_id', 'c.name')
            ->orderByDesc(DB::raw('SUM(CASE WHEN s.status = "delivered" THEN 1 ELSE 0 END)'))
            ->get();

        $courierStats = $courierRows
            ->filter(fn($r) => (int)$r->total_shipments > 0)
            ->map(function ($r) {
                $total     = (int)$r->total_shipments;
                $delivered = (int)$r->delivered_count;
                $returned  = (int)$r->returned_count;

                $deliveredPct = $total ? round(100 * $delivered / $total, 1) : 0;
                $returnedPct  = $total ? round(100 * $returned  / $total, 1) : 0;
                $otherPct     = $total ? round(100 * ($total - $delivered - $returned) / $total, 1) : 0;

                $avgHours   = is_null($r->avg_delivery_time_hours) ? null : (float)$r->avg_delivery_time_hours;
                $avgDisplay = is_null($avgHours) ? '—' : (round($avgHours, 1) . 'h');

                // calculatePerformanceGrade expects days; pass big value when unknown
                $daysForGrade = is_null($avgHours) ? 999 : $avgHours / 24.0;

                return [
                    'id'                    => $r->id,
                    'name'                  => $r->name,
                    'code'                  => $r->id,   // fallback (no couriers.code)
                    'grade'                 => $this->calculatePerformanceGrade($deliveredPct, $daysForGrade),
                    'total_shipments'       => $total,
                    'delivered_count'       => $delivered,
                    'returned_count'        => $returned,
                    'failed_count'          => (int)$r->failed_count,
                    'delivered_percentage'  => $deliveredPct,
                    'returned_percentage'   => $returnedPct,
                    'other_percentage'      => $otherPct,
                    'avg_delivery_time'     => $avgDisplay,
                ];
            })
            ->values();

        // ---- Areas (first part of address before comma)
        $areas = DB::table('shipments as s')
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('shipping_address')
            ->selectRaw("TRIM(SUBSTRING_INDEX(s.shipping_address, ',', 1)) as area")
            ->groupBy('area')
            ->pluck('area')
            ->filter()
            ->values();

        $periodOptions = [
            'all'  => 'Όλη η ιστορία',
            '7'    => 'Τελευταία 7 ημέρες',
            '30'   => 'Τελευταία 30 ημέρες',
            '90'   => 'Τελευταία 90 ημέρες',
            '365'  => 'Τελευταίος χρόνος',
        ];

        return Inertia::render('CourierPerformance', [
            'stats'          => $stats,
            'courierStats'   => $courierStats,
            'areas'          => $areas,
            'selectedPeriod' => $selectedPeriod,
            'selectedArea'   => $selectedArea,
            'periodOptions'  => $periodOptions,
        ]);
    }

    private function calculatePerformanceGrade($deliveredPercentage, $avgDeliveryTimeDays)
    {
        if ($deliveredPercentage >= 95 && $avgDeliveryTimeDays <= 2)   return 'A+';
        if ($deliveredPercentage >= 90 && $avgDeliveryTimeDays <= 3)   return 'A';
        if ($deliveredPercentage >= 85 && $avgDeliveryTimeDays <= 4)   return 'B+';
        if ($deliveredPercentage >= 80 && $avgDeliveryTimeDays <= 5)   return 'B';
        if ($deliveredPercentage >= 75)                                return 'C+';
        return 'C';
    }

    private function getStartDateForPeriod($period): ?Carbon
    {
        switch ($period) {
            case '24_hours': return Carbon::now()->subHours(24);
            case '7':
            case '7_days':   return Carbon::now()->subDays(7);
            case '30':
            case '30_days':  return Carbon::now()->subDays(30);
            case '90':
            case '3_months': return Carbon::now()->subMonths(3);
            case '365':
            case '12_months':return Carbon::now()->subMonths(12);
            case '24_months':return Carbon::now()->subMonths(24);
            default:         return null; // All time
        }
    }

    private function getShipmentTrends($period): array
    {
        $days = match ($period) {
            '24_hours' => 1,
            '7_days'   => 7,
            '30_days'  => 30,
            '3_months' => 90,
            '12_months'=> 365,
            '24_months'=> 730,
            default    => 30
        };

        $trends = [];
        $step = $days > 30 ? max(1, intval($days / 15)) : 1;

        for ($i = $days - 1; $i >= 0; $i -= $step) {
            $date = Carbon::now()->subDays($i);
            $trends[] = [
                'date'      => $date->format('M j'),
                'shipments' => Shipment::whereDate('created_at', $date)->count(),
                'delivered' => Shipment::whereDate('actual_delivery', $date)->count(),
            ];
        }

        return $trends;
        }
}
