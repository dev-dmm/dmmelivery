<?php
// app/Models/Shipment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Scopes\TenantScope;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Events\DeliveryScoreUpdated;

class Shipment extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'customer_id',
        'global_customer_id',
        'courier_id',
        'tracking_number',
        'courier_tracking_id',
        'status',
        'weight',
        'dimensions',
        'shipping_address',
        'shipping_city',
        'billing_address',
        'shipping_cost',
        'estimated_delivery',
        'actual_delivery',
        'scored_at',
        'scored_delta',
        'courier_response',
    ];

    protected $hidden = [
        'courier_response', // Internal API responses
        'global_customer_id', // Don't expose global customer ID in public APIs
    ];

    protected $casts = [
        'dimensions' => 'array',
        'courier_response' => 'array',
        'weight' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'estimated_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
        'scored_at' => 'datetime',
        'scored_delta' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
        
        // WebSocket events
        static::created(function ($shipment) {
            app(\App\Services\Contracts\WebSocketServiceInterface::class)->broadcastNewShipment($shipment);
        });
        
        static::updated(function ($shipment) {
            if ($shipment->wasChanged('status')) {
                $oldStatus = $shipment->getOriginal('status');
                $newStatus = $shipment->status;
                
                app(\App\Services\Contracts\WebSocketServiceInterface::class)->broadcastShipmentUpdate($shipment, [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
                
                // Special handling for delivered status
                if ($newStatus === 'delivered') {
                    app(\App\Services\Contracts\WebSocketServiceInterface::class)->broadcastShipmentDelivered($shipment);
                }
                
                // Update customer delivery score when status changes to final status
                $shipment->updateCustomerDeliveryScore($oldStatus, $newStatus);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function globalCustomer(): BelongsTo
    {
        return $this->belongsTo(GlobalCustomer::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class)->orderByDesc('happened_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function predictiveEta(): HasOne
    {
        return $this->hasOne(PredictiveEta::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get current status from history with caching
     * Caches for 5 seconds to avoid repeated DB calls
     */
    public function getCurrentStatusAttribute(): ?ShipmentStatusHistory
    {
        $cacheKey = "shipment:{$this->id}:current_status";
        
        return cache()->remember($cacheKey, 5, function () {
            return $this->statusHistory()->limit(1)->first();
        });
    }

    // Optimized query scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['delivered', 'cancelled', 'returned']);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeInTransit($query)
    {
        return $query->whereIn('status', ['in_transit', 'out_for_delivery']);
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['customer', 'courier', 'statusHistory', 'predictiveEta']);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByTrackingNumber($query, $trackingNumber)
    {
        // Normalize: strip spaces and hyphens, case-insensitive
        $normalized = strtolower(preg_replace('/[\s\-]/', '', $trackingNumber));
        
        return $query->where(function ($q) use ($trackingNumber, $normalized) {
            // Exact match first (most common)
            $q->where('tracking_number', $trackingNumber)
              ->orWhere('courier_tracking_id', $trackingNumber);
            
            // Case-insensitive match (if DB supports it)
            if (config('database.default') === 'pgsql') {
                $q->orWhereRaw('LOWER(REPLACE(REPLACE(tracking_number, \' \', \'\'), \'-\', \'\')) = ?', [$normalized])
                  ->orWhereRaw('LOWER(REPLACE(REPLACE(courier_tracking_id, \' \', \'\'), \'-\', \'\')) = ?', [$normalized]);
            } else {
                // MySQL case-insensitive by default, but normalize for consistency
                $q->orWhereRaw('LOWER(REPLACE(REPLACE(tracking_number, \' \', \'\'), \'-\', \'\')) = ?', [$normalized])
                  ->orWhereRaw('LOWER(REPLACE(REPLACE(courier_tracking_id, \' \', \'\'), \'-\', \'\')) = ?', [$normalized]);
            }
        });
    }

    /**
     * All valid shipment statuses
     * Single source of truth for status validation across the application
     * 
     * @var array<string>
     */
    public const STATUSES = [
        'pending',
        'picked_up',
        'in_transit',
        'out_for_delivery',
        'delivered',
        'failed',
        'returned',
        'cancelled',
    ];

    /**
     * Final statuses that trigger delivery score updates
     * Maps status to score delta: positive for delivered, negative for returned/cancelled
     * 
     * This constant ensures app-level consistency with DB-level CHECK constraints
     * and makes it easy to add new final statuses in the future (e.g., 'return_to_sender')
     * 
     * @var array<string, int>
     */
    public const FINAL_STATUSES = [
        'delivered' => 1,
        'returned' => -1,
        'cancelled' => -1,
    ];

    /**
     * Update customer delivery score based on shipment status change
     * Only updates score when transitioning to a final status (delivered, returned, cancelled)
     * Uses scored_at and scored_delta to prevent double scoring
     * +1 point for delivered, -1 point for returned/cancelled
     */
    public function updateCustomerDeliveryScore(string $oldStatus, string $newStatus): void
    {
        $finalStatuses = array_keys(self::FINAL_STATUSES);
        
        // Early return for final→final status changes (should not score)
        // Skip logging to reduce noise - these are expected transitions
        if (in_array($oldStatus, $finalStatuses) && in_array($newStatus, $finalStatuses)) {
            return;
        }
        
        // Only score if transitioning from non-final to final status AND not already scored
        if (!in_array($oldStatus, $finalStatuses) && in_array($newStatus, $finalStatuses) && !$this->scored_at) {
            $customer = $this->customer;
            
            if (!$customer) {
                Log::warning("⚠️ No customer found for shipment", [
                    'tenant_id' => $this->tenant_id,
                    'shipment_id' => $this->id,
                    'tracking_number' => $this->tracking_number,
                ]);
                return;
            }

            // Calculate score delta using constant mapping (ensures consistency with DB constraints)
            $delta = self::FINAL_STATUSES[$newStatus] ?? 0;
            
            // Safety check: if status is not in mapping, skip scoring
            if ($delta === 0) {
                Log::warning("Unknown final status for scoring", [
                    'tenant_id' => $this->tenant_id,
                    'shipment_id' => $this->id,
                    'status' => $newStatus,
                ]);
                return;
            }
            
            // Atomic transaction: increment score + journal insert + mark shipment as scored
            // Row-level lock prevents concurrent workers from double-scoring
            // Lock order: shipment first, then customer (prevents deadlocks)
            // Retry 3 times for sporadic deadlocks (especially in MySQL)
            $retryCount = 0;
            DB::transaction(function () use ($customer, $delta, $newStatus, &$retryCount) {
                $retryCount++;
                // Lock this shipment row (FOR UPDATE) to prevent concurrent scoring
                $locked = self::query()->whereKey($this->getKey())->lockForUpdate()->first();
                
                // Null-safe check: shipment might have been deleted during lock
                if (!$locked) {
                    Log::warning("Shipment disappeared during scoring lock", [
                        'tenant_id' => $this->tenant_id,
                        'shipment_id' => $this->id,
                    ]);
                    return;
                }
                
                // Re-check: if already scored by another worker, stop
                if ($locked->scored_at) {
                    Log::debug("Shipment already scored, skipping duplicate", [
                        'tenant_id' => $this->tenant_id,
                        'shipment_id' => $this->id,
                        'scored_at' => $locked->scored_at,
                    ]);
                    return;
                }
                
                // Lock customer row in consistent order (shipment → customer) to prevent deadlocks
                $customerLocked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();
                
                if (!$customerLocked) {
                    Log::warning("Customer disappeared during scoring lock", [
                        'tenant_id' => $this->tenant_id,
                        'shipment_id' => $this->id,
                        'customer_id' => $customer->id,
                    ]);
                    return;
                }
                
                // 1) Atomic increment on locked customer
                // Note: increment() doesn't fire model events, but we keep withoutEvents for symmetry
                // and to ensure no observers fire if Laravel behavior changes in future versions
                Model::withoutEvents(function () use ($customerLocked, $delta) {
                    $customerLocked->increment('delivery_score', $delta);
                });
                
                // 2) Mark shipment as scored (without triggering model events)
                Model::withoutEvents(function () use ($locked, $delta) {
                    $locked->timestamps = false;
                    $locked->forceFill([
                        'scored_at' => now(),
                        'scored_delta' => $delta,
                    ])->save();
                    $locked->timestamps = true;
                });
                
                // 3) One-row-per-shipment journal (unique per shipment_id)
                // Use upsert to prevent PK id from changing on re-runs
                // Only update customer_id (and tenant_id if exists) - keep delta/reason/id/created_at immutable
                $now = now();
                $payload = [
                    'shipment_id' => $locked->id,
                    'id' => (string) Str::uuid(), // Only used for insert, never updated
                    'customer_id' => $customerLocked->id,
                    'delta' => $delta,
                    'reason' => $newStatus,
                    'created_at' => $now,
                ];
                
                // Add tenant_id if column exists (materialized for reporting)
                $updateColumns = ['customer_id'];
                if (Schema::hasColumn('delivery_score_journal', 'tenant_id')) {
                    $payload['tenant_id'] = $locked->tenant_id;
                    $updateColumns[] = 'tenant_id';
                }
                
                // Upsert: uniqueBy shipment_id, update ONLY customer_id (and tenant_id if exists)
                // Never update: id, delta, reason, created_at (immutable audit trail)
                DB::table('delivery_score_journal')->upsert(
                    [$payload],
                    ['shipment_id'], // unique constraint
                    $updateColumns   // only these fields are updated if row exists
                );
            }, 3); // 3 retry attempts for deadlock handling
            
            // Structured log context for easy querying
            $logContext = [
                'tenant_id' => $this->tenant_id,
                'shipment_id' => $this->id,
                'customer_id' => $customer->id,
            ];
            
            // Log retry count for telemetry (only if > 1, to avoid noise)
            if ($retryCount > 1) {
                Log::debug("DeliveryScore:retry", $logContext + [
                    'retry_count' => $retryCount,
                ]);
            }
            
            $newScore = $customer->fresh()->delivery_score;
            
            Log::info("DeliveryScore:increment", $logContext + [
                'delta' => $delta,
                'reason' => $newStatus,
                'new_score' => $newScore,
            ]);
            
            // Dispatch event for metrics/observability
            // Correlation ID can be passed from controller if available
            $correlationId = request()->header('X-Correlation-ID') ?? '';
            event(new DeliveryScoreUpdated(
                $this,
                $customer->fresh(),
                $delta,
                $newStatus,
                $newScore,
                $correlationId
            ));
        }
    }
}