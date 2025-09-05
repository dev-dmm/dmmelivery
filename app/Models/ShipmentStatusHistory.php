<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ShipmentStatusHistory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'location',
        'courier_response',
        'happened_at',
    ];

    protected $casts = [
        'courier_response' => 'array',
        'happened_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
