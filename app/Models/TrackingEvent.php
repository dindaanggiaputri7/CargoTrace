<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    public const UPDATED_AT = null;
    public const TYPE_STATUS = 'STATUS_UPDATE';
    public const TYPE_LOCATION = 'LOCATION_UPDATE';

    protected $fillable = [
        'shipment_id', 'location_id', 'created_by', 'event_type', 'status', 'description',
        'event_time', 'latitude', 'longitude', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Tracking events are append-only and cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Tracking events are retained as history and cannot be deleted.'));
    }

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
