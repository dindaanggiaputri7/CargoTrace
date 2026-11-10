<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentException extends Model
{
    use HasFactory;

    public const TYPES = [
        'CUSTOMS_HOLD', 'WEATHER_DELAY', 'PORT_CONGESTION',
        'DAMAGED', 'MISROUTED', 'DOCUMENTATION_ISSUE',
    ];
    public const SEVERITIES = ['LOW', 'MEDIUM', 'HIGH'];
    public const STATUSES = ['OPEN', 'INVESTIGATING', 'RESOLVED'];

    protected $fillable = [
        'shipment_id', 'reported_by', 'resolved_by', 'exception_type', 'severity', 'status',
        'title', 'description', 'detected_at', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
}
