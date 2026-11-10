<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'shipment_id', 'location_id', 'created_by', 'event_type', 'status',
        'description', 'event_time', 'latitude', 'longitude', 'metadata',
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

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
