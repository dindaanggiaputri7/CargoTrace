<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $fillable = [
        'booking_request_id', 'version_number', 'carrier_id', 'prepared_by', 'amount', 'currency',
        'expected_departure_at', 'expected_arrival_at', 'valid_until', 'terms', 'status', 'accepted_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expected_departure_at' => 'datetime', 'expected_arrival_at' => 'datetime', 'valid_until' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function bookingRequest() { return $this->belongsTo(BookingRequest::class); }
    public function carrier() { return $this->belongsTo(Carrier::class); }
    public function preparer() { return $this->belongsTo(User::class, 'prepared_by'); }
    public function payments() { return $this->hasMany(BookingPayment::class); }
}
