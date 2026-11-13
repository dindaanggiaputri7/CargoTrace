<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookingRequest extends Model
{
    public const STATUSES = [
        'PENDING_REVIEW', 'NEEDS_REVISION', 'APPROVED', 'QUOTED', 'QUOTE_ACCEPTED',
        'QUOTE_DECLINED', 'PENDING_PAYMENT', 'READY_FOR_CONVERSION', 'REJECTED', 'CANCELLED', 'CONVERTED',
    ];

    protected $fillable = [
        'request_number', 'customer_id', 'origin_location_id', 'destination_location_id',
        'desired_departure_at', 'desired_arrival_at', 'cargo_type', 'cargo_description',
        'package_count', 'total_weight', 'weight_unit', 'total_volume', 'volume_unit',
        'customer_notes', 'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'operator_note', 'shipment_id',
    ];

    protected function casts(): array
    {
        return [
            'desired_departure_at' => 'datetime', 'desired_arrival_at' => 'datetime',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
            'total_weight' => 'decimal:3', 'total_volume' => 'decimal:3',
            'package_count' => 'integer',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function origin(): BelongsTo { return $this->belongsTo(Location::class, 'origin_location_id'); }
    public function destination(): BelongsTo { return $this->belongsTo(Location::class, 'destination_location_id'); }
    public function items(): HasMany { return $this->hasMany(BookingRequestItem::class); }
    public function histories(): HasMany { return $this->hasMany(BookingRequestHistory::class)->orderBy('created_at')->orderBy('id'); }
    public function quotations(): HasMany { return $this->hasMany(Quotation::class)->orderByDesc('version_number'); }
    public function latestQuotation(): HasOne { return $this->hasOne(Quotation::class)->latestOfMany('version_number'); }
    public function payments(): HasMany { return $this->hasMany(BookingPayment::class)->latest(); }
    public function latestPayment(): HasOne { return $this->hasOne(BookingPayment::class)->latestOfMany(); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
}
