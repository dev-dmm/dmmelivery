<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\WebSocketService;
use App\Services\CacheService;
use App\Exceptions\ShipmentNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShipmentController extends Controller
{
    private WebSocketService $webSocketService;
    private CacheService $cacheService;

    public function __construct(WebSocketService $webSocketService, CacheService $cacheService)
    {
        $this->webSocketService = $webSocketService;
        $this->cacheService = $cacheService;
    }

    /**
     * Display a listing of shipments
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::withRelations()
            ->forTenant(Auth::user()->tenant_id);

        // Apply filters
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('courier_id')) {
            $query->where('courier_id', $request->courier_id);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $shipments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $shipments->items(),
            'pagination' => [
                'current_page' => $shipments->currentPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
                'last_page' => $shipments->lastPage(),
                'has_more' => $shipments->hasMorePages(),
            ],
            'meta' => [
                'filters_applied' => $request->only(['status', 'courier_id', 'customer_id', 'date_from', 'date_to']),
                'generated_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Store a newly created shipment
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'courier_id' => 'nullable|exists:couriers,id',
            'tracking_number' => 'nullable|string|max:255|unique:shipments,tracking_number',
            'weight' => 'nullable|numeric|min:0',
            'shipping_address' => 'required|string',
            'billing_address' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'estimated_delivery' => 'nullable|date|after:now',
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
            'order_id' => $request->order_id,
            'courier_id' => $request->courier_id,
            'tracking_number' => $request->tracking_number,
            'weight' => $request->weight,
            'shipping_address' => $request->shipping_address,
            'billing_address' => $request->billing_address,
            'shipping_cost' => $request->shipping_cost,
            'estimated_delivery' => $request->estimated_delivery,
            'status' => 'pending',
        ]);

        // Clear cache
        $this->cacheService->invalidateTenantCaches(Auth::user()->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => $shipment->load(['customer', 'courier', 'order']),
        ], 201);
    }

    /**
     * Display the specified shipment
     */
    public function show(Shipment $shipment): JsonResponse
    {
        // Check if shipment belongs to user's tenant
        if ($shipment->tenant_id !== Auth::user()->tenant_id) {
            throw new ShipmentNotFoundException();
        }

        $shipment->load(['customer', 'courier', 'order', 'statusHistory', 'predictiveEta', 'alerts']);

        return response()->json([
            'success' => true,
            'data' => $shipment,
            'meta' => [
                'generated_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Update the specified shipment
     */
    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        // Check if shipment belongs to user's tenant
        if ($shipment->tenant_id !== Auth::user()->tenant_id) {
            throw new ShipmentNotFoundException();
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,picked_up,in_transit,out_for_delivery,delivered,failed,returned',
            'tracking_number' => 'nullable|string|max:255|unique:shipments,tracking_number,' . $shipment->id,
            'courier_tracking_id' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'estimated_delivery' => 'nullable|date',
            'actual_delivery' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldStatus = $shipment->status;
        $shipment->update($request->only([
            'status', 'tracking_number', 'courier_tracking_id', 'weight',
            'shipping_address', 'billing_address', 'shipping_cost',
            'estimated_delivery', 'actual_delivery'
        ]));

        // Broadcast update if status changed
        if ($oldStatus !== $shipment->status) {
            $this->webSocketService->broadcastShipmentUpdate($shipment, [
                'old_status' => $oldStatus,
                'new_status' => $shipment->status,
            ]);
        }

        // Clear cache
        $this->cacheService->invalidateShipmentCaches($shipment->id, $shipment->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Shipment updated successfully',
            'data' => $shipment->fresh(['customer', 'courier', 'order']),
        ]);
    }

    /**
     * Remove the specified shipment
     */
    public function destroy(Shipment $shipment): JsonResponse
    {
        // Check if shipment belongs to user's tenant
        if ($shipment->tenant_id !== Auth::user()->tenant_id) {
            throw new ShipmentNotFoundException();
        }

        $shipment->delete();

        // Clear cache
        $this->cacheService->invalidateTenantCaches(Auth::user()->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Shipment deleted successfully',
        ]);
    }

    /**
     * Get tracking details for a shipment
     */
    public function getTrackingDetails(Shipment $shipment): JsonResponse
    {
        // Check if shipment belongs to user's tenant
        if ($shipment->tenant_id !== Auth::user()->tenant_id) {
            throw new ShipmentNotFoundException();
        }

        // Try to get cached tracking data
        $cachedData = $this->cacheService->getCachedShipmentData($shipment->id);
        if ($cachedData) {
            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'cached' => true,
            ]);
        }

        // Fetch fresh tracking data
        $trackingData = [
            'shipment' => $shipment->load(['customer', 'courier', 'statusHistory']),
            'current_status' => $shipment->currentStatus,
            'status_history' => $shipment->statusHistory()->orderBy('happened_at', 'desc')->get(),
            'predictive_eta' => $shipment->predictiveEta,
            'alerts' => $shipment->alerts()->where('status', 'active')->get(),
        ];

        // Cache the data
        $this->cacheService->cacheShipmentData($shipment->id, $trackingData);

        return response()->json([
            'success' => true,
            'data' => $trackingData,
            'cached' => false,
        ]);
    }

    /**
     * Update shipment status
     */
    public function updateStatus(Request $request, Shipment $shipment): JsonResponse
    {
        // Check if shipment belongs to user's tenant
        if ($shipment->tenant_id !== Auth::user()->tenant_id) {
            throw new ShipmentNotFoundException();
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,picked_up,in_transit,out_for_delivery,delivered,failed,returned',
            'description' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:255',
            'happened_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldStatus = $shipment->status;
        $shipment->update(['status' => $request->status]);

        // Create status history entry
        $shipment->statusHistory()->create([
            'status' => $request->status,
            'description' => $request->description,
            'location' => $request->location,
            'happened_at' => $request->happened_at ?? now(),
        ]);

        // Broadcast update
        $this->webSocketService->broadcastShipmentUpdate($shipment, [
            'old_status' => $oldStatus,
            'new_status' => $shipment->status,
            'description' => $request->description,
            'location' => $request->location,
        ]);

        // Clear cache
        $this->cacheService->invalidateShipmentCaches($shipment->id, $shipment->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Shipment status updated successfully',
            'data' => $shipment->fresh(['customer', 'courier', 'statusHistory']),
        ]);
    }

    /**
     * Get status history for a shipment
     */
    public function getStatusHistory(Shipment $shipment): JsonResponse
    {
        // Check if shipment belongs to user's tenant
        if ($shipment->tenant_id !== Auth::user()->tenant_id) {
            throw new ShipmentNotFoundException();
        }

        $history = $shipment->statusHistory()
            ->orderBy('happened_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
            'meta' => [
                'total_events' => $history->count(),
                'generated_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get public shipment status (no authentication required)
     */
    public function getPublicStatus(string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::byTrackingNumber($trackingNumber)
            ->where('status', '!=', 'cancelled')
            ->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found',
                'error_code' => 'SHIPMENT_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'current_location' => $shipment->currentStatus?->location,
                'estimated_delivery' => $shipment->estimated_delivery,
                'actual_delivery' => $shipment->actual_delivery,
                'courier' => $shipment->courier?->name,
                'last_updated' => $shipment->updated_at,
            ],
            'meta' => [
                'generated_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Handle webhook for shipment updates
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // Validate webhook signature if needed
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'required|string',
            'status' => 'required|string',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
            'happened_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook data',
                'errors' => $validator->errors(),
            ], 422);
        }

        $shipment = Shipment::byTrackingNumber($request->tracking_number)->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found',
            ], 404);
        }

        // Update shipment status
        $oldStatus = $shipment->status;
        $shipment->update(['status' => $request->status]);

        // Create status history entry
        $shipment->statusHistory()->create([
            'status' => $request->status,
            'description' => $request->description,
            'location' => $request->location,
            'happened_at' => $request->happened_at ?? now(),
        ]);

        // Broadcast update
        $this->webSocketService->broadcastShipmentUpdate($shipment, [
            'old_status' => $oldStatus,
            'new_status' => $shipment->status,
            'source' => 'webhook',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully',
        ]);
    }
}
