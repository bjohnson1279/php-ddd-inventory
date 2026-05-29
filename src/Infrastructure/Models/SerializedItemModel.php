<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class SerializedItemModel extends Model
{
    protected $table = 'serialized_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'variant_id',
        'serial_number',
        'tenant_id',
        'location_id',
        'status',
        'history',
        'created_at',
    ];

    protected $casts = [
        'history' => 'array',
    ];
}
