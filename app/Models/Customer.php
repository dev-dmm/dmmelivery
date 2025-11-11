<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\TenantScope;

class Customer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'global_customer_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'notes',
        'delivery_score',
    ];

    /**
     * Cache for completed shipments count (per request lifecycle)
     */
    protected ?int $completedShipmentsCache = null;

    /**
     * Cache for delivered shipments count (per request lifecycle)
     */
    protected ?int $deliveredShipmentsCache = null;

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function globalCustomer(): BelongsTo
    {
        return $this->belongsTo(GlobalCustomer::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Get delivery score status information
     * 
     * @return array
     */
    public function getDeliveryScoreStatus(): array
    {
        $score = $this->delivery_score ?? 0;
        
        if ($score >= 5) {
            return [
                'status' => 'excellent',
                'label' => 'Αξιόπιστος',
                'color' => 'green',
                'is_risky' => false,
            ];
        } elseif ($score >= 2) {
            return [
                'status' => 'good',
                'label' => 'Καλός',
                'color' => 'blue',
                'is_risky' => false,
            ];
        } elseif ($score >= 0) {
            return [
                'status' => 'neutral',
                'label' => 'Ουδέτερος',
                'color' => 'gray',
                'is_risky' => false,
            ];
        } elseif ($score >= -2) {
            return [
                'status' => 'warning',
                'label' => 'Προσοχή',
                'color' => 'yellow',
                'is_risky' => true,
            ];
        } else {
            return [
                'status' => 'danger',
                'label' => 'Υψηλός Κίνδυνος',
                'color' => 'red',
                'is_risky' => true,
            ];
        }
    }

    /**
     * Check if customer is considered risky (negative score)
     * 
     * @param int $threshold - Score threshold (default: -3)
     * @return bool
     */
    public function isRisky(int $threshold = -3): bool
    {
        return ($this->delivery_score ?? 0) < $threshold;
    }

    /**
     * Get count of completed shipments (delivered, returned, cancelled)
     * Memoized per request lifecycle to avoid multiple queries
     * 
     * @return int
     */
    public function completedShipmentsCount(): int
    {
        if ($this->completedShipmentsCache !== null) {
            return $this->completedShipmentsCache;
        }

        return $this->completedShipmentsCache = $this->shipments()
            ->whereIn('status', ['delivered', 'returned', 'cancelled'])
            ->count();
    }

    /**
     * Get count of delivered shipments
     * Memoized per request lifecycle to avoid multiple queries
     * 
     * @return int
     */
    public function deliveredShipmentsCount(): int
    {
        if ($this->deliveredShipmentsCache !== null) {
            return $this->deliveredShipmentsCache;
        }

        return $this->deliveredShipmentsCache = $this->shipments()
            ->where('status', 'delivered')
            ->count();
    }

    /**
     * Check if customer has enough completed shipments to trust the score
     * 
     * @param int $min - Minimum number of completed shipments (default: 3)
     * @return bool
     */
    public function hasEnoughData(int $min = 3): bool
    {
        return $this->completedShipmentsCount() >= $min;
    }

    /**
     * Calculate delivery success percentage
     * 
     * @return float|null Returns null if no completed shipments
     */
    public function getSuccessPercentage(): ?float
    {
        $total = $this->completedShipmentsCount();

        if ($total === 0) {
            return null;
        }

        $delivered = $this->deliveredShipmentsCount();

        return ($delivered / $total) * 100;
    }

    /**
     * Get delivery success rate as a range (±2–5% based on volume)
     * Makes it feel statistical and not "exact truth"
     * 
     * @return array|null Returns [min, max] or null if not enough data
     */
    public function getSuccessRange(): ?array
    {
        $percentage = $this->getSuccessPercentage();

        if ($percentage === null) {
            return null;
        }

        // Define range width based on volume (more orders = tighter range)
        $total = $this->completedShipmentsCount();

        if ($total < 5) {
            $offset = 5; // small sample → wider range
        } elseif ($total < 20) {
            $offset = 3;
        } else {
            $offset = 2;
        }

        $min = max(0, round($percentage - $offset));
        $max = min(100, round($percentage + $offset));

        return [$min, $max];
    }

    /**
     * Get success rate range as formatted string
     * 
     * @return string
     */
    public function getSuccessRangeString(): string
    {
        if (!$this->hasEnoughData()) {
            return 'Not enough data yet';
        }

        $range = $this->getSuccessRange();
        
        if ($range === null) {
            return 'Not enough data yet';
        }

        return $range[0] . '% - ' . $range[1] . '%';
    }
}
