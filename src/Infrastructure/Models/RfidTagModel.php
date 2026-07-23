<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class RfidTagModel extends Model
{
    protected $table = 'rfid_tags';
    protected $primaryKey = 'epc';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'epc',
        'sku',
        'serial_number',
        'status',
        'last_seen_at',
        'last_location',
        'created_at'
    ];
}
