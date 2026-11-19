<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'user_id', 'name', 'company_name', 'email', 'phone', 'address',
        'city', 'region', 'country', 'created_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'customer_record_id');
    }

    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'customer_record_id');
    }
}
