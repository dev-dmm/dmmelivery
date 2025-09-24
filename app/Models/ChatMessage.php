<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\TenantScope;

class ChatMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'chat_session_id',
        'sender_type',
        'sender_id',
        'message',
        'message_type',
        'metadata',
        'is_ai_generated',
        'confidence_score',
        'intent',
        'entities',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_ai_generated' => 'boolean',
        'confidence_score' => 'decimal:2',
        'entities' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Message type checks
    public function isFromCustomer(): bool
    {
        return $this->sender_type === 'customer';
    }

    public function isFromAI(): bool
    {
        return $this->sender_type === 'ai';
    }

    public function isFromAgent(): bool
    {
        return $this->sender_type === 'agent';
    }

    // Message type styling
    public function getMessageTypeColorAttribute(): string
    {
        return match($this->message_type) {
            'text' => 'blue',
            'quick_reply' => 'green',
            'shipment_info' => 'purple',
            'action_button' => 'orange',
            'error' => 'red',
            default => 'gray'
        };
    }

    public function getMessageTypeIconAttribute(): string
    {
        return match($this->message_type) {
            'text' => '💬',
            'quick_reply' => '⚡',
            'shipment_info' => '📦',
            'action_button' => '🔘',
            'error' => '❌',
            default => '❓'
        };
    }

    // Get sender display name
    public function getSenderDisplayNameAttribute(): string
    {
        return match($this->sender_type) {
            'customer' => 'Customer',
            'ai' => 'AI Assistant',
            'agent' => $this->sender?->name ?? 'Support Agent',
            default => 'Unknown'
        };
    }

    // Get formatted message
    public function getFormattedMessageAttribute(): string
    {
        if ($this->message_type === 'shipment_info' && isset($this->metadata['shipment'])) {
            $shipment = $this->metadata['shipment'];
            return "📦 **Shipment {$shipment['tracking_number']}**\n" .
                   "Status: {$shipment['status']}\n" .
                   "Courier: {$shipment['courier']}\n" .
                   "ETA: {$shipment['estimated_delivery']}";
        }

        return $this->message;
    }

    // Check if message needs human review
    public function needsHumanReview(): bool
    {
        return $this->isFromAI() && 
               $this->confidence_score < 0.7 && 
               in_array($this->intent, ['complaint', 'escalation', 'complex_query']);
    }

    // Get intent color
    public function getIntentColorAttribute(): string
    {
        return match($this->intent) {
            'tracking' => 'blue',
            'delivery' => 'green',
            'complaint' => 'red',
            'escalation' => 'orange',
            'greeting' => 'purple',
            'goodbye' => 'gray',
            default => 'gray'
        };
    }
}
