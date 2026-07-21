<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransactionModel extends Model
{
    protected $table = 'inventory_transactions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id', 'tenant_id', 'product_id', 'type', 'quantity_change', 'condition', 'created_at', 'reference_id'];
}
