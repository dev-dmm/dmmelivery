<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\Order;
use App\Services\GlobalCustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::where('tenant_id', Auth::user()->tenant_id);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
                'has_more' => $customers->hasMorePages(),
            ]
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for existing customer by email OR phone
        $existingCustomer = Customer::where('tenant_id', Auth::user()->tenant_id)
            ->where(function ($query) use ($request) {
                $query->where('email', $request->email);
                if ($request->phone) {
                    $query->orWhere('phone', $request->phone);
                }
            })
            ->first();

        if ($existingCustomer) {
            // Ensure global customer is linked
            if (!$existingCustomer->global_customer_id) {
                $globalCustomerService = app(GlobalCustomerService::class);
                $globalCustomer = $globalCustomerService->findOrCreateGlobalCustomer($request->email, $request->phone);
                $existingCustomer->update(['global_customer_id' => $globalCustomer->id]);
            }
            
            // Update existing customer with new information if provided
            $updateData = [];
            if ($request->name && $request->name !== $existingCustomer->name) {
                $updateData['name'] = $request->name;
            }
            if ($request->address && $request->address !== $existingCustomer->address) {
                $updateData['address'] = $request->address;
            }
            if ($request->phone && $request->phone !== $existingCustomer->phone) {
                $updateData['phone'] = $request->phone;
            }

            if (!empty($updateData)) {
                $existingCustomer->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer already exists and was updated',
                'data' => $existingCustomer->fresh(),
            ], 200);
        }

        // Find or create global customer
        $globalCustomerService = app(GlobalCustomerService::class);
        $globalCustomer = $globalCustomerService->findOrCreateGlobalCustomer($request->email, $request->phone);

        $customer = Customer::create([
            'tenant_id' => Auth::user()->tenant_id,
            'global_customer_id' => $globalCustomer->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer,
        ], 201);
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer): JsonResponse
    {
        // Check if customer belongs to user's tenant
        if ($customer->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        // Check if customer belongs to user's tenant
        if ($customer->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer->update($request->only(['name', 'email', 'phone', 'address']));

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer->fresh(),
        ]);
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        // Check if customer belongs to user's tenant
        if ($customer->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Get shipments for customer
     */
    public function getShipments(Customer $customer): JsonResponse
    {
        // Check if customer belongs to user's tenant
        if ($customer->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $shipments = Shipment::where('customer_id', $customer->id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with(['courier', 'order'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shipments,
        ]);
    }

    /**
     * Get orders for customer
     */
    public function getOrders(Customer $customer): JsonResponse
    {
        // Check if customer belongs to user's tenant
        if ($customer->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $orders = Order::where('customer_id', $customer->id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with(['orderItems', 'shipments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }
}
