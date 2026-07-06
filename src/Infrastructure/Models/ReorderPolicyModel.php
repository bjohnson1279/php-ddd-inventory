<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ReorderPolicyModel extends Model
{
    protected $table = 'reorder_policies';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sku',
        'location_id',
        'reorder_point',
        'reorder_quantity',
        'safety_stock'
    ];
}
