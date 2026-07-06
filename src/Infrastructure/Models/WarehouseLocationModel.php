<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseLocationModel extends Model
{
    protected $table = 'warehouse_locations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'warehouse_id',
        'zone',
        'aisle',
        'rack',
        'shelf',
        'bin',
        'max_weight_grams',
        'max_volume_cubic_meters'
    ];

    public $timestamps = false;
}
