<?php

namespace InventoryApp\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ApprovalController;
use InventoryApp\Application\Approval\ManageApprovalWorkflows;

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
        return $default;
    }
}

class ApprovalControllerTest extends TestCase
{
    private ApprovalController $controller;
    private $manageWorkflowsMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manageWorkflowsMock = $this->createMock(ManageApprovalWorkflows::class);
        $this->controller = new ApprovalController($this->manageWorkflowsMock);
    }

    public function testListWorkflows()
    {
        $this->manageWorkflowsMock->expects($this->once())
            ->method('listWorkflows')
            ->with('test-tenant')
            ->willReturn([['id' => 'wf_1', 'name' => 'WF']]);

        $request = new ApprovalRequestStub('GET', '/api/approvals/workflows', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listWorkflows($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
        $this->assertCount(1, $body['data']);
    }

    public function testCreateWorkflow()
    {
        $this->manageWorkflowsMock->expects($this->once())
            ->method('createWorkflow')
            ->with('test-tenant', $this->anything())
            ->willReturn(['id' => 'new_wf_id', 'message' => 'Created']);

        $request = new ApprovalRequestStub('POST', '/api/approvals/workflows', [], ['name' => 'Test Workflow', 'triggerEvent' => 'PO_CREATED', 'config' => ['steps' => [['approver_role' => 'manager']]]], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->createWorkflow($request);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $body['data']);
    }

    public function testUpdateWorkflow()
    {
        $this->manageWorkflowsMock->expects($this->once())
            ->method('updateWorkflow')
            ->with('test-tenant', 'wf_1', $this->anything())
            ->willReturn(['id' => 'wf_1', 'message' => 'Workflow updated successfully.']);

        $request = new ApprovalRequestStub('PUT', '/api/approvals/workflows/wf_1', [], ['config' => []], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->updateWorkflow($request, 'wf_1');

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('wf_1', $body['data']['id']);
    }

    public function testToggleWorkflow()
    {
        $this->manageWorkflowsMock->expects($this->once())
            ->method('toggleWorkflow')
            ->with('test-tenant', 'wf_1')
            ->willReturn(['id' => 'wf_1', 'is_active' => false, 'message' => 'Workflow toggled successfully.']);

        $request = new ApprovalRequestStub('POST', '/api/approvals/workflows/wf_1/toggle', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->toggleWorkflow($request, 'wf_1');

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('wf_1', $body['data']['id']);
        $this->assertFalse($body['data']['is_active']);
    }

    public function testListPendingRequests()
    {
        $this->manageWorkflowsMock->expects($this->once())
            ->method('listPendingRequests')
            ->with('test-tenant', [])
            ->willReturn([['id' => 'req_1']]);

        $request = new ApprovalRequestStub('GET', '/api/approvals/pending', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listPendingRequests($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
        $this->assertCount(1, $body['data']);
    }

    public function testGetApprovalRequest()
    {
        $this->manageWorkflowsMock->expects($this->once())
            ->method('getApprovalRequest')
            ->with('test-tenant', 'req_1')
            ->willReturn(['id' => 'req_1']);

        $request = new ApprovalRequestStub('GET', '/api/approvals/req_1', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->getApprovalRequest($request, 'req_1');

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
    }

    public function testSubmitDecision()
    {
        $this->manageWorkflowsMock->expects($this->once())
            ->method('submitDecision')
            ->with('test-tenant', 'req_1', 'system', 'APPROVED', null)
            ->willReturn(['status' => 'APPROVED']);

        $request = new ApprovalRequestStub('POST', '/api/approvals/req_1/decide', [], ['decision' => 'APPROVED'], ['_auth_tenant_id' => 'test-tenant', '_auth_roles' => ['admin']]);
        $response = $this->controller->submitDecision($request, 'req_1');

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('APPROVED', $body['data']['status']);
    }
}