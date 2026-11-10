<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'];
    public const ACTIVE_STATUSES = ['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT'];

    protected $fillable = [
        'shipment_number', 'customer_reference', 'customer_id', 'created_by', 'carrier_id',
        'origin_location_id', 'destination_location_id', 'cargo_type', 'cargo_description',
        'package_count', 'total_weight', 'weight_unit', 'total_volume', 'volume_unit',
        'expected_departure_at', 'expected_arrival_at', 'current_eta', 'status',
        'booked_at', 'picked_up_at', 'in_transit_at', 'delivered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'package_count' => 'integer',
            'total_weight' => 'decimal:3',
            'total_volume' => 'decimal:3',
            'expected_departure_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'current_eta' => 'datetime',
            'booked_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'in_transit_at' => 'datetime',
            'delivered_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function carrier() { return $this->belongsTo(Carrier::class); }
    public function origin() { return $this->belongsTo(Location::class, 'origin_location_id'); }
    public function destination() { return $this->belongsTo(Location::class, 'destination_location_id'); }
    public function items() { return $this->hasMany(ShipmentItem::class); }
    public function trackingEvents() { return $this->hasMany(TrackingEvent::class)->orderBy('event_time')->orderBy('id'); }
    public function latestTrackingEvent() { return $this->hasOne(TrackingEvent::class)->latestOfMany('event_time'); }
    public function exceptions() { return $this->hasMany(ShipmentException::class); }
    public function notifications() { return $this->hasMany(Notification::class); }

    public function effectiveEta()
    {
        return $this->current_eta ?? $this->expected_arrival_at;
    }

    public function scopeDelayed(Builder $query): Builder
    {
        return $query->where('status', '!=', 'DELIVERED')
            ->whereRaw('COALESCE(current_eta, expected_arrival_at) > expected_arrival_at');
    }
}
