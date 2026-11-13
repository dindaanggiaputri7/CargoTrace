<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShipmentTripAssignment extends Model {
 protected $fillable=['shipment_id','transport_trip_id','leg_order','assigned_at','unassigned_at','status'];
 protected function casts(): array { return ['assigned_at'=>'datetime','unassigned_at'=>'datetime']; }
 public function shipment(){return $this->belongsTo(Shipment::class);}
 public function trip(){return $this->belongsTo(TransportTrip::class,'transport_trip_id');}
}