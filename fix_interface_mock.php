<?php

$file = 'tests/Unit/Http/Middleware/RequirePermissionTest.php';
$contents = file_get_contents($file);

// In earlier versions (or PHP 8.2), dynamic properties on stdClass were allowed but sometimes caused issues in mock builders depending on PHPUnit version.
// Instead, let's create a stub class inline and use it.

$contents = <<< 'EOD'
<?php

namespace Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Middleware\RequirePermission;
use InventoryApp\Infrastructure\Http\Response;

class RequirePermissionTestRequestStub {
    public $inputs = [];
    public function input($key) {
        return $this->inputs[$key] ?? null;
    }
}

class RequirePermissionTest extends TestCase
{
    public function test_allows_request_when_permission_exists()
    {
        $middleware = new RequirePermission('purchase_order', 'place');

        $requestMock = new RequirePermissionTestRequestStub();
        $requestMock->inputs = ['_auth_permissions' => ['purchase_order:place', 'inventory:view']];

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

        $requestMock = new RequirePermissionTestRequestStub();
        $requestMock->inputs = ['_auth_permissions' => ['inventory:view']];

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

        $requestMock = new RequirePermissionTestRequestStub();
        $requestMock->inputs = ['_auth_permissions' => []];

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

        $requestMock = new RequirePermissionTestRequestStub();
        $requestMock->inputs = ['_auth_permissions' => ['*:*']];

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

        $requestMock = new RequirePermissionTestRequestStub();
        $requestMock->inputs = [
            '_auth_permissions' => ['purchase_order:place'],
            '_auth_tenant_id' => 'tenant-1',
            'tenantId' => 'tenant-2'
        ];

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
EOD;

file_put_contents($file, $contents);
echo "Rewrote RequirePermissionTest.php completely.";
