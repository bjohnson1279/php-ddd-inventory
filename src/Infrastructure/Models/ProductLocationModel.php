<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLocationModel extends Model
{
    protected $table = 'product_locations';
    public $incrementing = false;
    protected $primaryKey = null;
    public $timestamps = false;

    protected $fillable = ['product_id', 'location_id', 'stock_quantity', 'open_box_quantity', 'damaged_quantity', 'updated_at'];

    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id', 'id');
    }
}
