<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display the customer profile page
     */
    public function show(Customer $customer): Response
    {
        // Get tenant for policy checking
        $tenant = request()->attributes->get('tenant') ?? auth()->user()?->tenant;
        $canViewGlobal = (bool) ($tenant?->can_view_global_scores ?? false);

        // Ensure customer belongs to current tenant (via TenantScope)
        // Load relationships and counts efficiently using withCount to avoid N+1 queries
        $customer->load([
            'globalCustomer', // Load global customer for global score calculation
            'shipments' => function ($query) {
                $query->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->with(['courier:id,name,code', 'statusHistory' => function ($q) {
                        $q->latest('happened_at')->limit(1);
                    }]);
            }
        ]);

        // Use withCount() to get all counts in a single query instead of multiple count() calls
        $customer->loadCount([
            'shipments as total_shipments',
            'shipments as completed_shipments' => fn($q) => 
                $q->whereIn('status', ['delivered', 'returned', 'cancelled']),
            'shipments as delivered_shipments' => fn($q) => 
                $q->where('status', 'delivered'),
            'shipments as returned_shipments' => fn($q) => 
                $q->where('status', 'returned'),
            'shipments as cancelled_shipments' => fn($q) => 
                $q->where('status', 'cancelled'),
            'shipments as pending_shipments' => fn($q) => 
                $q->whereIn('status', ['pending', 'picked_up', 'in_transit', 'out_for_delivery']),
            'shipments as failed_shipments' => fn($q) => 
                $q->where('status', 'failed'),
        ]);

        // Get statistics from loaded counts
        $stats = [
            'total_shipments' => $customer->total_shipments,
            'completed_shipments' => $customer->completed_shipments,
            'delivered_shipments' => $customer->delivered_shipments,
            'returned_shipments' => $customer->returned_shipments,
            'cancelled_shipments' => $customer->cancelled_shipments,
            'pending_shipments' => $customer->pending_shipments,
            'failed_shipments' => $customer->failed_shipments,
            'delivery_score' => $customer->delivery_score ?? 0,
            'has_enough_data' => $customer->hasEnoughData(),
            'success_rate_range' => $customer->getSuccessRangeString(),
            'success_percentage' => $customer->getSuccessPercentage(),
            'score_status' => $customer->getDeliveryScoreStatus(),
            'is_risky' => $customer->isRisky(),
        ];

        // Get score history (for chart) - last 30 shipments with status changes
        $scoreHistory = $customer->shipments()
            ->whereIn('status', ['delivered', 'returned', 'cancelled'])
            ->orderBy('updated_at', 'desc')
            ->limit(30)
            ->get(['id', 'status', 'updated_at', 'created_at'])
            ->map(function ($shipment) use ($customer) {
                // Calculate what the score would have been at this point
                // This is a simplified version - in production you might want to track score changes
                return [
                    'date' => $shipment->updated_at->format('Y-m-d'),
                    'status' => $shipment->status,
                ];
            })
            ->reverse()
            ->values();

        // Policy gating: Only load and calculate global stats if tenant has permission
        $globalStats = ['enabled' => false];
        if ($canViewGlobal && $customer->global_customer_id) {
            $global = $customer->globalCustomer()
                ->withCount([
                    'shipments as global_completed' => fn($q) =>
                        $q->whereIn('status', ['delivered', 'returned', 'cancelled']),
                    'shipments as global_delivered' => fn($q) =>
                        $q->where('status', 'delivered'),
                ])
                ->first();

            if ($global) {
                $globalStats = [
                    'enabled' => true,
                    'completed' => $global->global_completed,
                    'delivered' => $global->global_delivered,
                    'success_percentage' => $global->getGlobalSuccessPercentage(),
                    'score' => $global->getGlobalDeliveryScore(),
                    'score_status' => $global->getGlobalDeliveryScoreStatus(),
                    'is_risky' => $global->isRisky(),
                ];
            }
        }

        return Inertia::render('Customers/Show', [
            'customer' => new CustomerResource($customer),
            'stats' => $stats,
            'globalStats' => $globalStats,
            'recentShipments' => $customer->shipments()
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'tracking_number' => $s->tracking_number,
                    'status' => $s->status,
                    'created_at' => $s->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $s->updated_at->format('Y-m-d H:i:s'),
                    'courier' => $s->courier ? [
                        'name' => $s->courier->name,
                        'code' => $s->courier->code,
                    ] : null,
                ]),
            'scoreHistory' => $scoreHistory,
        ]);
    }
}
