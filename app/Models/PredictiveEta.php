<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\TenantScope;

class PredictiveEta extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'shipment_id',
        'original_eta',
        'predicted_eta',
        'confidence_score',
        'delay_risk_level',
        'delay_factors',
        'weather_impact',
        'traffic_impact',
        'historical_accuracy',
        'route_optimization_suggestions',
        'last_updated_at',
    ];

    protected $casts = [
        'original_eta' => 'datetime',
        'predicted_eta' => 'datetime',
        'confidence_score' => 'decimal:2',
        'delay_factors' => 'array',
        'weather_impact' => 'decimal:2',
        'traffic_impact' => 'decimal:2',
        'historical_accuracy' => 'decimal:2',
        'route_optimization_suggestions' => 'array',
        'last_updated_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    // Risk level calculations
    public function getDelayRiskLevelAttribute(): string
    {
        $score = $this->confidence_score;
        
        if ($score >= 0.8) return 'low';
        if ($score >= 0.6) return 'medium';
        if ($score >= 0.4) return 'high';
        return 'critical';
    }

    public function getDelayRiskColorAttribute(): string
    {
        return match($this->delay_risk_level) {
            'low' => 'green',
            'medium' => 'yellow', 
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    public function getDelayRiskIconAttribute(): string
    {
        return match($this->delay_risk_level) {
            'low' => '✅',
            'medium' => '⚠️',
            'high' => '🚨',
            'critical' => '🔴',
            default => '❓'
        };
    }

    // Check if prediction is significantly different from original
    public function hasSignificantDelay(): bool
    {
        if (!$this->original_eta || !$this->predicted_eta) {
            return false;
        }

        $delayHours = $this->predicted_eta->diffInHours($this->original_eta, false);
        return $delayHours > 2; // More than 2 hours delay
    }

    // Get delay explanation
    public function getDelayExplanation(): string
    {
        $factors = $this->delay_factors ?? [];
        $explanations = [];

        if (isset($factors['weather']) && $factors['weather'] > 0.3) {
            $explanations[] = "Adverse weather conditions";
        }
        
        if (isset($factors['traffic']) && $factors['traffic'] > 0.3) {
            $explanations[] = "Heavy traffic congestion";
        }
        
        if (isset($factors['route_issues']) && $factors['route_issues'] > 0.3) {
            $explanations[] = "Route optimization needed";
        }
        
        if (isset($factors['courier_performance']) && $factors['courier_performance'] > 0.3) {
            $explanations[] = "Historical courier delays";
        }

        if (empty($explanations)) {
            return "No significant delay factors detected";
        }

        return implode(", ", $explanations);
    }
}
