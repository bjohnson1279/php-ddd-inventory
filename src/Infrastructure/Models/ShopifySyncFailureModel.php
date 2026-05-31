<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifySyncFailureModel extends Model
{
    protected $table = 'shopify_sync_failures';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'sku',
        'location_id',
        'quantity',
        'attempts',
        'last_error',
        'status',
        'created_at',
        'updated_at'
    ];

    public $timestamps = false;
}
