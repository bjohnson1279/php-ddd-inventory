<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDecisionModel extends Model
{
    protected $table = 'approval_decisions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // decided_at handled manually or in DB

    protected $fillable = [
        'id', 'request_id', 'step_index', 'decider_id', 'decision', 'notes', 'decided_at'
    ];

    protected $casts = [
        'step_index' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(ApprovalRequestModel::class, 'request_id', 'id');
    }
}
