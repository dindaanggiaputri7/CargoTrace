<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    protected $fillable = [
        'booking_request_id', 'quotation_id', 'customer_id', 'amount', 'currency', 'provider',
        'payment_method', 'provider_reference', 'status', 'expires_at', 'paid_at', 'provider_data',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'provider_data' => 'array'];
    }

    public function bookingRequest() { return $this->belongsTo(BookingRequest::class); }
    public function quotation() { return $this->belongsTo(Quotation::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
}
