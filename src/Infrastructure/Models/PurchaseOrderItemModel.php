<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItemModel extends Model
{
    protected $table = 'purchase_order_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'purchase_order_id',
        'variant_id',
        'quantity',
        'received_quantity',
        'unit_cost_cents'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderModel::class, 'purchase_order_id', 'id');
    }
}
