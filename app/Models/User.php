<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERATOR = 'operator';
    public const ROLE_CUSTOMER = 'customer';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_OPERATOR, self::ROLE_CUSTOMER];

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isOperator(): bool { return $this->role === self::ROLE_OPERATOR; }
    public function isCustomer(): bool { return $this->role === self::ROLE_CUSTOMER; }

    public function customerShipments() { return $this->hasMany(Shipment::class, 'customer_id'); }
    public function createdShipments() { return $this->hasMany(Shipment::class, 'created_by'); }
    public function trackingEvents() { return $this->hasMany(TrackingEvent::class, 'created_by'); }
    public function reportedExceptions() { return $this->hasMany(ShipmentException::class, 'reported_by'); }
    public function resolvedExceptions() { return $this->hasMany(ShipmentException::class, 'resolved_by'); }
    public function notificationFeed() { return $this->hasMany(Notification::class); }
    public function auditLogs() { return $this->hasMany(AuditLog::class, 'user_id'); }
}
