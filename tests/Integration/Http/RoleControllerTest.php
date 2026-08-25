<?php

namespace InventoryApp\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\RoleController;

class RoleRequestStub
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

class RoleControllerTest extends TestCase
{
    private RoleController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new RoleController();
    }

    public function testListRolesReturnsRolesList()
    {
        $request = new RoleRequestStub('GET', '/api/roles', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listRoles($request);

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body['data']);
        $this->assertCount(4, $body['data']);
        $this->assertEquals('admin', $body['data'][0]['id']);
    }

    public function testCreateCustomRole()
    {
        $request = new RoleRequestStub('POST', '/api/roles', [], ['name' => 'Custom Manager', 'permissionIds' => ['inv:view']], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->createCustomRole($request);

        
        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['data']['isCustom']);
        $this->assertEquals('Custom Manager', $body['data']['name']);
        $this->assertStringStartsWith('custom_test-tenant_', $body['data']['id']);
    }

    public function testUpdateRolePermissions()
    {
        $request = new RoleRequestStub('PUT', '/api/roles/custom_1/permissions', [], ['permissionIds' => ['inv:edit']], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->updateRolePermissions($request, 'custom_1');

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Role permissions updated successfully.', $body['message']);
    }

    public function testDeleteCustomRole()
    {
        $request = new RoleRequestStub('DELETE', '/api/roles/custom_1', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->deleteCustomRole($request, 'custom_1');

        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Role deleted successfully.', $body['message']);
    }
}
