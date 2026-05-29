<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUomConfigurationModel extends Model
{
    protected $table = 'product_uom_configurations';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'variant_id',
        'base_unit',
        'purchase_unit',
        'sale_unit',
    ];

    public function rules()
    {
        return $this->hasMany(UomConversionRuleModel::class, 'configuration_id');
    }
}
