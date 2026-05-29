<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class UomConversionRuleModel extends Model
{
    protected $table = 'uom_conversion_rules';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'configuration_id',
        'unit',
        'factor_to_base',
        'label',
    ];
}
