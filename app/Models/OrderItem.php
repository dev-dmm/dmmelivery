<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Scopes\TenantScope;

class OrderItem extends Model
{
    use HasUuids, HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    protected $fillable = [
        'order_id',
        'tenant_id',
        
        // Product Information
        'product_sku',
        'product_name',
        'product_description',
        'product_category',
        'product_brand',
        'product_model',
        'product_attributes',
        
        // eShop Product References
        'external_product_id',
        'external_variant_id',
        'product_url',
        'product_images',
        
        // Quantity & Pricing
        'quantity',
        'unit_price',
        'discount_amount',
        'final_unit_price',
        'total_price',
        
        // Tax Information
        'tax_rate',
        'tax_amount',
        'tax_class',
        
        // Physical Properties
        'weight',
        'dimensions',
        'is_digital',
        'requires_special_handling',
        'is_fragile',
        'is_hazardous',
        
        // Inventory & Fulfillment
        'fulfillment_status',
        'quantity_shipped',
        'quantity_delivered',
        'quantity_returned',
        
        // Supplier/Vendor Information
        'supplier_name',
        'supplier_sku',
        'supplier_cost',
        
        // Custom Fields
        'custom_fields',
        'special_instructions',
        
        // Serial Numbers / Tracking
        'serial_numbers',
        'batch_numbers',
        
        // Timestamps
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'product_attributes' => 'array',
        'product_images' => 'array',
        'dimensions' => 'array',
        'custom_fields' => 'array',
        'serial_numbers' => 'array',
        'batch_numbers' => 'array',
        'is_digital' => 'boolean',
        'requires_special_handling' => 'boolean',
        'is_fragile' => 'boolean',
        'is_hazardous' => 'boolean',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'supplier_cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Status Methods
    public function isPending(): bool
    {
        return $this->fulfillment_status === 'pending';
    }

    public function isAllocated(): bool
    {
        return $this->fulfillment_status === 'allocated';
    }

    public function isPicked(): bool
    {
        return $this->fulfillment_status === 'picked';
    }

    public function isPacked(): bool
    {
        return $this->fulfillment_status === 'packed';
    }

    public function isShipped(): bool
    {
        return in_array($this->fulfillment_status, ['shipped', 'delivered']);
    }

    public function isDelivered(): bool
    {
        return $this->fulfillment_status === 'delivered';
    }

    public function isCancelled(): bool
    {
        return in_array($this->fulfillment_status, ['cancelled', 'returned', 'exchanged']);
    }

    // Product Methods
    public function getDisplayName(): string
    {
        $name = $this->product_name;
        
        if ($this->product_brand) {
            $name = $this->product_brand . ' ' . $name;
        }
        
        if ($this->product_model) {
            $name .= ' (' . $this->product_model . ')';
        }
        
        return $name;
    }

    public function getProductIdentifier(): string
    {
        return $this->product_sku ?: $this->external_product_id ?: 'SKU-' . substr($this->id, 0, 8);
    }

    public function hasVariations(): bool
    {
        return !empty($this->product_attributes) && is_array($this->product_attributes);
    }

    public function getVariationString(): string
    {
        if (!$this->hasVariations()) {
            return '';
        }
        
        $variations = [];
        foreach ($this->product_attributes as $key => $value) {
            $variations[] = ucfirst($key) . ': ' . $value;
        }
        
        return implode(', ', $variations);
    }

    // Pricing Methods
    public function calculateDiscountPercentage(): float
    {
        if ($this->unit_price <= 0) {
            return 0;
        }
        
        return round(($this->discount_amount / $this->unit_price) * 100, 2);
    }

    public function getTotalSavings(): float
    {
        return $this->discount_amount * $this->quantity;
    }

    public function getSubtotalBeforeDiscount(): float
    {
        return $this->unit_price * $this->quantity;
    }

    public function getFormattedPrice(): string
    {
        return number_format($this->final_unit_price, 2) . ' ' . ($this->order->currency ?? 'EUR');
    }

    public function getFormattedTotal(): string
    {
        return number_format($this->total_price, 2) . ' ' . ($this->order->currency ?? 'EUR');
    }

    // Tax Methods
    public function getTaxPercentage(): float
    {
        return $this->tax_rate * 100;
    }

    public function getTaxAmountFormatted(): string
    {
        return number_format($this->tax_amount, 2) . ' ' . ($this->order->currency ?? 'EUR');
    }

    // Physical Properties Methods
    public function getTotalWeight(): float
    {
        return ($this->weight ?? 0) * $this->quantity;
    }

    public function needsSpecialHandling(): bool
    {
        return $this->requires_special_handling || $this->is_fragile || $this->is_hazardous;
    }

    public function getHandlingFlags(): array
    {
        $flags = [];
        
        if ($this->is_digital) {
            $flags[] = '💾 Digital';
        }
        
        if ($this->is_fragile) {
            $flags[] = '⚠️ Fragile';
        }
        
        if ($this->is_hazardous) {
            $flags[] = '☠️ Hazardous';
        }
        
        if ($this->requires_special_handling) {
            $flags[] = '🔧 Special Handling';
        }
        
        return $flags;
    }

    // Fulfillment Methods
    public function getRemainingQuantity(): int
    {
        return $this->quantity - $this->quantity_shipped;
    }

    public function getShippedPercentage(): float
    {
        if ($this->quantity <= 0) {
            return 0;
        }
        
        return round(($this->quantity_shipped / $this->quantity) * 100, 1);
    }

    public function getDeliveredPercentage(): float
    {
        if ($this->quantity <= 0) {
            return 0;
        }
        
        return round(($this->quantity_delivered / $this->quantity) * 100, 1);
    }

    public function isFullyShipped(): bool
    {
        return $this->quantity_shipped >= $this->quantity;
    }

    public function isFullyDelivered(): bool
    {
        return $this->quantity_delivered >= $this->quantity;
    }

    public function isPartiallyShipped(): bool
    {
        return $this->quantity_shipped > 0 && $this->quantity_shipped < $this->quantity;
    }

    // Status Updates
    public function allocateInventory(): void
    {
        $this->update(['fulfillment_status' => 'allocated']);
    }

    public function markAsPicked(): void
    {
        $this->update(['fulfillment_status' => 'picked']);
    }

    public function markAsPacked(): void
    {
        $this->update(['fulfillment_status' => 'packed']);
    }

    public function markAsShipped(int $quantity = null): void
    {
        $shippedQuantity = $quantity ?? $this->quantity;
        
        $this->update([
            'fulfillment_status' => 'shipped',
            'quantity_shipped' => min($shippedQuantity, $this->quantity),
            'shipped_at' => now(),
        ]);
    }

    public function markAsDelivered(int $quantity = null): void
    {
        $deliveredQuantity = $quantity ?? $this->quantity_shipped;
        
        $this->update([
            'fulfillment_status' => 'delivered',
            'quantity_delivered' => min($deliveredQuantity, $this->quantity),
            'delivered_at' => now(),
        ]);
    }

    public function markAsReturned(int $quantity): void
    {
        $this->update([
            'fulfillment_status' => 'returned',
            'quantity_returned' => min($quantity, $this->quantity_delivered),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['fulfillment_status' => 'cancelled']);
    }

    // Image Methods
    public function getPrimaryImage(): ?string
    {
        if (empty($this->product_images) || !is_array($this->product_images)) {
            return null;
        }
        
        return $this->product_images[0] ?? null;
    }

    public function getAllImages(): array
    {
        return $this->product_images ?? [];
    }

    // Custom Fields Methods
    public function getCustomField(string $key, $default = null)
    {
        if (!$this->custom_fields || !is_array($this->custom_fields)) {
            return $default;
        }
        
        return $this->custom_fields[$key] ?? $default;
    }

    public function setCustomField(string $key, $value): void
    {
        $customFields = $this->custom_fields ?? [];
        $customFields[$key] = $value;
        $this->update(['custom_fields' => $customFields]);
    }

    // Serial Number Methods
    public function hasSerialNumbers(): bool
    {
        return !empty($this->serial_numbers) && is_array($this->serial_numbers);
    }

    public function addSerialNumber(string $serialNumber): void
    {
        $serials = $this->serial_numbers ?? [];
        if (!in_array($serialNumber, $serials)) {
            $serials[] = $serialNumber;
            $this->update(['serial_numbers' => $serials]);
        }
    }

    public function removeSerialNumber(string $serialNumber): void
    {
        $serials = $this->serial_numbers ?? [];
        $serials = array_filter($serials, fn($serial) => $serial !== $serialNumber);
        $this->update(['serial_numbers' => array_values($serials)]);
    }

    // Supplier Methods
    public function hasSupplierInfo(): bool
    {
        return !empty($this->supplier_name) || !empty($this->supplier_sku);
    }

    public function getSupplierDisplay(): string
    {
        if (!$this->hasSupplierInfo()) {
            return 'Unknown Supplier';
        }
        
        return $this->supplier_name . ($this->supplier_sku ? ' (' . $this->supplier_sku . ')' : '');
    }

    public function getProfitMargin(): ?float
    {
        if (!$this->supplier_cost || $this->supplier_cost <= 0) {
            return null;
        }
        
        $profit = $this->final_unit_price - $this->supplier_cost;
        return round(($profit / $this->supplier_cost) * 100, 2);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('fulfillment_status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('fulfillment_status', 'pending');
    }

    public function scopeReadyToShip($query)
    {
        return $query->whereIn('fulfillment_status', ['packed', 'ready_to_ship']);
    }

    public function scopeShipped($query)
    {
        return $query->whereIn('fulfillment_status', ['shipped', 'delivered']);
    }

    public function scopeDigital($query)
    {
        return $query->where('is_digital', true);
    }

    public function scopePhysical($query)
    {
        return $query->where('is_digital', false);
    }

    public function scopeFragile($query)
    {
        return $query->where('is_fragile', true);
    }

    public function scopeBySku($query, $sku)
    {
        return $query->where('product_sku', $sku);
    }
}
