<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TransportAsset extends Model {
 protected $fillable=['asset_code','name','asset_type','identifier_type','identifier_value','carrier_id','flag_country','is_active','metadata'];
 protected function casts(): array { return ['is_active'=>'boolean','metadata'=>'array']; }
 public function carrier(){return $this->belongsTo(Carrier::class);}
 public function trips(){return $this->hasMany(TransportTrip::class);}
}