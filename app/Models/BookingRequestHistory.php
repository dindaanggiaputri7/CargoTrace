<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRequestHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['booking_request_id', 'actor_id', 'from_status', 'to_status', 'note', 'changes', 'created_at'];

    protected function casts(): array { return ['changes' => 'array', 'created_at' => 'datetime']; }
    public function bookingRequest() { return $this->belongsTo(BookingRequest::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
