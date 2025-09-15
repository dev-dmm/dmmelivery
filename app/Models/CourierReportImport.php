<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Scopes\TenantScope;

class CourierReportImport extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'file_name',
        'file_path',
        'file_size',
        'file_hash',
        'mime_type',
        'status',
        'total_rows',
        'processed_rows',
        'matched_rows',
        'unmatched_rows',
        'price_mismatch_rows',
        'successful_rows',
        'failed_rows',
        'started_at',
        'completed_at',
        'processing_time_seconds',
        'results_summary',
        'errors',
        'warnings',
        'error_log',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'results_summary' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'partial']);
    }

    // Progress Methods
    public function getProgressPercentage(): float
    {
        if ($this->total_rows <= 0) {
            return 0;
        }
        
        return round(($this->processed_rows / $this->total_rows) * 100, 1);
    }

    public function getMatchRate(): float
    {
        if ($this->processed_rows <= 0) {
            return 0;
        }
        
        return round(($this->matched_rows / $this->processed_rows) * 100, 1);
    }

    public function getPriceMatchRate(): float
    {
        if ($this->matched_rows <= 0) {
            return 0;
        }
        
        return round((($this->matched_rows - $this->price_mismatch_rows) / $this->matched_rows) * 100, 1);
    }

    // Processing Methods
    public function start(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $processingTime = $this->started_at ? now()->diffInSeconds($this->started_at) : null;
        
        $status = 'completed';
        if ($this->failed_rows > 0 && $this->successful_rows > 0) {
            $status = 'partial';
        } elseif ($this->failed_rows > 0 && $this->successful_rows === 0) {
            $status = 'failed';
        }
        
        $this->update([
            'status' => $status,
            'completed_at' => now(),
            'processing_time_seconds' => $processingTime,
        ]);
    }

    public function fail(string $error = null): void
    {
        $processingTime = $this->started_at ? now()->diffInSeconds($this->started_at) : 0;
        
        $updates = [
            'status' => 'failed',
            'completed_at' => now(),
            'processing_time_seconds' => max(0, $processingTime), // Ensure non-negative
        ];
        
        if ($error) {
            $updates['error_log'] = ($this->error_log ? $this->error_log . "\n" : '') . $error;
        }
        
        $this->update($updates);
    }

    public function updateProgress(int $processed, int $matched, int $unmatched, int $priceMismatch, int $successful, int $failed): void
    {
        $this->update([
            'processed_rows' => $processed,
            'matched_rows' => $matched,
            'unmatched_rows' => $unmatched,
            'price_mismatch_rows' => $priceMismatch,
            'successful_rows' => $successful,
            'failed_rows' => $failed,
        ]);
    }

    // Display Methods
    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'pending' => '⏳',
            'processing' => '⚙️',
            'completed' => '✅',
            'failed' => '❌',
            'partial' => '⚠️',
            default => '📋',
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'processing' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'partial' => 'yellow',
            default => 'gray',
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

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
