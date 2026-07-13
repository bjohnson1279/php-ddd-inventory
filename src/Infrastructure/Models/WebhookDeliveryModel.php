<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDeliveryModel extends Model
{
    protected $table = 'webhook_deliveries';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'subscription_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'last_error',
        'next_attempt_at',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
