<?php

namespace InventoryApp\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\RoleController;
use InventoryApp\Infrastructure\Http\Request;

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
        $request = new Request('GET', '/api/roles', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->listRoles($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertIsArray($body['data']);
        $this->assertCount(4, $body['data']);
        $this->assertEquals('admin', $body['data'][0]['id']);
    }

    public function testCreateCustomRole()
    {
        $request = new Request('POST', '/api/roles', [], ['name' => 'Custom Manager', 'permissionIds' => ['inv:view']], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->createCustomRole($request);
        
        $this->assertEquals(201, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertTrue($body['data']['isCustom']);
        $this->assertEquals('Custom Manager', $body['data']['name']);
        $this->assertStringStartsWith('custom_test-tenant_', $body['data']['id']);
    }

    public function testUpdateRolePermissions()
    {
        $request = new Request('PUT', '/api/roles/custom_1/permissions', [], ['permissionIds' => ['inv:edit']], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->updateRolePermissions($request, 'custom_1');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Role permissions updated successfully.', $response->getBody()['message']);
    }

    public function testDeleteCustomRole()
    {
        $request = new Request('DELETE', '/api/roles/custom_1', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->deleteCustomRole($request, 'custom_1');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Role deleted successfully.', $response->getBody()['message']);
    }
}
