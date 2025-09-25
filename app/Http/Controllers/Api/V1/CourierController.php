<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CourierController extends Controller
{
    /**
     * Display a listing of couriers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Courier::where('tenant_id', Auth::user()->tenant_id);

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $couriers = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $couriers->items(),
            'pagination' => [
                'current_page' => $couriers->currentPage(),
                'per_page' => $couriers->perPage(),
                'total' => $couriers->total(),
                'last_page' => $couriers->lastPage(),
                'has_more' => $couriers->hasMorePages(),
            ]
        ]);
    }

    /**
     * Store a newly created courier
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'api_endpoint' => 'nullable|url',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $courier = Courier::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name' => $request->name,
            'code' => $request->code,
            'api_endpoint' => $request->api_endpoint,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Courier created successfully',
            'data' => $courier,
        ], 201);
    }

    /**
     * Display the specified courier
     */
    public function show(Courier $courier): JsonResponse
    {
        // Check if courier belongs to user's tenant
        if ($courier->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Courier not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $courier,
        ]);
    }

    /**
     * Update the specified courier
     */
    public function update(Request $request, Courier $courier): JsonResponse
    {
        // Check if courier belongs to user's tenant
        if ($courier->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Courier not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50',
            'api_endpoint' => 'nullable|url',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $courier->update($request->only(['name', 'code', 'api_endpoint', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => 'Courier updated successfully',
            'data' => $courier->fresh(),
        ]);
    }

    /**
     * Remove the specified courier
     */
    public function destroy(Courier $courier): JsonResponse
    {
        // Check if courier belongs to user's tenant
        if ($courier->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Courier not found',
            ], 404);
        }

        $courier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Courier deleted successfully',
        ]);
    }

    /**
     * Get shipments for courier
     */
    public function getShipments(Courier $courier): JsonResponse
    {
        // Check if courier belongs to user's tenant
        if ($courier->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Courier not found',
            ], 404);
        }

        $shipments = Shipment::where('courier_id', $courier->id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with(['customer', 'order'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shipments,
        ]);
    }

    /**
     * Test courier connection
     */
    public function testConnection(Courier $courier): JsonResponse
    {
        // Check if courier belongs to user's tenant
        if ($courier->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Courier not found',
            ], 404);
        }

        // Simple connection test
        try {
            // This would normally test the actual API connection
            // For now, we'll just simulate a test
            $isConnected = !empty($courier->api_endpoint);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'courier_id' => $courier->id,
                    'courier_name' => $courier->name,
                    'is_connected' => $isConnected,
                    'tested_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
