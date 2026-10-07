<?php

namespace InventoryApp\Tests\Integration\Http;

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\RoleController;
use Illuminate\Database\Capsule\Manager as Capsule;

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
        Capsule::table('roles')->insertOrIgnore([
            'id' => 'custom_1',
            'name' => 'Custom Role 1'
        ]);

        $request = new RoleRequestStub('DELETE', '/api/roles/custom_1', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->deleteCustomRole($request, 'custom_1');

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Role deleted successfully.', $body['message']);
        $this->assertNull(Capsule::table('roles')->where('id', 'custom_1')->first());
    }

    public function testDeleteSystemRoleFails()
    {
        $request = new RoleRequestStub('DELETE', '/api/roles/admin', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->deleteCustomRole($request, 'admin');

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Cannot delete system role.', $body['error']);
    }

    public function testDeleteRoleWithAssignedUsersFails()
    {
        Capsule::table('roles')->insertOrIgnore([
            'id' => 'custom_assigned',
            'name' => 'Assigned Role'
        ]);
        $userId = '11111111-1111-4111-8111-111111111111';
        Capsule::table('users')->insertOrIgnore([
            'id' => $userId,
            'tenant_id' => 'test-tenant',
            'email' => 'user1@example.com',
            'password_hash' => 'hash',
            'name' => 'Test User'
        ]);
        Capsule::table('user_roles')->insertOrIgnore([
            'user_id' => $userId,
            'role_id' => 'custom_assigned'
        ]);

        $request = new RoleRequestStub('DELETE', '/api/roles/custom_assigned', [], [], ['_auth_tenant_id' => 'test-tenant']);
        $response = $this->controller->deleteCustomRole($request, 'custom_assigned');

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Cannot delete role assigned to users.', $body['error']);

        // Clean up
        Capsule::table('user_roles')->where('role_id', 'custom_assigned')->delete();
        Capsule::table('roles')->where('id', 'custom_assigned')->delete();
        Capsule::table('users')->where('id', $userId)->delete();
    }
}
