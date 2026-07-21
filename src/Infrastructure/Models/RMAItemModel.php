<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class RMAItemModel extends Model
{
    protected $table = 'rma_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'rma_id',
        'variant_id',
        'quantity',
        'received_quantity',
        'unit_cost_cents',
        'status',
        'disposition',
        'created_at'
    ];

    public function rma()
    {
        return $this->belongsTo(RMAModel::class, 'rma_id', 'id');
    }
}
