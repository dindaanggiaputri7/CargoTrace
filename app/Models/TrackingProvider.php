<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
class TrackingProvider extends Model {
 protected $fillable=['name','provider_type','driver','base_url','credential_encrypted','webhook_secret_hash','is_active','poll_interval_seconds','stale_after_minutes','settings','last_synced_at'];
 protected $hidden=['credential_encrypted','webhook_secret_hash'];
 protected function casts(): array { return ['is_active'=>'boolean','settings'=>'array','last_synced_at'=>'datetime']; }
 public function setCredentialAttribute(?string $value): void { $this->attributes['credential_encrypted']=$value ? Crypt::encryptString($value) : null; }
 public function getCredentialAttribute(): ?string { return $this->credential_encrypted ? Crypt::decryptString($this->credential_encrypted) : null; }
 public function positions(){return $this->hasMany(TripPosition::class);}
 public function syncLogs(){return $this->hasMany(TrackingSyncLog::class);}
}