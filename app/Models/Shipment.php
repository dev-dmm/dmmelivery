<?php
// app/Models/Shipment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function getCurrentStatusAttribute(): ?ShipmentStatusHistory
    {
        return $this->statusHistory()->latest('happened_at')->first();
    }
}