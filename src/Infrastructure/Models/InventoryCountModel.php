<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCountModel extends Model
{
    protected $table = 'inventory_counts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id','status','created_at','completed_at'];

    public function items()
    {
        return $this->hasMany(InventoryCountItemModel::class, 'inventory_count_id', 'id');
    }
}
