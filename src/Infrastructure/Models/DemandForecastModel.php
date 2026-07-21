<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class DemandForecastModel extends Model
{
    protected $table = 'demand_forecasts';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'sku',
        'location_id',
        'forecasted_quantity',
        'period_start',
        'period_end',
        'confidence_level',
        'created_at',
        'updated_at'
    ];
}
