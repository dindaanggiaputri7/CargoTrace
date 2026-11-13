<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrackingSyncLog extends Model {
 protected $fillable=['tracking_provider_id','transport_trip_id','sync_type','status','records_received','records_created','error_code','message','started_at','finished_at'];
 protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime']; }
 public function provider(){return $this->belongsTo(TrackingProvider::class,'tracking_provider_id');}
 public function trip(){return $this->belongsTo(TransportTrip::class,'transport_trip_id');}
}