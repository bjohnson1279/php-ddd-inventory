<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class CostLayerModel extends Model
{
    protected $table = 'inventory_cost_layers';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'variant_id',
        'original_quantity',
        'remaining_quantity',
        'unit_cost_cents',
        'purchase_order_id',
        'received_at',
        'lot_number',
        'expiration_date'
    ];

    protected $casts = [
        'original_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'unit_cost_cents' => 'integer',
    ];
}
