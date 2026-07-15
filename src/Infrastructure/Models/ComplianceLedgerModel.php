<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceLedgerModel extends Model
{
    protected $table = 'compliance_ledgers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'actor_id',
        'event_type',
        'sequence_number',
        'previous_hash',
        'current_hash',
        'signature',
        'payload',
        'created_at'
    ];

    public $timestamps = false;
}
