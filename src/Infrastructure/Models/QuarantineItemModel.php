<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class QuarantineItemModel extends Model
{
    protected $table = 'quarantine_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'variant_id',
        'quantity',
        'reason',
        'status',
        'location_id',
        'created_at',
        'resolved_at'
    ];
}
