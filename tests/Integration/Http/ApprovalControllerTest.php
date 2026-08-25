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
}

class ApprovalControllerTest extends TestCase
{
    private ApprovalController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ApprovalController();
    }

    public function testListWorkflows()
    {
        $request = new ApprovalRequestStub('GET', '/api/approvals/workflows', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listWorkflows($request);

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
    }

    public function testCreateWorkflow()
    {
        $request = new ApprovalRequestStub('POST', '/api/approvals/workflows', [], ['name' => 'Test Workflow', 'triggerEvent' => 'PO_CREATED'], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->createWorkflow($request);

        
        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Created', $body['data']['message']);
    }

    public function testUpdateWorkflow()
    {
        $request = new ApprovalRequestStub('PUT', '/api/approvals/workflows/wf_1', [], ['config' => []], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->updateWorkflow($request, 'wf_1');

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Workflow updated successfully.', $body['message']);
    }

    public function testToggleWorkflow()
    {
        $request = new ApprovalRequestStub('POST', '/api/approvals/workflows/wf_1/toggle', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->toggleWorkflow($request, 'wf_1');

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Workflow toggled successfully.', $body['message']);
    }

    public function testListPendingRequests()
    {
        $request = new ApprovalRequestStub('GET', '/api/approvals/pending', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listPendingRequests($request);

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
    }

    public function testGetApprovalRequest()
    {
        $request = new ApprovalRequestStub('GET', '/api/approvals/req_1', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->getApprovalRequest($request, 'req_1');

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
    }

    public function testSubmitDecision()
    {
        $request = new ApprovalRequestStub('POST', '/api/approvals/req_1/decide', [], ['decision' => 'APPROVED'], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->submitDecision($request, 'req_1');

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Decision submitted successfully.', $body['message']);
    }
}
