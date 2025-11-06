<?php
// app/Models/Shipment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Scopes\TenantScope;

class Shipment extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'customer_id',
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
        'courier_response',
    ];

    protected $hidden = [
        'courier_response', // Internal API responses
    ];

    protected $casts = [
        'dimensions' => 'array',
        'courier_response' => 'array',
        'weight' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'estimated_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
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
                app(\App\Services\Contracts\WebSocketServiceInterface::class)->broadcastShipmentUpdate($shipment, [
                    'old_status' => $shipment->getOriginal('status'),
                    'new_status' => $shipment->status,
                ]);
                
                // Special handling for delivered status
                if ($shipment->status === 'delivered' && $shipment->wasChanged('status')) {
                    app(\App\Services\Contracts\WebSocketServiceInterface::class)->broadcastShipmentDelivered($shipment);
                }
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
        return $this->hasMany(ShipmentStatusHistory::class);
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

    public function getCurrentStatusAttribute(): ?ShipmentStatusHistory
    {
        return $this->statusHistory()->latest('happened_at')->first();
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
        return $query->where('tracking_number', $trackingNumber)
                    ->orWhere('courier_tracking_id', $trackingNumber);
    }
}