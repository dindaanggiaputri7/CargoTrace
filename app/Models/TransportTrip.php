<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TransportTrip extends Model {
 protected $fillable=['trip_number','carrier_id','transport_asset_id','transport_mode','origin_location_id','destination_location_id','scheduled_departure_at','scheduled_arrival_at','actual_departure_at','actual_arrival_at','status','notes','created_by'];
 protected function casts(): array { return ['scheduled_departure_at'=>'datetime','scheduled_arrival_at'=>'datetime','actual_departure_at'=>'datetime','actual_arrival_at'=>'datetime']; }
 public function carrier(){return $this->belongsTo(Carrier::class);}
 public function asset(){return $this->belongsTo(TransportAsset::class,'transport_asset_id');}
 public function origin(){return $this->belongsTo(Location::class,'origin_location_id');}
 public function destination(){return $this->belongsTo(Location::class,'destination_location_id');}
 public function assignments(){return $this->hasMany(ShipmentTripAssignment::class);}
 public function positions(){return $this->hasMany(TripPosition::class)->orderBy('observed_at');}
 public function latestPosition(){return $this->hasOne(TripPosition::class)->latestOfMany('observed_at');}
}