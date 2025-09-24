<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\TenantScope;

class ChatSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'session_id',
        'status',
        'language',
        'context_data',
        'last_activity_at',
        'resolved_at',
        'satisfaction_rating',
        'satisfaction_feedback',
    ];

    protected $casts = [
        'context_data' => 'array',
        'last_activity_at' => 'datetime',
        'resolved_at' => 'datetime',
        'satisfaction_rating' => 'integer',
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

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    // Status checks
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isEscalated(): bool
    {
        return $this->status === 'escalated';
    }

    // Actions
    public function markAsResolved(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function escalate(): void
    {
        $this->update(['status' => 'escalated']);
    }

    public function updateActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    // Get session duration
    public function getDurationAttribute(): string
    {
        if ($this->resolved_at) {
            return $this->created_at->diffForHumans($this->resolved_at, true);
        }
        
        return $this->created_at->diffForHumans(now(), true);
    }

    // Get satisfaction level
    public function getSatisfactionLevelAttribute(): string
    {
        if (!$this->satisfaction_rating) {
            return 'not_rated';
        }

        return match(true) {
            $this->satisfaction_rating >= 4 => 'satisfied',
            $this->satisfaction_rating >= 3 => 'neutral',
            default => 'dissatisfied'
        };
    }

    // Get context for AI
    public function getContextForAI(): array
    {
        $context = $this->context_data ?? [];
        
        // Add customer info
        if ($this->customer) {
            $context['customer'] = [
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ];
        }

        // Add recent shipments
        $recentShipments = $this->customer?->shipments()
            ->latest()
            ->limit(5)
            ->get()
            ->map(function($shipment) {
                return [
                    'tracking_number' => $shipment->tracking_number,
                    'status' => $shipment->status,
                    'estimated_delivery' => $shipment->estimated_delivery?->format('Y-m-d H:i:s'),
                    'courier' => $shipment->courier?->name,
                ];
            });

        if ($recentShipments) {
            $context['recent_shipments'] = $recentShipments;
        }

        return $context;
    }
}
