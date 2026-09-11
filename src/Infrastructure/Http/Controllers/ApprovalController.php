<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use Exception;

/**
 * ApprovalController
 *
 * Handles HTTP requests for Approval Workflows and Decisions.
 */
class ApprovalController
{
    private \InventoryApp\Application\Approval\ManageApprovalWorkflows $manageWorkflows;

    public function __construct(\InventoryApp\Application\Approval\ManageApprovalWorkflows $manageWorkflows)
    {
        $this->manageWorkflows = $manageWorkflows;
    }

    private function getTenantId($request): string
    {
        return $request->getAttribute('tenantId') ?? (function_exists('tenantId') ? tenantId() : 'system');
    }

    private function getUserId($request): string
    {
        return $request->getAttribute('userId') ?? 'system';
    }

    /**
     * Lists all approval workflows for the tenant.
     */
    public function listWorkflows($request): Response
    {
        try {
            $workflows = $this->manageWorkflows->listWorkflows($this->getTenantId($request));
            return new Response(['data' => $workflows]);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Creates a new approval workflow.
     */
    public function createWorkflow($request): Response
    {
        try {
            $data = $request->input();
            $workflow = $this->manageWorkflows->createWorkflow($this->getTenantId($request), $data);
            return new Response(['data' => $workflow], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Updates an approval workflow configuration.
     */
    public function updateWorkflow($request, $workflowId): Response
    {
        try {
            $data = $request->input();
            $workflow = $this->manageWorkflows->updateWorkflow($this->getTenantId($request), $workflowId, $data);
            return new Response(['data' => $workflow]);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Toggles an approval workflow active/inactive.
     */
    public function toggleWorkflow($request, $workflowId): Response
    {
        try {
            $workflow = $this->manageWorkflows->toggleWorkflow($this->getTenantId($request), $workflowId);
            return new Response(['data' => $workflow]);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Lists pending approval requests.
     */
    public function listPendingRequests($request): Response
    {
        try {
            $userRoles = $request->getAttribute('roles') ?? [];
            $requests = $this->manageWorkflows->listPendingRequests($this->getTenantId($request), $userRoles);
            return new Response(['data' => $requests]);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Gets a single approval request with its decision history.
     */
    public function getApprovalRequest($request, $requestId): Response
    {
        try {
            $requestData = $this->manageWorkflows->getApprovalRequest($this->getTenantId($request), $requestId);
            return new Response(['data' => $requestData]);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Submits an approval or rejection decision.
     */
    public function submitDecision($request, $requestId): Response
    {
        $data = $request->input();
        $decision = $data['decision'] ?? null;
        $notes = $data['notes'] ?? null;

        if (!$decision) {
            return new Response(['error' => 'Decision is required (APPROVED or REJECTED)'], 400);
        }

        try {
            $result = $this->manageWorkflows->submitDecision(
                $this->getTenantId($request), 
                $requestId, 
                $this->getUserId($request), 
                $decision, 
                $notes
            );
            return new Response(['data' => $result]);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
