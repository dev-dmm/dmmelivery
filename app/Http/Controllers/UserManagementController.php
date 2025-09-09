<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Display all users for super admin management
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search');
        $role_filter = $request->get('role');
        $tenant_filter = $request->get('tenant');
        
        $query = User::query()
            ->with(['tenant:id,name,subdomain'])
            ->select([
                'users.*'
            ]);
        
        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function ($tenantQuery) use ($search) {
                      $tenantQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('subdomain', 'like', "%{$search}%");
                  });
            });
        }
        
        // Filter by role
        if ($role_filter) {
            $query->where('role', $role_filter);
        }
        
        // Filter by tenant
        if ($tenant_filter) {
            $query->where('tenant_id', $tenant_filter);
        }
        
        $users = $query->orderBy('created_at', 'desc')
                       ->paginate($perPage)
                       ->withQueryString();
        
        // Get tenants list for filter dropdown
        $tenants = Tenant::select('id', 'name', 'subdomain')
                        ->orderBy('name')
                        ->get();
        
        // Get available roles
        $availableRoles = User::getAvailableRoles();
        
        // Get role statistics
        $roleStats = User::selectRaw('role, COUNT(*) as count')
                        ->groupBy('role')
                        ->get()
                        ->keyBy('role');
        
        return Inertia::render('SuperAdmin/Users', [
            'users' => $users,
            'tenants' => $tenants,
            'availableRoles' => $availableRoles,
            'roleStats' => $roleStats,
            'filters' => [
                'search' => $search,
                'role' => $role_filter,
                'tenant' => $tenant_filter,
                'per_page' => $perPage
            ]
        ]);
    }
    
    /**
     * Update user role
     */
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => ['required', Rule::in(array_keys(User::getAvailableRoles()))]
        ]);
        
        $oldRole = $user->role;
        $user->update(['role' => $request->role]);
        
        return response()->json([
            'success' => true,
            'message' => "User role updated from {$oldRole} to {$request->role}",
            'user' => $user->load('tenant:id,name,subdomain')
        ]);
    }
    
    /**
     * Toggle user active status
     */
    public function toggleActive(Request $request, User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        
        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'User activated' : 'User deactivated',
            'user' => $user->load('tenant:id,name,subdomain')
        ]);
    }
    
    /**
     * Show user details
     */
    public function show(User $user)
    {
        $user->load([
            'tenant:id,name,subdomain,contact_email,created_at'
        ]);
        
        return Inertia::render('SuperAdmin/UserDetails', [
            'user' => $user,
            'availableRoles' => User::getAvailableRoles()
        ]);
    }
}