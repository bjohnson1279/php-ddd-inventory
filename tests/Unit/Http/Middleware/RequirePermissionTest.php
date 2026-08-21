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
        
        $requestMock = $this->createMock(\InventoryApp\Infrastructure\Http\Request::class);
        $requestMock->method('input')
            ->with('_auth_permissions')
            ->willReturn(['purchase_order:place', 'inventory:view']);

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
        
        $requestMock = $this->createMock(\InventoryApp\Infrastructure\Http\Request::class);
        $requestMock->method('input')
            ->with('_auth_permissions')
            ->willReturn(['inventory:view']);

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(['data' => 'success']);
        };

        $response = $middleware->handle($requestMock, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(403, $response->getStatusCode());
        
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString('Forbidden', $body['error']);
    }
    
    public function test_blocks_request_when_no_permissions_provided()
    {
        $middleware = new RequirePermission('purchase_order', 'place');
        
        $requestMock = $this->createMock(\InventoryApp\Infrastructure\Http\Request::class);
        $requestMock->method('input')
            ->with('_auth_permissions')
            ->willReturn(null);

        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(['data' => 'success']);
        };

        $response = $middleware->handle($requestMock, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
