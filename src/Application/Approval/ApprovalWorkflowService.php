<?php

namespace InventoryApp\Application\Approval;

use InventoryApp\Domain\Approval\ApprovalWorkflow;
use InventoryApp\Domain\Approval\ApprovalRequest;
use InventoryApp\Domain\Approval\ApprovalDecisionRecord;
use InventoryApp\Infrastructure\Models\ApprovalWorkflowModel;
use InventoryApp\Infrastructure\Models\ApprovalRequestModel;
use InventoryApp\Infrastructure\Models\ApprovalDecisionModel;
use InventoryApp\Domain\Shared\Events\EventDispatcher;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;

class ApprovalWorkflowService
{
    private EventDispatcher $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Evaluates whether a domain action should be intercepted.
     */
    public function evaluateAndIntercept(
        string $tenantId,
        string $triggerEvent,
        string $referenceType,
        string $referenceId,
        string $requesterId,
        array $payload
    ): array {
        $workflowRecord = ApprovalWorkflowModel::where('tenant_id', $tenantId)
            ->where('trigger_event', $triggerEvent)
            ->first();

        if (!$workflowRecord || !$workflowRecord->is_active) {
            return ['intercepted' => false];
        }

        $config = is_string($workflowRecord->config) ? json_decode($workflowRecord->config, true) : $workflowRecord->config;

        $workflow = new ApprovalWorkflow(
            $workflowRecord->id,
            $workflowRecord->tenant_id,
            $workflowRecord->name,
            $workflowRecord->trigger_event,
            $workflowRecord->is_active,
            $config,
            $workflowRecord->created_at,
            $workflowRecord->updated_at
        );

        if (!$workflow->shouldTrigger($payload)) {
            return ['intercepted' => false];
        }

        $firstStep = $workflow->getStep(0);
        $expiresAt = null;
        if ($firstStep && isset($firstStep['timeoutHours']) && $firstStep['timeoutHours'] > 0) {
            $expiresAt = new \DateTimeImmutable('+' . $firstStep['timeoutHours'] . ' hours');
        }

        $requestId = \Ramsey\Uuid\Uuid::uuid4()->toString();

        ApprovalRequestModel::create([
            'id' => $requestId,
            'tenant_id' => $tenantId,
            'workflow_id' => $workflowRecord->id,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'requester_id' => $requesterId,
            'status' => 'PENDING',
            'current_step' => 0,
            'payload' => $payload,
            'expires_at' => $expiresAt,
        ]);

        return ['intercepted' => true, 'requestId' => $requestId];
    }

    /**
     * Processes an approve or reject decision on a pending request.
     */
    public function processDecision(
        string $requestId,
        string $deciderId,
        string $decision,
        ?string $notes = null
    ): array {
        $requestRecord = ApprovalRequestModel::with(['workflow', 'decisions'])->find($requestId);

        if (!$requestRecord) {
            throw new Exception("Approval request {$requestId} not found.");
        }

        $workflowConfig = is_string($requestRecord->workflow->config) ? json_decode($requestRecord->workflow->config, true) : $requestRecord->workflow->config;

        $existingDecisions = [];
        foreach ($requestRecord->decisions as $d) {
            $existingDecisions[] = new ApprovalDecisionRecord(
                $d->id,
                $d->step_index,
                $d->decider_id,
                $d->decision,
                $d->notes,
                $d->decided_at
            );
        }

        $request = ApprovalRequest::reconstruct(
            $requestRecord->id,
            $requestRecord->tenant_id,
            $requestRecord->workflow_id,
            $requestRecord->reference_type,
            $requestRecord->reference_id,
            $requestRecord->requester_id,
            is_string($requestRecord->payload) ? json_decode($requestRecord->payload, true) : $requestRecord->payload,
            count($workflowConfig['steps'] ?? []),
            $requestRecord->status,
            $requestRecord->current_step,
            $existingDecisions,
            $requestRecord->expires_at,
            $requestRecord->created_at,
            $requestRecord->updated_at
        );

        $decisionId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $decisionRecord = new ApprovalDecisionRecord(
            $decisionId,
            $request->getCurrentStep(),
            $deciderId,
            $decision,
            $notes,
            new \DateTimeImmutable()
        );

        if ($decision === 'REJECTED') {
            $request->reject($decisionRecord);
        } else {
            $currentStepConfig = $workflowConfig['steps'][$request->getCurrentStep()] ?? null;
            $requiredCount = $currentStepConfig['requiredCount'] ?? 1;
            $request->approve($decisionRecord, $requiredCount);
        }

        Capsule::transaction(function () use ($decisionId, $requestId, $decisionRecord, $deciderId, $decision, $notes, $request) {
            ApprovalDecisionModel::create([
                'id' => $decisionId,
                'request_id' => $requestId,
                'step_index' => $decisionRecord->stepIndex,
                'decider_id' => $deciderId,
                'decision' => $decision,
                'notes' => $notes,
                'decided_at' => $decisionRecord->decidedAt
            ]);

            ApprovalRequestModel::where('id', $requestId)->update([
                'status' => $request->getStatus(),
                'current_step' => $request->getCurrentStep(),
            ]);
        });

        // Dispatch events (in a real app, define these classes)
        // if ($request->getStatus() === ApprovalRequest::STATUS_APPROVED) {
        //     $this->eventDispatcher->dispatch(new ApprovalRequestApprovedEvent(...));
        // } elseif ($request->getStatus() === ApprovalRequest::STATUS_REJECTED) {
        //     $this->eventDispatcher->dispatch(new ApprovalRequestRejectedEvent(...));
        // }

        return [
            'status' => $request->getStatus(),
            'referenceType' => $requestRecord->reference_type,
            'referenceId' => $requestRecord->reference_id,
        ];
    }

    /**
     * Checks for expired/stale approval requests and escalates or expires them.
     */
    public function checkExpiredRequests(): int
    {
        $now = new \DateTimeImmutable();
        $staleRequests = ApprovalRequestModel::with('workflow')
            ->whereIn('status', ['PENDING', 'ESCALATED'])
            ->where('expires_at', '<=', $now)
            ->get();

        $processedCount = 0;

        foreach ($staleRequests as $record) {
            $config = is_string($record->workflow->config) ? json_decode($record->workflow->config, true) : $record->workflow->config;
            $request = ApprovalRequest::reconstruct(
                $record->id, $record->tenant_id, $record->workflow_id,
                $record->reference_type, $record->reference_id, $record->requester_id,
                is_string($record->payload) ? json_decode($record->payload, true) : $record->payload,
                count($config['steps'] ?? []),
                $record->status,
                $record->current_step,
                [],
                $record->expires_at
            );

            $request->escalate();

            $newExpiresAt = null;
            if ($request->isPending()) {
                $nextStep = $config['steps'][$request->getCurrentStep()] ?? null;
                if ($nextStep && isset($nextStep['timeoutHours']) && $nextStep['timeoutHours'] > 0) {
                    $newExpiresAt = new \DateTimeImmutable('+' . $nextStep['timeoutHours'] . ' hours');
                }
            }

            ApprovalRequestModel::where('id', $record->id)->update([
                'status' => $request->getStatus(),
                'current_step' => $request->getCurrentStep(),
                'expires_at' => $newExpiresAt
            ]);

            $processedCount++;
        }

        return $processedCount;
    }

    /**
     * Lists pending approval requests for a given tenant, optionally filtered.
     */
    public function listPendingRequests(string $tenantId, ?array $deciderRoleIds = null): array
    {
        $query = ApprovalRequestModel::with(['workflow', 'decisions'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['PENDING', 'ESCALATED'])
            ->orderBy('created_at', 'desc');

        $requests = $query->get()->toArray();

        if (empty($deciderRoleIds)) {
            return $requests;
        }

        $filtered = [];
        foreach ($requests as $req) {
            $config = is_string($req['workflow']['config']) ? json_decode($req['workflow']['config'], true) : $req['workflow']['config'];
            $currentStep = $config['steps'][$req['current_step']] ?? null;

            if (!$currentStep) continue;

            $approverRoles = $currentStep['approverRoles'] ?? [];
            if (!empty(array_intersect($approverRoles, $deciderRoleIds))) {
                $filtered[] = $req;
            }
        }

        return $filtered;
    }
}
