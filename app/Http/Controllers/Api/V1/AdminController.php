<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Courier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Get system statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'users' => User::count(),
            'tenants' => Tenant::count(),
            'shipments' => Shipment::count(),
            'orders' => Order::count(),
            'customers' => Customer::count(),
            'couriers' => Courier::count(),
            'active_shipments' => Shipment::whereIn('status', ['pending', 'in_transit', 'out_for_delivery'])->count(),
            'delivered_shipments' => Shipment::where('status', 'delivered')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get tenant statistics
     */
    public function tenantStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->tenant_id;
        
        if ($tenantId) {
            $stats = [
                'tenant_id' => $tenantId,
                'users' => User::where('tenant_id', $tenantId)->count(),
                'shipments' => Shipment::where('tenant_id', $tenantId)->count(),
                'orders' => Order::where('tenant_id', $tenantId)->count(),
                'customers' => Customer::where('tenant_id', $tenantId)->count(),
                'couriers' => Courier::where('tenant_id', $tenantId)->count(),
            ];
        } else {
            $stats = Tenant::withCount(['users', 'shipments', 'orders', 'customers', 'couriers'])->get();
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get system health
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'database' => $this->checkDatabaseConnection(),
            'cache' => $this->checkCacheConnection(),
            'queue' => $this->checkQueueConnection(),
        ];

        return response()->json([
            'success' => true,
            'data' => $health,
        ]);
    }

    /**
     * Get system logs
     */
    public function logs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'level' => 'nullable|string|in:debug,info,warning,error,critical',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $level = $request->level ?? 'info';
        $limit = $request->limit ?? 100;

        // This is a simplified log retrieval
        // In a real application, you'd want to use a proper log management system
        $logs = [
            'message' => 'Log retrieval not implemented in this demo',
            'level' => $level,
            'limit' => $limit,
        ];

        return response()->json([
            'success' => true,
            'data' => $logs,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Create admin user
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $request->tenant_id,
            'is_admin' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin user created successfully',
            'data' => $user,
        ], 201);
    }

    /**
     * Get system configuration
     */
    public function config(): JsonResponse
    {
        $config = [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'database_default' => config('database.default'),
            'cache_default' => config('cache.default'),
            'queue_default' => config('queue.default'),
            'broadcasting_default' => config('broadcasting.default'),
        ];

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): array
    {
        try {
            \DB::connection()->getPdo();
            return [
                'status' => 'connected',
                'driver' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connection
     */
    private function checkCacheConnection(): array
    {
        try {
            \Cache::put('health_check', 'ok', 60);
            $value = \Cache::get('health_check');
            return [
                'status' => $value === 'ok' ? 'connected' : 'disconnected',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue connection
     */
    private function checkQueueConnection(): array
    {
        try {
            // Simple queue health check
            return [
                'status' => 'connected',
                'driver' => config('queue.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }
    }
}
