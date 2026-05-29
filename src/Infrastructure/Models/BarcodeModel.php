<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodeModel extends Model
{
    protected $table = 'barcodes';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'value',
        'variant_id',
        'symbology',
        'source',
        'is_primary',
        'created_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];
}
