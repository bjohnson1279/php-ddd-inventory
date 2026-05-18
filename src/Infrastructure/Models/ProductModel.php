<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    protected $table = 'products';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'sku', 'name', 'department', 'reorder_threshold'];

    public function locations()
    {
        return $this->hasMany(ProductLocationModel::class, 'product_id', 'id');
    }

    public $timestamps = false;
}
