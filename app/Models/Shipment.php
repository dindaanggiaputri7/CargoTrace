<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    public const STATUSES = ['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'];

    protected $fillable = [
        'shipment_number', 'customer_reference', 'customer_id', 'created_by',
        'carrier_id', 'origin_location_id', 'destination_location_id',
        'cargo_description', 'package_count', 'total_weight', 'weight_unit',
        'status', 'expected_arrival_at', 'current_eta', 'booked_at',
        'picked_up_at', 'delivered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'package_count' => 'integer',
            'total_weight' => 'decimal:3',
            'expected_arrival_at' => 'datetime',
            'current_eta' => 'datetime',
            'booked_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function carrier() { return $this->belongsTo(Carrier::class); }
    public function origin() { return $this->belongsTo(Location::class, 'origin_location_id'); }
    public function destination() { return $this->belongsTo(Location::class, 'destination_location_id'); }
    public function items() { return $this->hasMany(ShipmentItem::class); }
    public function trackingEvents() { return $this->hasMany(TrackingEvent::class)->orderBy('event_time'); }
    public function exceptions() { return $this->hasMany(ShipmentException::class); }
    public function notifications() { return $this->hasMany(Notification::class); }

    public function effectiveEta()
    {
        return $this->current_eta ?? $this->expected_arrival_at;
    }
}
