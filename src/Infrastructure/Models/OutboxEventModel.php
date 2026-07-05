<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEventModel extends Model
{
    protected $table = 'outbox_events';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_name',
        'payload',
        'occurred_on',
        'processed_at',
        'attempts',
        'last_error',
        'next_attempt_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
    ];
}
