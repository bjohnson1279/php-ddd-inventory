<?php

namespace Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Middleware\RequirePermission;
use InventoryApp\Infrastructure\Http\Response;

class RequirePermissionTest extends TestCase
{
    public function test_allows_request_when_permission_exists()
    {
        $middleware = new RequirePermission('purchase_order', 'place');

        $requestMock = $this->getMockBuilder(\stdClass::class)->addMethods(['input'])->getMock();
        $requestMock->method('input')
            ->willReturnCallback(function($key) {
                if ($key === '_auth_permissions') return ['purchase_order:place', 'inventory:view'];
                return null;
            });

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(['data' => 'success']);
        };

        $response = $middleware->handle($requestMock, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_blocks_request_when_permission_missing()
    {
        $middleware = new RequirePermission('purchase_order', 'place');

        $requestMock = $this->getMockBuilder(\stdClass::class)->addMethods(['input'])->getMock();
        $requestMock->method('input')
            ->willReturnCallback(function($key) {
                if ($key === '_auth_permissions') return ['inventory:view'];
                return null;
            });

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(['data' => 'success']);
        };

        $response = $middleware->handle($requestMock, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString('Forbidden', $body['error']);
    }

    public function test_blocks_request_when_no_permissions_provided()
    {
        $middleware = new RequirePermission('purchase_order', 'place');

        $requestMock = $this->getMockBuilder(\stdClass::class)->addMethods(['input'])->getMock();
        $requestMock->method('input')
            ->willReturnCallback(function($key) {
                if ($key === '_auth_permissions') return [];
                return null;
            });

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(['data' => 'success']);
        };

        $response = $middleware->handle($requestMock, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_allows_request_with_global_wildcard()
    {
        $middleware = new RequirePermission('purchase_order', 'place');

        $requestMock = $this->getMockBuilder(\stdClass::class)->addMethods(['input'])->getMock();
        $requestMock->method('input')
            ->willReturnCallback(function($key) {
                if ($key === '_auth_permissions') return ['*:*'];
                return null;
            });

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(['data' => 'success']);
        };

        $response = $middleware->handle($requestMock, $next);

        $this->assertTrue($nextCalled);
    }

    public function test_blocks_cross_tenant_request()
    {
        $middleware = new RequirePermission('purchase_order', 'place');

        $requestMock = $this->getMockBuilder(\stdClass::class)->addMethods(['input'])->getMock();
        $requestMock->method('input')
            ->willReturnCallback(function($key) {
                if ($key === '_auth_permissions') return ['purchase_order:place'];
                if ($key === '_auth_tenant_id') return 'tenant-1';
                if ($key === 'tenantId') return 'tenant-2';
                return null;
            });

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(['data' => 'success']);
        };

        $response = $middleware->handle($requestMock, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cross-tenant', $body['error']);
    }
}
