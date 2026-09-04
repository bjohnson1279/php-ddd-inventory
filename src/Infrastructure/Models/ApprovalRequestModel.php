<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequestModel extends Model
{
    protected $table = 'approval_requests';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'workflow_id', 'reference_type', 'reference_id', 
        'requester_id', 'status', 'current_step', 'payload', 'expires_at', 
        'created_at', 'updated_at'
    ];

    protected $casts = [
        'current_step' => 'integer',
        'payload' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflowModel::class, 'workflow_id', 'id');
    }

    public function decisions()
    {
        return $this->hasMany(ApprovalDecisionModel::class, 'request_id', 'id');
    }
}
