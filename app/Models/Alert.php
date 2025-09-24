<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\TenantScope;

class Alert extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'alert_rule_id',
        'shipment_id',
        'title',
        'description',
        'alert_type',
        'severity_level',
        'status',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'escalation_level',
        'notification_sent',
        'metadata',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'notification_sent' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Status checks
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isEscalated(): bool
    {
        return $this->escalation_level > 0;
    }

    // Actions
    public function acknowledge(User $user): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);
    }

    public function resolve(User $user, string $resolution = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'metadata' => array_merge($this->metadata ?? [], [
                'resolution' => $resolution,
                'resolved_at' => now()->toISOString(),
            ]),
        ]);
    }

    public function escalate(): void
    {
        $this->increment('escalation_level');
        
        $this->update([
            'metadata' => array_merge($this->metadata ?? [], [
                'escalated_at' => now()->toISOString(),
                'escalation_level' => $this->escalation_level,
            ]),
        ]);
    }

    // Get severity styling
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

    // Get status styling
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'red',
            'acknowledged' => 'yellow',
            'resolved' => 'green',
            default => 'gray'
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            'active' => '🔴',
            'acknowledged' => '⚠️',
            'resolved' => '✅',
            default => '❓'
        };
    }

    // Get time since triggered
    public function getTimeSinceTriggeredAttribute(): string
    {
        return $this->triggered_at->diffForHumans();
    }

    // Get escalation status
    public function getEscalationStatusAttribute(): string
    {
        if ($this->escalation_level === 0) {
            return 'Not escalated';
        }
        
        return "Escalated to level {$this->escalation_level}";
    }
}
