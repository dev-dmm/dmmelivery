<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::where('tenant_id', Auth::user()->tenant_id);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'has_more' => $orders->hasMorePages(),
            ]
        ]);
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'order_number' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'nullable|string|in:pending,processing,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = Order::create([
            'tenant_id' => Auth::user()->tenant_id,
            'customer_id' => $request->customer_id,
            'order_number' => $request->order_number,
            'total_amount' => $request->total_amount,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order,
        ], 201);
    }

    /**
     * Display the specified order
     */
    public function show(Order $order): JsonResponse
    {
        // Check if order belongs to user's tenant
        if ($order->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order->load(['customer', 'orderItems', 'shipments']),
        ]);
    }

    /**
     * Update the specified order
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        // Check if order belongs to user's tenant
        if ($order->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,processing,completed,cancelled',
            'total_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $order->update($request->only(['status', 'total_amount']));

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Remove the specified order
     */
    public function destroy(Order $order): JsonResponse
    {
        // Check if order belongs to user's tenant
        if ($order->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Create shipment for order
     */
    public function createShipment(Request $request, Order $order): JsonResponse
    {
        // Check if order belongs to user's tenant
        if ($order->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'courier_id' => 'nullable|exists:couriers,id',
            'tracking_number' => 'nullable|string|max:255|unique:shipments,tracking_number',
            'shipping_address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $shipment = Shipment::create([
            'tenant_id' => Auth::user()->tenant_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'courier_id' => $request->courier_id,
            'tracking_number' => $request->tracking_number,
            'shipping_address' => $request->shipping_address,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => $shipment->load(['customer', 'courier']),
        ], 201);
    }

    /**
     * Get shipments for order
     */
    public function getShipments(Order $order): JsonResponse
    {
        // Check if order belongs to user's tenant
        if ($order->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $shipments = $order->shipments()->with(['customer', 'courier'])->get();

        return response()->json([
            'success' => true,
            'data' => $shipments,
        ]);
    }
}
