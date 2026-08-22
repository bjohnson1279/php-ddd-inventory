<?php

namespace InventoryApp\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\ApprovalController;
use InventoryApp\Infrastructure\Http\Request;

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
        $request = new Request('GET', '/api/approvals/workflows', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listWorkflows($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertIsArray($body['data']);
    }

    public function testCreateWorkflow()
    {
        $request = new Request('POST', '/api/approvals/workflows', [], ['name' => 'Test Workflow', 'triggerEvent' => 'PO_CREATED'], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->createWorkflow($request);
        
        $this->assertEquals(201, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertEquals('Created', $body['data']['message']);
    }

    public function testUpdateWorkflow()
    {
        $request = new Request('PUT', '/api/approvals/workflows/wf_1', [], ['config' => []], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->updateWorkflow($request, 'wf_1');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Workflow updated successfully.', $response->getBody()['message']);
    }

    public function testToggleWorkflow()
    {
        $request = new Request('POST', '/api/approvals/workflows/wf_1/toggle', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->toggleWorkflow($request, 'wf_1');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Workflow toggled successfully.', $response->getBody()['message']);
    }

    public function testListPendingRequests()
    {
        $request = new Request('GET', '/api/approvals/pending', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listPendingRequests($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertIsArray($body['data']);
    }

    public function testGetApprovalRequest()
    {
        $request = new Request('GET', '/api/approvals/req_1', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->getApprovalRequest($request, 'req_1');
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertIsArray($body['data']);
    }

    public function testSubmitDecision()
    {
        $request = new Request('POST', '/api/approvals/req_1/decide', [], ['decision' => 'APPROVED'], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->submitDecision($request, 'req_1');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Decision submitted successfully.', $response->getBody()['message']);
    }
}
