<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class AuditDiscrepancyModel extends Model
{
    protected $table = 'audit_discrepancies';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'type',
        'reference_id',
        'external_ref_id',
        'description',
        'status',
        'occurred_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
