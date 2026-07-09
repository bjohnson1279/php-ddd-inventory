<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookSubscriptionModel extends Model
{
    protected $table = 'webhook_subscriptions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'target_url',
        'secret',
        'event_types',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
