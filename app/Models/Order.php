<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;

class Order extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'external_order_id',
        'order_number',
        'import_source',
        'import_log_id',
        'status',
        
        // Customer Information
        'customer_name',
        'customer_email', 
        'customer_phone',
        
        // Shipping Address
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'shipping_notes',
        
        // Billing Address
        'billing_address',
        'billing_city',
        'billing_postal_code',
        'billing_country',
        
        // Order Totals
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'discount_amount',
        'total_amount',
        'currency',
        
        // Payment Information
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_date',
        
        // Shipping Preferences
        'preferred_courier',
        'shipping_method',
        'requires_signature',
        'fragile_items',
        'total_weight',
        'package_dimensions',
        
        // Special Instructions
        'special_instructions',
        'delivery_preferences',
        
        // Order Dates
        'order_date',
        'expected_ship_date',
        'shipped_at',
        'delivered_at',
        
        // Linked Shipment
        'shipment_id',
        
        // Metadata
        'additional_data',
        'import_notes',
    ];

    protected $casts = [
        'package_dimensions' => 'array',
        'delivery_preferences' => 'array',
        'additional_data' => 'array',
        'requires_signature' => 'boolean',
        'fragile_items' => 'boolean',
        'order_date' => 'datetime',
        'expected_ship_date' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'payment_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_weight' => 'decimal:3',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function primaryShipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'id', 'shipment_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id');
    }



    // Status Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isReadyToShip(): bool
    {
        return $this->status === 'ready_to_ship';
    }

    public function isShipped(): bool
    {
        return in_array($this->status, ['shipped', 'delivered']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'failed', 'returned']);
    }

    // Payment Methods
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPaymentPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isPaymentFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    // Customer Methods
    public function getCustomerDisplayName(): string
    {
        if ($this->customer) {
            return $this->customer->getFullName();
        }
        
        return $this->customer_name ?: $this->customer_email ?: 'Unknown Customer';
    }

    public function getCustomerEmail(): string
    {
        return $this->customer?->email ?: $this->customer_email ?: '';
    }

    public function getCustomerPhone(): string
    {
        return $this->customer?->phone ?: $this->customer_phone ?: '';
    }

    // Address Methods
    public function getFullShippingAddress(): string
    {
        return sprintf(
            "%s, %s, %s %s",
            $this->shipping_address,
            $this->shipping_city,
            $this->shipping_postal_code,
            $this->shipping_country
        );
    }

    public function getFullBillingAddress(): string
    {
        if ($this->billing_address) {
            return sprintf(
                "%s, %s, %s %s",
                $this->billing_address,
                $this->billing_city,
                $this->billing_postal_code,
                $this->billing_country
            );
        }
        
        return $this->getFullShippingAddress(); // Use shipping as default
    }

    // Items Methods
    public function getTotalItems(): int
    {
        return $this->items()->sum('quantity');
    }

    public function getTotalWeight(): float
    {
        return $this->total_weight ?: $this->items()->sum('weight');
    }

    public function hasFragileItems(): bool
    {
        return $this->fragile_items || $this->items()->where('is_fragile', true)->exists();
    }

    public function hasDigitalItems(): bool
    {
        return $this->items()->where('is_digital', true)->exists();
    }

    public function hasPhysicalItems(): bool
    {
        return $this->items()->where('is_digital', false)->exists();
    }

    // Status Updates
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsReadyToShip(): void
    {
        $this->update(['status' => 'ready_to_ship']);
    }

    public function markAsShipped(Shipment $shipment = null): void
    {
        $updates = [
            'status' => 'shipped',
            'shipped_at' => now(),
        ];
        
        if ($shipment) {
            $updates['shipment_id'] = $shipment->id;
        }
        
        $this->update($updates);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    // Payment Updates
    public function markAsPaid(string $paymentReference = null, string $paymentMethod = null): void
    {
        $updates = [
            'payment_status' => 'paid',
            'payment_date' => now(),
        ];
        
        if ($paymentReference) {
            $updates['payment_reference'] = $paymentReference;
        }
        
        if ($paymentMethod) {
            $updates['payment_method'] = $paymentMethod;
        }
        
        $this->update($updates);
    }

    // Shipment Creation
    public function createShipment(): Shipment
    {
        if ($this->primaryShipment) {
            return $this->primaryShipment;
        }

        $customer = $this->customer;
        $shipment = Shipment::create([
            'tenant_id' => $this->tenant_id,
            'order_id' => $this->id,
            'customer_id' => $this->customer_id,
            'global_customer_id' => $customer?->global_customer_id ?? null,
            'courier_id' => null, // Will be set when courier is assigned
            'tracking_number' => $this->generateTrackingNumber(),
            'courier_tracking_id' => $this->generateTrackingNumber(), // Temp, will be updated by courier
            'status' => 'pending',
            'weight' => $this->getTotalWeight(),
            'shipping_address' => $this->getFullShippingAddress(),
            'shipping_city' => $this->shipping_city,
            'billing_address' => $this->getFullBillingAddress(),
            'shipping_cost' => $this->shipping_cost ?? 0,
            'estimated_delivery' => $this->expected_ship_date,
        ]);

        $this->update(['shipment_id' => $shipment->id]);
        $this->markAsShipped($shipment);

        return $shipment;
    }

    // Helper Methods
    private function generateTrackingNumber(): string
    {
        // Try to get real tracking number from DMM Delivery Bridge first
        $dmmService = app(\App\Services\Contracts\DMMDeliveryServiceInterface::class);
        $realTrackingNumber = $dmmService->getRealTrackingNumber($this->id);
        
        if ($realTrackingNumber) {
            return $realTrackingNumber;
        }
        
        // Fallback to generated tracking number
        $prefix = strtoupper(substr($this->tenant->name ?? 'EST', 0, 3));
        return $prefix . now()->format('Ymd') . strtoupper(substr($this->id, 0, 8));
    }

    public function getFormattedTotal(): string
    {
        return number_format($this->total_amount, 2) . ' ' . $this->currency;
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'processing' => 'blue',
            'ready_to_ship' => 'yellow',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled', 'failed', 'returned' => 'red',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'pending' => '⏳',
            'processing' => '⚙️',
            'ready_to_ship' => '📦',
            'shipped' => '🚚',
            'delivered' => '✅',
            'cancelled' => '❌',
            'failed' => '⚠️',
            'returned' => '↩️',
            default => '📋',
        };
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReadyToShip($query)
    {
        return $query->where('status', 'ready_to_ship');
    }

    public function scopeShipped($query)
    {
        return $query->whereIn('status', ['shipped', 'delivered']);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'pending');
    }

    // Courier Methods
    /**
     * Get courier for this order
     * 
     * Priority:
     * 1. Courier from shipment (if shipment exists)
     * 2. Courier from voucher (stored in additional_data.courier_company)
     * 3. Preferred courier field (legacy)
     * 
     * @return Courier|null
     */
    public function getCourier(): ?Courier
    {
        // First priority: Get courier from shipment
        $shipment = $this->primaryShipment ?? $this->shipments()->first();
        if ($shipment && $shipment->courier_id) {
            return $shipment->courier;
        }

        // Second priority: Get courier from voucher (additional_data)
        $courierCompany = $this->additional_data['courier_company'] ?? null;
        if ($courierCompany) {
            $courier = Courier::where('tenant_id', $this->tenant_id)
                ->where(function($query) use ($courierCompany) {
                    $query->whereRaw('LOWER(name) = ?', [strtolower($courierCompany)])
                          ->orWhereRaw('LOWER(code) = ?', [strtolower($courierCompany)]);
                })
                ->first();
            
            if ($courier) {
                return $courier;
            }
        }

        // Third priority: Get courier from preferred_courier field (legacy)
        if ($this->preferred_courier) {
            $courier = Courier::where('tenant_id', $this->tenant_id)
                ->where(function($query) {
                    $query->whereRaw('LOWER(name) = ?', [strtolower($this->preferred_courier)])
                          ->orWhereRaw('LOWER(code) = ?', [strtolower($this->preferred_courier)]);
                })
                ->first();
            
            if ($courier) {
                return $courier;
            }
        }

        return null;
    }

    /**
     * Get courier name for display
     * 
     * @return string
     */
    public function getCourierName(): string
    {
        $courier = $this->getCourier();
        if ($courier) {
            return $courier->name;
        }

        // Fallback to stored courier company name
        $courierCompany = $this->additional_data['courier_company'] ?? $this->preferred_courier;
        if ($courierCompany) {
            return ucfirst($courierCompany);
        }

        return 'Not assigned';
    }

    /**
     * Get voucher number for this order
     * 
     * @return string|null
     */
    public function getVoucherNumber(): ?string
    {
        // First check shipment tracking number (if it's a voucher)
        $shipment = $this->primaryShipment ?? $this->shipments()->first();
        if ($shipment && $shipment->tracking_number) {
            // If tracking number looks like a voucher (not generated), return it
            // Generated tracking numbers are 12 chars uppercase random
            // Vouchers are usually different format
            if (strlen($shipment->tracking_number) !== 12 || !preg_match('/^[A-Z0-9]{12}$/', $shipment->tracking_number)) {
                return $shipment->tracking_number;
            }
        }

        // Check additional_data
        return $this->additional_data['voucher_number'] ?? null;
    }

    /**
     * Check if order has a voucher
     * 
     * @return bool
     */
    public function hasVoucher(): bool
    {
        return !empty($this->getVoucherNumber());
    }
}
