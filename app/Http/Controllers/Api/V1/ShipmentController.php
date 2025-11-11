<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Requests\WebhookRequest;
use App\Models\Shipment;
use App\Services\Contracts\WebSocketServiceInterface;
use App\Services\Contracts\CacheServiceInterface;
use App\Exceptions\ShipmentNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    private WebSocketServiceInterface $webSocketService;
    private CacheServiceInterface $cacheService;

    public function __construct(WebSocketServiceInterface $webSocketService, CacheServiceInterface $cacheService)
    {
        $this->webSocketService = $webSocketService;
        $this->cacheService = $cacheService;
    }

    /**
     * Get correlation ID from request header or generate new one
     */
    private function getCorrelationId(Request $request): string
    {
        return $request->header('X-Correlation-ID') ?? (string) Str::ulid();
    }

    /**
     * Display a listing of shipments
     */
    public function index(Request $request): JsonResponse
    {
        $correlationId = $this->getCorrelationId($request);
        
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

        // Build cache key from filters
        $filters = $request->only(['status', 'courier_id', 'customer_id', 'date_from', 'date_to', 'per_page']);
        $filterHash = md5(json_encode($filters));
        $cacheKey = "tenant:{$request->user()->tenant_id}:shipments:list:{$filterHash}";

        // Try cache first (5 minutes)
        $cached = Cache::get($cacheKey);
        if ($cached) {
            Log::debug("DeliveryScore:cache_hit", [
                'correlation_id' => $correlationId,
                'cache_key' => $cacheKey,
            ]);
            return response()->json($cached);
        }

        // Cursor pagination for better performance on large datasets
        $perPage = min($request->get('per_page', 15), 100);
        $useCursor = $request->boolean('use_cursor', false);
        
        if ($useCursor && $request->has('cursor')) {
            $shipments = $query->orderBy('created_at', 'desc')
                ->cursorPaginate($perPage, ['*'], 'cursor', $request->cursor);
        } else {
            $shipments = $query->orderBy('created_at', 'desc')->paginate($perPage);
        }

        $response = [
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
                'filters_applied' => $filters,
                'generated_at' => now()->toISOString(),
                'correlation_id' => $correlationId,
            ]
        ];

        // Cache for 5 minutes
        Cache::put($cacheKey, $response, 300);

        return response()->json($response);
    }

    /**
     * Store a newly created shipment
     */
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $correlationId = $this->getCorrelationId($request);
        
        $order = \App\Models\Order::find($request->order_id);
        $customer = $order?->customer;
        
        $shipment = Shipment::create([
            'tenant_id' => Auth::user()->tenant_id,
            'order_id' => $request->order_id,
            'customer_id' => $order?->customer_id,
            'global_customer_id' => $customer?->global_customer_id ?? null,
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

        Log::info("DeliveryScore:shipment_created", [
            'correlation_id' => $correlationId,
            'tenant_id' => Auth::user()->tenant_id,
            'shipment_id' => $shipment->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => $shipment->load(['customer', 'courier', 'order']),
            'meta' => [
                'correlation_id' => $correlationId,
            ],
        ], 201);
    }

    /**
     * Display the specified shipment
     * Tenant scoping handled by ShipmentPolicy via route model binding
     */
    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

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
     * Tenant scoping handled by ShipmentPolicy via route model binding
     */
    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

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
     * Tenant scoping handled by ShipmentPolicy via route model binding
     */
    public function destroy(Shipment $shipment): JsonResponse
    {
        $this->authorize('delete', $shipment);

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
     * Tenant scoping handled by ShipmentPolicy via route model binding
     */
    public function getTrackingDetails(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $cacheKey = "tenant:{$shipment->tenant_id}:shipment:{$shipment->id}:tracking";
        
        // Try to get cached tracking data
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'cached' => true,
            ]);
        }

        // Fetch fresh tracking data (statusHistory already ordered desc by default)
        $trackingData = [
            'shipment' => $shipment->load(['customer', 'courier', 'statusHistory']),
            'current_status' => $shipment->currentStatus,
            'status_history' => $shipment->statusHistory,
            'predictive_eta' => $shipment->predictiveEta,
            'alerts' => $shipment->alerts()->where('status', 'active')->get(),
        ];

        // Cache the data (5 minutes)
        Cache::put($cacheKey, $trackingData, 300);

        return response()->json([
            'success' => true,
            'data' => $trackingData,
            'cached' => false,
        ]);
    }

    /**
     * Update shipment status with double-write safety
     * Tenant scoping handled by ShipmentPolicy via route model binding
     */
    public function updateStatus(UpdateStatusRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        $correlationId = $this->getCorrelationId($request);
        $newStatus = $request->status;
        $oldStatus = $shipment->status;

        // Double-write safety: early return if same status and recent duplicate
        if ($oldStatus === $newStatus) {
            $lastHistory = $shipment->statusHistory()->first(); // Already ordered desc
            if ($lastHistory && 
                $lastHistory->status === $newStatus && 
                $lastHistory->happened_at >= now()->subSeconds(5)) {
                
                Log::debug("DeliveryScore:duplicate_status_skipped", [
                    'correlation_id' => $correlationId,
                    'tenant_id' => $shipment->tenant_id,
                    'shipment_id' => $shipment->id,
                    'status' => $newStatus,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No-op (duplicate status within 5s)',
                    'data' => $shipment->fresh(['statusHistory']),
                    'meta' => [
                        'correlation_id' => $correlationId,
                    ],
                ]);
            }
        }

        // Update status (model hook will handle scoring)
        $shipment->update(['status' => $newStatus]);

        // Create status history entry
        $happenedAt = $request->happened_at ? \Carbon\CarbonImmutable::parse($request->happened_at) : now();
        $shipment->statusHistory()->create([
            'status' => $newStatus,
            'description' => $request->description,
            'location' => $request->location,
            'happened_at' => $happenedAt,
        ]);

        // Broadcast update
        $this->webSocketService->broadcastShipmentUpdate($shipment, [
            'old_status' => $oldStatus,
            'new_status' => $shipment->status,
            'description' => $request->description,
            'location' => $request->location,
        ]);

        // Clear cache
        Cache::forget("shipment:{$shipment->id}:current_status");
        $this->cacheService->invalidateShipmentCaches($shipment->id, $shipment->tenant_id);

        Log::info("DeliveryScore:status_updated", [
            'correlation_id' => $correlationId,
            'tenant_id' => $shipment->tenant_id,
            'shipment_id' => $shipment->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment status updated successfully',
            'data' => $shipment->fresh(['customer', 'courier', 'statusHistory']),
            'meta' => [
                'correlation_id' => $correlationId,
            ],
        ]);
    }

    /**
     * Get status history for a shipment
     * Tenant scoping handled by ShipmentPolicy via route model binding
     */
    public function getStatusHistory(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        // statusHistory already ordered desc by default
        $history = $shipment->statusHistory;

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
     * Rate limited to prevent enumeration attacks
     */
    public function getPublicStatus(Request $request, string $trackingNumber): JsonResponse
    {
        $correlationId = $this->getCorrelationId($request);
        
        // Optional tenant slug hash for additional security
        $tenantSlug = $request->query('tenant');
        
        $query = Shipment::byTrackingNumber($trackingNumber)
            ->where('status', '!=', 'cancelled');

        // If tenant slug provided, scope to that tenant
        if ($tenantSlug) {
            $tenant = \App\Models\Tenant::where('subdomain', $tenantSlug)->first();
            if ($tenant) {
                $query->where('tenant_id', $tenant->id);
            }
        }

        $shipment = $query->first();

        if (!$shipment) {
            Log::warning("DeliveryScore:public_status_not_found", [
                'correlation_id' => $correlationId,
                'tracking_number' => substr($trackingNumber, 0, 8) . '...', // Log partial only
            ]);
            
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
                'correlation_id' => $correlationId,
            ]
        ]);
    }

    /**
     * Handle webhook for shipment updates
     * Includes HMAC signature validation and idempotency handling
     */
    public function handleWebhook(WebhookRequest $request): JsonResponse
    {
        $correlationId = $this->getCorrelationId($request);
        
        // HMAC signature validation (if configured)
        $signatureHeader = $request->header('X-Payload-Signature');
        if ($signatureHeader) {
            // Get tenant from shipment or request
            $shipment = Shipment::byTrackingNumber($request->tracking_number)->first();
            if ($shipment) {
                $tenant = $shipment->tenant;
                if ($tenant && $tenant->require_signed_webhooks) {
                    $verifier = app(\App\Support\HmacVerifier::class);
                    $isGlobalKey = false; // Webhook-specific logic
                    if (!$verifier->verify($request, $tenant, $isGlobalKey)) {
                        Log::warning("DeliveryScore:webhook_signature_invalid", [
                            'correlation_id' => $correlationId,
                            'tenant_id' => $tenant->id,
                            'tracking_number' => substr($request->tracking_number, 0, 8) . '...',
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid signature',
                        ], 401);
                    }
                }
            }
        }

        // Idempotency check: deduplicate by event_id
        $eventId = $request->event_id;
        if ($eventId) {
            $dedupKey = "once:events:{$eventId}";
            if (Cache::has($dedupKey)) {
                Log::debug("DeliveryScore:webhook_duplicate_skipped", [
                    'correlation_id' => $correlationId,
                    'event_id' => $eventId,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook already processed (idempotent)',
                ]);
            }
            // Mark as processed (10 minute TTL)
            Cache::put($dedupKey, true, 600);
        }

        $shipment = Shipment::byTrackingNumber($request->tracking_number)->first();

        if (!$shipment) {
            Log::warning("DeliveryScore:webhook_shipment_not_found", [
                'correlation_id' => $correlationId,
                'tracking_number' => substr($request->tracking_number, 0, 8) . '...',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found',
            ], 404);
        }

        $oldStatus = $shipment->status;
        $newStatus = $request->status;

        // Double-write safety: early return if same status and recent duplicate
        if ($oldStatus === $newStatus) {
            $lastHistory = $shipment->statusHistory()->first();
            if ($lastHistory && 
                $lastHistory->status === $newStatus && 
                $lastHistory->happened_at >= now()->subSeconds(5)) {
                
                Log::debug("DeliveryScore:webhook_duplicate_status_skipped", [
                    'correlation_id' => $correlationId,
                    'tenant_id' => $shipment->tenant_id,
                    'shipment_id' => $shipment->id,
                    'status' => $newStatus,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No-op (duplicate status within 5s)',
                ]);
            }
        }

        // Update shipment status (model hook will handle scoring)
        $shipment->update(['status' => $newStatus]);

        // Create status history entry
        $happenedAt = $request->happened_at 
            ? \Carbon\CarbonImmutable::parse($request->happened_at) 
            : now();
        
        $shipment->statusHistory()->create([
            'status' => $newStatus,
            'description' => $request->description,
            'location' => $request->location,
            'happened_at' => $happenedAt,
        ]);

        // Broadcast update
        $this->webSocketService->broadcastShipmentUpdate($shipment, [
            'old_status' => $oldStatus,
            'new_status' => $shipment->status,
            'source' => 'webhook',
        ]);

        // Clear cache
        Cache::forget("shipment:{$shipment->id}:current_status");
        $this->cacheService->invalidateShipmentCaches($shipment->id, $shipment->tenant_id);

        Log::info("DeliveryScore:webhook_processed", [
            'correlation_id' => $correlationId,
            'tenant_id' => $shipment->tenant_id,
            'shipment_id' => $shipment->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'event_id' => $eventId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully',
            'meta' => [
                'correlation_id' => $correlationId,
            ],
        ]);
    }
}
