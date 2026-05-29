<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class StockOnboardingItemModel extends Model
{
    protected $table = 'stock_onboarding_items';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'onboarding_id',
        'variant_id',
        'quantity',
        'unit_cost_cents',
    ];

    public function onboarding()
    {
        return $this->belongsTo(StockOnboardingModel::class, 'onboarding_id', 'id');
    }
}
