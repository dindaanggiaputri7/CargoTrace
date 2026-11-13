<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TripPosition extends Model {
 protected $fillable=['transport_trip_id','tracking_provider_id','provider_event_id','latitude','longitude','speed_knots','heading','observed_at','received_at','source','quality','raw_payload'];
 protected function casts(): array { return ['observed_at'=>'datetime','received_at'=>'datetime','raw_payload'=>'array','latitude'=>'float','longitude'=>'float','speed_knots'=>'float','heading'=>'float']; }
 public function trip(){return $this->belongsTo(TransportTrip::class,'transport_trip_id');}
 public function provider(){return $this->belongsTo(TrackingProvider::class,'tracking_provider_id');}
}