<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    /**
     * Display all orders across all tenants
     */
    public function orders(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search');
        $tenant_filter = $request->get('tenant');
        $status_filter = $request->get('status');
        
        $query = Order::query()
            ->with([
                'tenant:id,name,subdomain',
                'customer:id,name,email',
                'shipments:id,order_id,tracking_number,status,courier_id',
                'shipments.courier:id,name,code'
            ])
            ->select([
                'orders.*'
            ]);
        
        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('external_order_id', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tenant', function ($tenantQuery) use ($search) {
                      $tenantQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('subdomain', 'like', "%{$search}%");
                  });
            });
        }
        
        // Filter by tenant
        if ($tenant_filter) {
            $query->where('tenant_id', $tenant_filter);
        }
        
        // Filter by status
        if ($status_filter) {
            $query->where('status', $status_filter);
        }
        
        $orders = $query->orderBy('created_at', 'desc')
                       ->paginate($perPage)
                       ->withQueryString();
        
        // Get tenant list for filter dropdown
        $tenants = Tenant::select('id', 'name', 'subdomain')
                        ->orderBy('name')
                        ->get();
        
        // Get order statuses for filter
        $orderStatuses = Order::select('status')
                            ->distinct()
                            ->whereNotNull('status')
                            ->orderBy('status')
                            ->pluck('status');
        
        // Get summary statistics
        $stats = $this->getOrderStats();
        
        return Inertia::render('SuperAdmin/Orders', [
            'orders' => $orders,
            'tenants' => $tenants,
            'orderStatuses' => $orderStatuses,
            'filters' => [
                'search' => $search,
                'tenant' => $tenant_filter,
                'status' => $status_filter,
                'per_page' => $perPage
            ],
            'stats' => $stats
        ]);
    }
    
    /**
     * Get order statistics for dashboard
     */
    private function getOrderStats()
    {
        return [
            'total_orders' => Order::count(),
            'total_tenants' => Tenant::count(),
            'total_shipments' => Shipment::count(),
            'orders_today' => Order::whereDate('created_at', today())->count(),
            'orders_this_week' => Order::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'orders_this_month' => Order::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'pending_shipments' => Shipment::where('status', 'pending')->count(),
            'active_tenants' => Tenant::whereHas('orders', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })->count()
        ];
    }
    
    /**
     * Display super admin dashboard
     */
    public function dashboard()
    {
        $stats = $this->getOrderStats();
        
        // Recent orders across all tenants
        $recentOrders = Order::with([
                'tenant:id,name,subdomain',
                'customer:id,name,email',
                'shipments:id,order_id,tracking_number,status'
            ])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Top tenants by order volume
        $topTenants = Tenant::withCount(['orders' => function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            }])
            ->having('orders_count', '>', 0)
            ->orderBy('orders_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'subdomain']);
        
        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'topTenants' => $topTenants
        ]);
    }
    
    /**
     * Display tenant details
     */
    public function tenantDetails(Tenant $tenant)
    {
        $tenant->load([
            'users:id,tenant_id,first_name,last_name,email,email_verified_at,created_at',
            'orders' => function ($q) {
                $q->with(['customer:id,name,email'])
                  ->orderBy('created_at', 'desc')
                  ->limit(20);
            },
            'couriers:id,tenant_id,name,code,is_active,is_default'
        ]);
        
        $tenantStats = [
            'total_orders' => $tenant->orders()->count(),
            'total_shipments' => Shipment::where('tenant_id', $tenant->id)->count(),
            'orders_this_month' => $tenant->orders()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'pending_shipments' => Shipment::where('tenant_id', $tenant->id)->where('status', 'pending')->count()
        ];
        
        return Inertia::render('SuperAdmin/TenantDetails', [
            'tenant' => $tenant,
            'stats' => $tenantStats
        ]);
    }
    
    /**
     * List all tenants
     */
    public function tenants(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search');
        
        $query = Tenant::query()
            ->withCount(['orders', 'users'])
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
                'search' => $search,
                'per_page' => $perPage
            ]
        ]);
    }
}
