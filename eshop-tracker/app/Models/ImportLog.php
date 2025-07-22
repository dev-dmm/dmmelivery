<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImportLog extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        
        // Import Source & Type
        'import_type',
        'import_method',
        'source_name',
        
        // File Information
        'file_name',
        'file_path',
        'file_size',
        'file_hash',
        'mime_type',
        
        // Import Status & Progress
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'skipped_rows',
        
        // Results
        'orders_created',
        'orders_updated',
        'customers_created',
        'customers_updated',
        
        // Validation & Mapping
        'field_mapping',
        'validation_rules',
        'import_options',
        
        // Error Handling
        'errors',
        'warnings',
        'error_log',
        'failed_rows_file',
        
        // Processing Information
        'started_at',
        'completed_at',
        'processing_time_seconds',
        'processed_by',
        'job_id',
        
        // Import Configuration
        'create_missing_customers',
        'update_existing_orders',
        'send_notifications',
        'auto_create_shipments',
        'default_status',
        
        // Metadata
        'metadata',
        'notes',
        'import_reference',
        
        // API-specific fields
        'api_endpoint',
        'api_headers',
        'api_payload',
        'api_response_code',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'validation_rules' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'metadata' => 'array',
        'api_headers' => 'array',
        'create_missing_customers' => 'boolean',
        'update_existing_orders' => 'boolean',
        'send_notifications' => 'boolean',
        'auto_create_shipments' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'import_log_id');
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'partial', 'cancelled']);
    }

    // Progress Methods
    public function getProgressPercentage(): float
    {
        if ($this->total_rows <= 0) {
            return 0;
        }
        
        return round(($this->processed_rows / $this->total_rows) * 100, 1);
    }

    public function getSuccessRate(): float
    {
        if ($this->processed_rows <= 0) {
            return 0;
        }
        
        return round(($this->successful_rows / $this->processed_rows) * 100, 1);
    }

    public function getFailureRate(): float
    {
        if ($this->processed_rows <= 0) {
            return 0;
        }
        
        return round(($this->failed_rows / $this->processed_rows) * 100, 1);
    }

    public function getRemainingRows(): int
    {
        return max(0, $this->total_rows - $this->processed_rows);
    }

    // File Methods
    public function hasFile(): bool
    {
        return !empty($this->file_path) && file_exists(storage_path('app/' . $this->file_path));
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        $unit = 0;
        
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unit];
    }

    public function getMimeTypeDisplay(): string
    {
        return match($this->mime_type) {
            'text/csv' => 'CSV',
            'application/vnd.ms-excel' => 'Excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
            'text/xml', 'application/xml' => 'XML',
            'application/json' => 'JSON',
            default => strtoupper($this->mime_type ?? 'Unknown'),
        };
    }

    // Error Methods
    public function hasErrors(): bool
    {
        return !empty($this->errors) || !empty($this->error_log) || $this->failed_rows > 0;
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getErrorCount(): int
    {
        return is_array($this->errors) ? count($this->errors) : 0;
    }

    public function getWarningCount(): int
    {
        return is_array($this->warnings) ? count($this->warnings) : 0;
    }

    public function addError(string $message, int $row = null): void
    {
        $errors = $this->errors ?? [];
        $error = ['message' => $message, 'timestamp' => now()->toISOString()];
        
        if ($row !== null) {
            $error['row'] = $row;
        }
        
        $errors[] = $error;
        $this->update(['errors' => $errors]);
    }

    public function addWarning(string $message, int $row = null): void
    {
        $warnings = $this->warnings ?? [];
        $warning = ['message' => $message, 'timestamp' => now()->toISOString()];
        
        if ($row !== null) {
            $warning['row'] = $row;
        }
        
        $warnings[] = $warning;
        $this->update(['warnings' => $warnings]);
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
        $processingTime = $this->started_at ? now()->diffInSeconds($this->started_at) : null;
        
        $updates = [
            'status' => 'failed',
            'completed_at' => now(),
            'processing_time_seconds' => $processingTime,
        ];
        
        if ($error) {
            $updates['error_log'] = ($this->error_log ? $this->error_log . "\n" : '') . $error;
        }
        
        $this->update($updates);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    public function updateProgress(int $processed, int $successful, int $failed, int $skipped = 0): void
    {
        $this->update([
            'processed_rows' => $processed,
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'skipped_rows' => $skipped,
        ]);
    }

    public function incrementCounts(int $orders_created = 0, int $orders_updated = 0, int $customers_created = 0, int $customers_updated = 0): void
    {
        $this->increment('orders_created', $orders_created);
        $this->increment('orders_updated', $orders_updated);
        $this->increment('customers_created', $customers_created);
        $this->increment('customers_updated', $customers_updated);
    }

    // Time Methods
    public function getProcessingTimeFormatted(): string
    {
        if (!$this->processing_time_seconds) {
            return 'N/A';
        }
        
        $seconds = $this->processing_time_seconds;
        
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . ' minutes';
        } else {
            return round($seconds / 3600, 1) . ' hours';
        }
    }

    public function getDuration(): ?int
    {
        if (!$this->started_at) {
            return null;
        }
        
        $end = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($end);
    }

    // Summary Methods
    public function getSummary(): array
    {
        return [
            'status' => $this->status,
            'progress' => $this->getProgressPercentage(),
            'success_rate' => $this->getSuccessRate(),
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'successful_rows' => $this->successful_rows,
            'failed_rows' => $this->failed_rows,
            'skipped_rows' => $this->skipped_rows,
            'orders_created' => $this->orders_created,
            'orders_updated' => $this->orders_updated,
            'customers_created' => $this->customers_created,
            'customers_updated' => $this->customers_updated,
            'has_errors' => $this->hasErrors(),
            'has_warnings' => $this->hasWarnings(),
            'error_count' => $this->getErrorCount(),
            'warning_count' => $this->getWarningCount(),
            'processing_time' => $this->getProcessingTimeFormatted(),
            'file_size' => $this->getFileSizeFormatted(),
        ];
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
            'cancelled' => '🚫',
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
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getTypeIcon(): string
    {
        return match ($this->import_type) {
            'csv' => '📊',
            'xml' => '📄',
            'json' => '📋',
            'api' => '🔗',
            'manual' => '✋',
            default => '📁',
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

    public function scopeByType($query, $type)
    {
        return $query->where('import_type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    public function scopeFinished($query)
    {
        return $query->whereIn('status', ['completed', 'failed', 'partial', 'cancelled']);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
