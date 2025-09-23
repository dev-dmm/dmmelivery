<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Shipment;
use App\Models\Courier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Scopes\TenantScope;

class SuperAdminController extends Controller
{
    /**
     * Display all orders across all tenants
     */
    public function orders(Request $request)
    {
        $perPage       = $request->get('per_page', 25);
        $search        = $request->get('search');
        $tenant_filter = $request->get('tenant');
        $status_filter = $request->get('status');

        $query = Order::query()
            // IMPORTANT: bypass tenant scope for super-admin views
            ->withoutGlobalScopes([TenantScope::class])
            ->with([
                'tenant:id,name,subdomain',
                'customer:id,name,email',
                // match FE: load plural
                'shipments:id,order_id,tracking_number,status,courier_id',
                'shipments.courier:id,name,code',
            ])
            ->select(['orders.*']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('external_order_id', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tenant', function ($tq) use ($search) {
                      $tq->where('name', 'like', "%{$search}%")
                         ->orWhere('subdomain', 'like', "%{$search}%");
                  });
            });
        }

        if ($tenant_filter) {
            $query->where('tenant_id', $tenant_filter);
        }

        if ($status_filter) {
            $query->where('status', $status_filter);
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage)
                        ->withQueryString();

        // Tenants for filters — no scope so you see *all* tenants
        $tenants = Tenant::query()
            ->withoutGlobalScopes([TenantScope::class])
            ->select('id', 'name', 'subdomain')
            ->orderBy('name')
            ->get();

        // Distinct order statuses across all tenants
        $orderStatuses = Order::query()
            ->withoutGlobalScopes([TenantScope::class])
            ->select('status')
            ->distinct()
            ->whereNotNull('status')
            ->orderBy('status')
            ->pluck('status');

        $stats = $this->getOrderStats();

        return Inertia::render('SuperAdmin/Orders', [
            'orders'        => $orders,
            'tenants'       => $tenants,
            'orderStatuses' => $orderStatuses,
            'filters'       => [
                'search'   => $search,
                'tenant'   => $tenant_filter,
                'status'   => $status_filter,
                'per_page' => $perPage,
            ],
            'stats'         => $stats,
        ]);
    }

    /**
     * Display all order items across all tenants (E-Shop Products)
     */
    public function orderItems(Request $request)
    {
        $perPage       = $request->get('per_page', 24);
        $search        = $request->get('search');
        $tenant_filter = $request->get('tenant');

        $query = OrderItem::query()
            // IMPORTANT: bypass tenant scope for super-admin views
            ->withoutGlobalScopes([TenantScope::class])
            ->with([
                'tenant:id,name,subdomain,business_name',
                'order:id,order_number,external_order_id',
            ])
            ->whereNotNull('product_images') // Only show items with images
            ->where('product_images', '!=', '[]')
            ->where('product_images', '!=', 'null')
            ->select(['order_items.*']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_sku', 'like', "%{$search}%")
                  ->orWhere('product_description', 'like', "%{$search}%")
                  ->orWhere('product_brand', 'like', "%{$search}%")
                  ->orWhere('product_category', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function ($tq) use ($search) {
                      $tq->where('name', 'like', "%{$search}%")
                         ->orWhere('business_name', 'like', "%{$search}%")
                         ->orWhere('subdomain', 'like', "%{$search}%");
                  });
            });
        }

        if ($tenant_filter) {
            $query->where('tenant_id', $tenant_filter);
        }

        $orderItems = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage)
                           ->withQueryString();

        // Tenants for filters — no scope so you see *all* tenants
        $tenants = Tenant::query()
            ->withoutGlobalScopes([TenantScope::class])
            ->select('id', 'name', 'subdomain', 'business_name')
            ->orderBy('name')
            ->get();

        $stats = $this->getOrderItemStats();

        return Inertia::render('SuperAdmin/OrderItems', [
            'orderItems' => $orderItems,
            'tenants'    => $tenants,
            'filters'    => [
                'search'   => $search,
                'tenant'   => $tenant_filter,
                'per_page' => $perPage,
            ],
            'stats'      => $stats,
        ]);
    }

    /**
     * Dashboard stats across all tenants
     */
    private function getOrderStats()
    {
        return [
            'total_orders'       => Order::withoutGlobalScopes([TenantScope::class])->count(),
            'total_tenants'      => Tenant::withoutGlobalScopes([TenantScope::class])->count(),
            'total_shipments'    => Shipment::withoutGlobalScopes([TenantScope::class])->count(),
            'orders_today'       => Order::withoutGlobalScopes([TenantScope::class])->whereDate('created_at', today())->count(),
            'orders_this_week'   => Order::withoutGlobalScopes([TenantScope::class])->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'orders_this_month'  => Order::withoutGlobalScopes([TenantScope::class])->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'pending_shipments'  => Shipment::withoutGlobalScopes([TenantScope::class])->where('status', 'pending')->count(),
            'active_tenants'     => Tenant::withoutGlobalScopes([TenantScope::class])
                ->whereHas('orders', function ($q) {
                    $q->withoutGlobalScopes([TenantScope::class])
                      ->where('created_at', '>=', now()->subDays(30));
                })->count(),
        ];
    }

    /**
     * Order items stats for e-shop products
     */
    private function getOrderItemStats()
    {
        return [
            'total_items'        => OrderItem::withoutGlobalScopes([TenantScope::class])->count(),
            'items_with_images'  => OrderItem::withoutGlobalScopes([TenantScope::class])
                ->whereNotNull('product_images')
                ->where('product_images', '!=', '[]')
                ->where('product_images', '!=', 'null')
                ->count(),
            'active_tenants'     => Tenant::withoutGlobalScopes([TenantScope::class])
                ->whereHas('orders', function ($q) {
                    $q->withoutGlobalScopes([TenantScope::class])
                      ->where('created_at', '>=', now()->subDays(30));
                })->count(),
            'total_value'        => OrderItem::withoutGlobalScopes([TenantScope::class])
                ->whereNotNull('product_images')
                ->where('product_images', '!=', '[]')
                ->where('product_images', '!=', 'null')
                ->sum(DB::raw('final_unit_price * quantity')),
        ];
    }

    /**
     * Super admin dashboard
     */
    public function dashboard()
    {
        $stats = $this->getOrderStats();
    
        $recentOrdersQ = Order::withoutGlobalScopes([TenantScope::class])
            ->with([
                'tenant:id,name,subdomain,business_name',
                'customer:id,name,email',
                'shipments' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class]),
                'shipments.courier:id,name,code',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(10);
    
        $topTenantsQ = Tenant::withoutGlobalScopes([TenantScope::class])
            ->withCount(['orders' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class])
                ->where('created_at', '>=', now()->subDays(30))])
            ->having('orders_count', '>', 0)
            ->orderBy('orders_count', 'desc')
            ->limit(10);
    
        return Inertia::render('SuperAdmin/Dashboard', [
            'stats'        => $stats,                               // eager
            'recentOrders' => Inertia::lazy(fn() => $recentOrdersQ->get()),
            'topTenants'   => Inertia::lazy(fn() => $topTenantsQ->get()),
        ]);
    }
    /**
     * Tenant details
     */
    public function tenantDetails(Tenant $tenant)
    {
        $tenant->load([
            'users:id,tenant_id,first_name,last_name,email,email_verified_at,created_at',
            'orders' => function ($q) {
                $q->withoutGlobalScopes([TenantScope::class])
                  ->with(['customer:id,name,email'])
                  ->orderBy('created_at', 'desc')
                  ->limit(20);
            },
            'couriers:id,tenant_id,name,code,is_active,is_default',
        ]);

        $tenantStats = [
            'total_orders'       => $tenant->orders()->withoutGlobalScopes([TenantScope::class])->count(),
            'total_shipments'    => Shipment::withoutGlobalScopes([TenantScope::class])->where('tenant_id', $tenant->id)->count(),
            'orders_this_month'  => $tenant->orders()->withoutGlobalScopes([TenantScope::class])->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'pending_shipments'  => Shipment::withoutGlobalScopes([TenantScope::class])->where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
        ];

        return Inertia::render('SuperAdmin/TenantDetails', [
            'tenant' => $tenant,
            'stats'  => $tenantStats,
        ]);
    }

    /**
     * List all tenants
     */
    public function tenants(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search  = $request->get('search');

        $query = Tenant::withoutGlobalScopes([TenantScope::class])
            ->withCount([
                'orders' => fn ($q) => $q->withoutGlobalScopes([TenantScope::class]),
                'users',
            ])
            ->with(['users' => function ($q) {
                $q->select('id', 'tenant_id', 'first_name', 'last_name', 'email', 'created_at')
                  ->orderBy('created_at');
            }]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('created_at', 'desc')
                         ->paginate($perPage)
                         ->withQueryString();

        return Inertia::render('SuperAdmin/Tenants', [
            'tenants' => $tenants,
            'filters' => [
                'search'   => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Display couriers for a specific tenant
     */
    public function tenantCouriers(Tenant $tenant, Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search');

        $query = $tenant->couriers()
            ->withoutGlobalScopes([TenantScope::class])
            ->select(['couriers.*']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $couriers = $query->orderBy('created_at', 'desc')
                         ->paginate($perPage)
                         ->withQueryString();

        return Inertia::render('SuperAdmin/TenantCouriers', [
            'tenant' => $tenant,
            'couriers' => $couriers,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Create a new courier for a tenant
     */
    public function createCourier(Tenant $tenant, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'api_endpoint' => 'nullable|url',
            'api_key' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'tracking_url_template' => 'nullable|string',
        ]);

        // If this is being set as default, unset other defaults for this tenant
        if ($request->is_default) {
            $tenant->couriers()
                ->withoutGlobalScopes([TenantScope::class])
                ->update(['is_default' => false]);
        }

        $courier = $tenant->couriers()->create([
            'name' => $request->name,
            'code' => $request->code,
            'api_endpoint' => $request->api_endpoint,
            'api_key' => $request->api_key,
            'is_active' => $request->is_active ?? true,
            'is_default' => $request->is_default ?? false,
            'tracking_url_template' => $request->tracking_url_template,
        ]);

        return redirect()->back()->with('success', 'Courier created successfully.');
    }

    /**
     * Update a courier
     */
    public function updateCourier(Tenant $tenant, Courier $courier, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'api_endpoint' => 'nullable|url',
            'api_key' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'tracking_url_template' => 'nullable|string',
        ]);

        // If this is being set as default, unset other defaults for this tenant
        if ($request->is_default) {
            $tenant->couriers()
                ->withoutGlobalScopes([TenantScope::class])
                ->where('id', '!=', $courier->id)
                ->update(['is_default' => false]);
        }

        $courier->update([
            'name' => $request->name,
            'code' => $request->code,
            'api_endpoint' => $request->api_endpoint,
            'api_key' => $request->api_key,
            'is_active' => $request->is_active,
            'is_default' => $request->is_default,
            'tracking_url_template' => $request->tracking_url_template,
        ]);

        return redirect()->back()->with('success', 'Courier updated successfully.');
    }

    /**
     * Delete a courier
     */
    public function deleteCourier(Tenant $tenant, Courier $courier)
    {
        // Check if courier has any shipments
        $shipmentCount = Shipment::withoutGlobalScopes([TenantScope::class])
            ->where('courier_id', $courier->id)
            ->count();

        if ($shipmentCount > 0) {
            return redirect()->back()->with('error', 'Cannot delete courier with existing shipments.');
        }

        $courier->delete();

        return redirect()->back()->with('success', 'Courier deleted successfully.');
    }

    /**
     * Create ACS courier for tenant (quick setup)
     */
    public function createACSCourier(Tenant $tenant)
    {
        // Check if ACS courier already exists
        $existingACS = $tenant->couriers()
            ->withoutGlobalScopes([TenantScope::class])
            ->where('code', 'acs')
            ->first();

        if ($existingACS) {
            return redirect()->back()->with('error', 'ACS courier already exists for this tenant.');
        }

        // Set other couriers as non-default
        $tenant->couriers()
            ->withoutGlobalScopes([TenantScope::class])
            ->update(['is_default' => false]);

        // Create ACS courier
        $courier = $tenant->couriers()->create([
            'name' => 'ACS Courier',
            'code' => 'acs',
            'api_endpoint' => 'https://api.acscourier.gr',
            'is_active' => true,
            'is_default' => true,
            'tracking_url_template' => 'https://www.acscourier.gr/el/track/{tracking_number}',
        ]);

        return redirect()->back()->with('success', 'ACS courier created successfully.');
    }
}
