<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalCustomer extends Model
{
    use HasUuids;

    protected $fillable = [
        'primary_email',
        'primary_phone',
        'hashed_fingerprint',
    ];

    protected $hidden = [
        'hashed_fingerprint', // Don't expose fingerprint in API responses
    ];

    /**
     * Cache for completed shipments count (per request lifecycle)
     */
    protected ?int $completedShipmentsCache = null;

    /**
     * Cache for delivered shipments count (per request lifecycle)
     */
    protected ?int $deliveredShipmentsCache = null;

    /**
     * Get all tenant-specific customer records linked to this global customer
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get all shipments across all tenants for this global customer
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Calculate global delivery score based on all shipments across tenants
     * Returns null if not enough data (< 3 completed shipments)
     * 
     * @return int|null
     */
    public function getGlobalDeliveryScore(): ?int
    {
        $total = $this->shipments()
            ->whereIn('status', ['delivered', 'returned', 'cancelled'])
            ->count();

        // Need at least 3 completed shipments to calculate score
        if ($total < 3) {
            return null;
        }

        $delivered = $this->shipments()
            ->where('status', 'delivered')
            ->count();

        $returned = $this->shipments()
            ->whereIn('status', ['returned', 'cancelled'])
            ->count();

        // Score calculation: +1 for delivered, -1 for returned/cancelled
        $score = $delivered - $returned;

        return $score;
    }

    /**
     * Get global delivery score status information
     * 
     * @return array|null
     */
    public function getGlobalDeliveryScoreStatus(): ?array
    {
        $score = $this->getGlobalDeliveryScore();
        
        if ($score === null) {
            return null;
        }
        
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
     * Check if global customer is considered risky
     * 
     * @param int $threshold
     * @return bool
     */
    public function isRisky(int $threshold = -3): bool
    {
        $score = $this->getGlobalDeliveryScore();
        return $score !== null && $score < $threshold;
    }

    /**
     * Get count of completed shipments globally
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
     * Get count of delivered shipments globally
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
     * Get global delivery success percentage
     * 
     * @return float|null
     */
    public function getGlobalSuccessPercentage(): ?float
    {
        $total = $this->completedShipmentsCount();

        if ($total === 0) {
            return null;
        }

        $delivered = $this->deliveredShipmentsCount();

        return ($delivered / $total) * 100;
    }

    /**
     * Check if global customer has enough data for reliable scoring
     * 
     * @param int $min
     * @return bool
     */
    public function hasEnoughData(int $min = 3): bool
    {
        return $this->completedShipmentsCount() >= $min;
    }
}
