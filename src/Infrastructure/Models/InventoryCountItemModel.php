<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCountItemModel extends Model
{
    protected $table = 'inventory_count_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id','inventory_count_id','product_id','sku','location_id','counted_quantity','created_at'];

    public function count()
    {
        return $this->belongsTo(InventoryCountModel::class, 'inventory_count_id', 'id');
    }
}
