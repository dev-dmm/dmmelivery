<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\TenantScope;

class AlertRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger_conditions',
        'alert_type',
        'severity_level',
        'notification_channels',
        'escalation_rules',
        'is_active',
        'last_triggered_at',
        'trigger_count',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'notification_channels' => 'array',
        'escalation_rules' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    // Check if rule should trigger for a shipment
    public function shouldTrigger(Shipment $shipment): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $conditions = $this->trigger_conditions;
        
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $shipment)) {
                return false;
            }
        }

        return true;
    }

    // Evaluate a single condition
    private function evaluateCondition(array $condition, Shipment $shipment): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        $shipmentValue = $this->getShipmentFieldValue($field, $shipment);

        return match($operator) {
            'equals' => $shipmentValue == $value,
            'not_equals' => $shipmentValue != $value,
            'greater_than' => $shipmentValue > $value,
            'less_than' => $shipmentValue < $value,
            'contains' => str_contains($shipmentValue, $value),
            'not_contains' => !str_contains($shipmentValue, $value),
            'in' => in_array($shipmentValue, $value),
            'not_in' => !in_array($shipmentValue, $value),
            default => false
        };
    }

    // Get field value from shipment
    private function getShipmentFieldValue(string $field, Shipment $shipment)
    {
        return match($field) {
            'status' => $shipment->status,
            'hours_in_current_status' => $this->getHoursInCurrentStatus($shipment),
            'delay_hours' => $this->getDelayHours($shipment),
            'courier_id' => $shipment->courier_id,
            'weight' => $shipment->weight,
            'shipping_city' => $this->extractCityFromAddress($shipment->shipping_address),
            'has_predictive_eta' => $shipment->predictiveEta()->exists(),
            'delay_risk_level' => $shipment->predictiveEta?->delay_risk_level ?? 'low',
            'confidence_score' => $shipment->predictiveEta?->confidence_score ?? 1.0,
            default => null
        };
    }

    private function getHoursInCurrentStatus(Shipment $shipment): int
    {
        $currentStatus = $shipment->statusHistory()->latest('happened_at')->first();
        return $currentStatus ? now()->diffInHours($currentStatus->happened_at) : 0;
    }

    private function getDelayHours(Shipment $shipment): int
    {
        if (!$shipment->estimated_delivery) {
            return 0;
        }

        $now = now();
        if ($now->gt($shipment->estimated_delivery)) {
            return $now->diffInHours($shipment->estimated_delivery);
        }

        return 0;
    }

    private function extractCityFromAddress(string $address): string
    {
        $parts = explode(',', $address);
        return trim($parts[count($parts) - 2] ?? 'Unknown');
    }

    // Get severity color
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity_level) {
            'low' => 'blue',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    // Get severity icon
    public function getSeverityIconAttribute(): string
    {
        return match($this->severity_level) {
            'low' => 'ℹ️',
            'medium' => '⚠️',
            'high' => '🚨',
            'critical' => '🔴',
            default => '❓'
        };
    }
}
