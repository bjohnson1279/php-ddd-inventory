<?php

namespace InventoryApp\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ApprovalController;

class ApprovalRequestStub
{
    private $method;
    private $uri;
    private $query;
    private $body;
    private $headers;

    public function __construct($method, $uri, $query = [], $body = [], $headers = [])
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function input($key = null, $default = null)
    {
        $all = array_merge($this->query, $this->body, $this->headers);
        if ($key === null) {
            return $all;
        }
        return $all[$key] ?? $default;
    }

    public function getAttribute($key, $default = null)
    {
        if ($key === 'tenantId') {
            return $this->headers['_auth_tenant_id'] ?? $default;
        }
        if ($key === 'userId') {
            return $this->headers['_auth_user_id'] ?? $default;
        }
        if ($key === 'roles') {
            return $this->headers['_auth_roles'] ?? $default;
        }
        return $this->headers['_' . strtolower($key)] ?? $this->headers['_auth_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key))] ?? $default;
    }
}

class ApprovalControllerTest extends TestCase
{
    private ApprovalController $controller;
    private string $tenantId = 'a0000000-0000-0000-0000-000000000001';
    private string $wfId = 'b0000000-0000-0000-0000-000000000001';
    private string $reqId = 'c0000000-0000-0000-0000-000000000001';
    private string $userId = 'd0000000-0000-0000-0000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        try {
            \InventoryApp\Infrastructure\Models\ApprovalDecisionModel::whereNotNull('id')->delete();
            \InventoryApp\Infrastructure\Models\ApprovalRequestModel::whereNotNull('id')->delete();
            \InventoryApp\Infrastructure\Models\ApprovalWorkflowModel::whereNotNull('id')->delete();
        } catch (\Throwable $e) {}

        $this->controller = \InventoryApp\Infrastructure\ServiceContainer::getInstance()->make(ApprovalController::class);

        \InventoryApp\Infrastructure\Models\ApprovalWorkflowModel::create([
            'id' => $this->wfId,
            'tenant_id' => $this->tenantId,
            'name' => 'Initial Workflow',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'steps' => [
                    ['role' => 'manager', 'requiredCount' => 1]
                ]
            ],
            'is_active' => true,
        ]);

        \InventoryApp\Infrastructure\Models\ApprovalRequestModel::create([
            'id' => $this->reqId,
            'tenant_id' => $this->tenantId,
            'workflow_id' => $this->wfId,
            'reference_type' => 'PURCHASE_ORDER',
            'reference_id' => 'PO-100',
            'requester_id' => $this->userId,
            'status' => 'PENDING',
            'current_step' => 0,
            'payload' => ['amount' => 500],
        ]);
    }

    public function testListWorkflows()
    {
        $request = new ApprovalRequestStub('GET', '/api/approvals/workflows', [], [], ['_auth_tenant_id' => $this->tenantId]);
        $response = $this->controller->listWorkflows($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
    }

    public function testCreateWorkflow()
    {
        $request = new ApprovalRequestStub('POST', '/api/approvals/workflows', [], [
            'name' => 'Test Workflow',
            'triggerEvent' => 'INVENTORY_COUNT_COMPLETED',
            'config' => [
                'steps' => [
                    ['role' => 'manager', 'requiredCount' => 1]
                ]
            ]
        ], ['_auth_tenant_id' => $this->tenantId]);
        $response = $this->controller->createWorkflow($request);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Test Workflow', $body['data']['name']);
    }

    public function testUpdateWorkflow()
    {
        $request = new ApprovalRequestStub('PUT', '/api/approvals/workflows/' . $this->wfId, [], [
            'name' => 'Updated Workflow',
            'config' => [
                'steps' => [
                    ['role' => 'admin', 'requiredCount' => 1]
                ]
            ]
        ], ['_auth_tenant_id' => $this->tenantId]);
        $response = $this->controller->updateWorkflow($request, $this->wfId);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Workflow', $body['data']['name']);
    }

    public function testToggleWorkflow()
    {
        $request = new ApprovalRequestStub('POST', '/api/approvals/workflows/' . $this->wfId . '/toggle', [], [], ['_auth_tenant_id' => $this->tenantId]);
        $response = $this->controller->toggleWorkflow($request, $this->wfId);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['data']['is_active']);
    }

    public function testListPendingRequests()
    {
        $request = new ApprovalRequestStub('GET', '/api/approvals/pending', [], [], [
            '_auth_tenant_id' => $this->tenantId,
            '_auth_roles' => ['manager']
        ]);
        $response = $this->controller->listPendingRequests($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
    }

    public function testGetApprovalRequest()
    {
        $request = new ApprovalRequestStub('GET', '/api/approvals/' . $this->reqId, [], [], ['_auth_tenant_id' => $this->tenantId]);
        $response = $this->controller->getApprovalRequest($request, $this->reqId);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals($this->reqId, $body['data']['id']);
    }

    public function testSubmitDecision()
    {
        $request = new ApprovalRequestStub('POST', '/api/approvals/' . $this->reqId . '/decide', [], [
            'decision' => 'APPROVED',
            'notes' => 'Approved in test'
        ], [
            '_auth_tenant_id' => $this->tenantId,
            '_auth_user_id' => $this->userId
        ]);
        $response = $this->controller->submitDecision($request, $this->reqId);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertNotNull($body['data']);
    }
}
