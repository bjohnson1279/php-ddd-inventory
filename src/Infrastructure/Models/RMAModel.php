<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class RMAModel extends Model
{
    protected $table = 'rmas';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'rma_number',
        'tenant_id',
        'customer_id',
        'location_id',
        'status',
        'created_at',
        'updated_at'
    ];

    public function items()
    {
        return $this->hasMany(RMAItemModel::class, 'rma_id', 'id');
    }
}
