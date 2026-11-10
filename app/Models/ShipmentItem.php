<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'item_name', 'sku', 'description', 'quantity', 'unit_weight', 'weight_unit', 'unit_volume', 'volume_unit',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_weight' => 'decimal:3', 'unit_volume' => 'decimal:3'];
    }

    public function shipment() { return $this->belongsTo(Shipment::class); }
}
