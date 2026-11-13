<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRequestItem extends Model
{
    protected $fillable = ['booking_request_id', 'item_name', 'description', 'quantity', 'unit_weight', 'weight_unit', 'unit_volume', 'volume_unit'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_weight' => 'decimal:3', 'unit_volume' => 'decimal:3'];
    }

    public function bookingRequest() { return $this->belongsTo(BookingRequest::class); }
}
