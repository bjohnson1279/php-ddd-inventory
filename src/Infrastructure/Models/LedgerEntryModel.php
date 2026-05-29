<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the ledger_entries table.
 *
 * The ledger is append-only — rows are never updated or deleted.
 * Timestamps are managed manually (occurred_at, created_at).
 */
class LedgerEntryModel extends Model
{
    protected $table = 'ledger_entries';

    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id',
        'variant_id',
        'quantity',
        'reason',
        'actor_id',
        'reference_id',
        'occurred_at',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quantity' => 'integer',
    ];
}
