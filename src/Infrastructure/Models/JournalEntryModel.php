<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryModel extends Model
{
    protected $table = 'journal_entries';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'entry_date',
        'description',
        'reference_id',
        'method',
        'lines',
        'created_at',
    ];

    protected $casts = [
        'lines' => 'array',
    ];
}
