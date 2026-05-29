<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class KitComponentModel extends Model
{
    protected $table = 'kit_components';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'kit_id',
        'variant_id',
        'quantity',
    ];
}
