<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderModel extends Model
{
    protected $table = 'purchase_orders';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'purchase_order_number',
        'vendor_id',
        'tenant_id',
        'status',
        'location_id'
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItemModel::class, 'purchase_order_id', 'id');
    }
}
