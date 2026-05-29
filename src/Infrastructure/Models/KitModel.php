<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class KitModel extends Model
{
    protected $table = 'kits';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'sku',
        'name',
    ];

    public function components()
    {
        return $this->hasMany(KitComponentModel::class, 'kit_id');
    }
}
