<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class StockOnboardingModel extends Model
{
    protected $table = 'stock_onboardings';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'location_id',
        'as_of_date',
        'status',
        'created_at',
    ];

    public function items()
    {
        return $this->hasMany(StockOnboardingItemModel::class, 'onboarding_id', 'id');
    }
}
