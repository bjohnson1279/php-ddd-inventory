<?php

namespace InventoryApp\Application\Approval;

use InventoryApp\Infrastructure\Models\ApprovalWorkflowModel;
use InventoryApp\Infrastructure\Models\ApprovalRequestModel;
use Exception;

class ManageApprovalWorkflows
{
    private ApprovalWorkflowService $workflowService;

    public function __construct(ApprovalWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    public function createWorkflow(string $tenantId, array $data): array
    {
        if (empty($data['config']['steps'])) {
            throw new Exception("Approval workflow must define at least one approval step.");
        }

        $id = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $workflow = ApprovalWorkflowModel::create([
            'id' => $id,
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'trigger_event' => $data['triggerEvent'],
            'config' => $data['config'],
            'is_active' => $data['isActive'] ?? true,
        ]);

        return $workflow->toArray();
    }

    public function updateWorkflow(string $tenantId, string $workflowId, array $data): array
    {
        $workflow = ApprovalWorkflowModel::where('tenant_id', $tenantId)
            ->where('id', $workflowId)
            ->first();

        if (!$workflow) {
            throw new Exception("Workflow not found");
        }

        if (isset($data['name'])) $workflow->name = $data['name'];
        if (isset($data['config'])) $workflow->config = $data['config'];
        $workflow->save();

        return $workflow->toArray();
    }

    public function toggleWorkflow(string $tenantId, string $workflowId): array
    {
        $workflow = ApprovalWorkflowModel::where('tenant_id', $tenantId)
            ->where('id', $workflowId)
            ->first();

        if (!$workflow) {
            throw new Exception("Workflow not found");
        }

        $workflow->is_active = !$workflow->is_active;
        $workflow->save();

        return $workflow->toArray();
    }

    public function listWorkflows(string $tenantId): array
    {
        return ApprovalWorkflowModel::where('tenant_id', $tenantId)->get()->toArray();
    }

    public function getApprovalRequest(string $tenantId, string $requestId): array
    {
        $request = ApprovalRequestModel::with(['workflow', 'decisions'])
            ->where('tenant_id', $tenantId)
            ->where('id', $requestId)
            ->first();

        if (!$request) {
            throw new Exception("Approval Request not found");
        }

        return $request->toArray();
    }

    public function listPendingRequests(string $tenantId, ?array $userRoles = null): array
    {
        return $this->workflowService->listPendingRequests($tenantId, $userRoles);
    }

    public function submitDecision(string $tenantId, string $requestId, string $userId, string $decision, ?string $notes = null): array
    {
        return $this->workflowService->processDecision($requestId, $userId, $decision, $notes);
    }
}
