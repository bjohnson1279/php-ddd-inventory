<?php

namespace Tests\Unit\Infrastructure\Http\Middleware;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Middleware\RateLimitMiddleware;
use InventoryApp\Infrastructure\Http\Response;

class RateLimitMiddlewareTest extends TestCase
{
    private $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir();
        // clear old cache
        $ip = '127.0.0.1';
        $cacheFile = $this->tempDir . '/rate_limit_' . md5($ip) . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    public function testAllowsRequestsUnderLimit()
    {
        $middleware = new RateLimitMiddleware(2, 60);

        $response1 = $middleware->handle('req1', function($req) { return new Response(['msg' => 'ok'], 200); });
        $this->assertEquals(200, $response1->getStatusCode());

        $response2 = $middleware->handle('req2', function($req) { return new Response(['msg' => 'ok'], 200); });
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testBlocksRequestsOverLimit()
    {
        $middleware = new RateLimitMiddleware(2, 60);

        $middleware->handle('req1', function($req) { return new Response(['msg' => 'ok'], 200); });
        $middleware->handle('req2', function($req) { return new Response(['msg' => 'ok'], 200); });

        $response3 = $middleware->handle('req3', function($req) { return new Response(['msg' => 'ok'], 200); });
        $this->assertEquals(429, $response3->getStatusCode());

        $content = json_decode($response3->getContent(), true);
        $this->assertEquals('Too Many Requests', $content['error']);
    }
}
