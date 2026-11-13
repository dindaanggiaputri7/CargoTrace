<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExternalProviderEvent extends Model {
 protected $fillable=['tracking_provider_id','provider_event_id','shipment_id','event_category','event_type','description','observed_at','payload'];
 protected function casts(): array { return ['observed_at'=>'datetime','payload'=>'array']; }
 public function provider(){return $this->belongsTo(TrackingProvider::class,'tracking_provider_id');}
 public function shipment(){return $this->belongsTo(Shipment::class);}
}