<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentModel extends Model
{
    protected $table = 'shipments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'sku',
        'quantity',
        'destination_address',
        'carrier',
        'tracking_number',
        'label_url',
        'shipping_rate_cents',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'shipping_rate_cents' => 'integer',
    ];
}
