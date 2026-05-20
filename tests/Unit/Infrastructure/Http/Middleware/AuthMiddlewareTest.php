<?php

namespace Tests\Unit\Infrastructure\Http\Middleware;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Middleware\AuthMiddleware;
use InventoryApp\Infrastructure\Identity\ApiTokenService;
use InventoryApp\Infrastructure\Http\Response;

class AuthMiddlewareTest extends TestCase
{
    private $tokenService;
    private $middleware;

    protected function setUp(): void
    {
        $this->tokenService = $this->createMock(ApiTokenService::class);
        $this->middleware = new AuthMiddleware($this->tokenService);
    }

    public function testMissingAuthHeaderReturns401(): void
    {
        $request = $this->getMockBuilder(\stdClass::class)->addMethods(['header'])->getMock();
        $request->method('header')->with('Authorization', '')->willReturn('');

        $response = $this->middleware->handle($request, function() {});

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Missing or malformed', $response->getContent());
    }

    public function testInvalidTokenReturns401(): void
    {
        $request = $this->getMockBuilder(\stdClass::class)->addMethods(['header'])->getMock();
        $request->method('header')->with('Authorization', '')->willReturn('Bearer invalid-token');

        $this->tokenService->method('validate')->with('invalid-token')->willReturn(null);

        $response = $this->middleware->handle($request, function() {});

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid or expired token', $response->getContent());
    }

    public function testValidTokenCallsNext(): void
    {
        $request = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['header', 'merge'])
            ->getMock();
        $request->method('header')->willReturn('Bearer valid-token');
        
        $tokenData = (object)['user_id' => 'u1', 'tenant_id' => 't1'];
        $this->tokenService->method('validate')->with('valid-token')->willReturn($tokenData);

        $request->expects($this->once())->method('merge')->with([
            '_auth_user_id' => 'u1',
            '_auth_tenant_id' => 't1'
        ]);

        $called = false;
        $this->middleware->handle($request, function($req) use (&$called) {
            $called = true;
            return 'OK';
        });

        $this->assertTrue($called);
    }
}
