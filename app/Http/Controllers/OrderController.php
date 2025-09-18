<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of orders for the current tenant.
     */
    public function index(Request $request): Response
    {
        // Get tenant from app container (set by IdentifyTenant middleware)
        $tenant = app()->has('tenant') ? app('tenant') : null;
        if (!$tenant) {
            return redirect()->route('login')->with('error', 'Unable to identify your tenant. Please log in again.');
        }

        // Get filters
        $search = $request->get('search');
        $status = $request->get('status');
        $perPage = $request->get('per_page', 15);

        // Build query
        $query = Order::where('tenant_id', $tenant->id)
            ->with(['customer', 'items', 'shipments.courier'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhere('external_order_id', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate($perPage);

        // Get stats
        $stats = [
            'total' => Order::where('tenant_id', $tenant->id)->count(),
            'pending' => Order::where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
            'processing' => Order::where('tenant_id', $tenant->id)->where('status', 'processing')->count(),
            'shipped' => Order::where('tenant_id', $tenant->id)->whereIn('status', ['shipped', 'delivered'])->count(),
            'completed' => Order::where('tenant_id', $tenant->id)->where('status', 'delivered')->count(),
        ];

        // Status options
        $statusOptions = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'ready_to_ship' => 'Ready to Ship',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'failed' => 'Failed',
            'returned' => 'Returned',
        ];

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'statusOptions' => $statusOptions,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order): Response
    {
        // Ensure order belongs to current tenant
        $tenant = app()->has('tenant') ? app('tenant') : null;
        if (!$tenant || $order->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Load relationships
        $order->load([
            'customer',
            'items',
            'shipments.courier',
            'shipments.statusHistory' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }
}
