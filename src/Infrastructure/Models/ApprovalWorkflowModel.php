<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowModel extends Model
{
    protected $table = 'approval_workflows';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'name', 'trigger_event', 'config', 'is_active', 'created_at', 'updated_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requests()
    {
        return $this->hasMany(ApprovalRequestModel::class, 'workflow_id', 'id');
    }
}
