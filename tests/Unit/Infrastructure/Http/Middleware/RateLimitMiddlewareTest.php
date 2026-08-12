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
        $ip = '10.0.0.' . rand(1, 255); // Use something other than 127.0.0.1 to avoid the PHPUNIT_COMPOSER_INSTALL bypass
        $cacheFile = $this->tempDir . '/rate_limit_' . hash('sha256', $ip) . '.json';
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

    public function testIgnoresForwardedForHeaderIfProxyNotTrusted()
    {
        // Mock remote addr (proxy) that is NOT trusted
        $untrustedProxyIp = '10.0.0.' . rand(1, 255);
        $_SERVER['REMOTE_ADDR'] = $untrustedProxyIp;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.5, 10.0.0.2'; // Should be ignored

        $cacheFile = $this->tempDir . '/rate_limit_' . hash('sha256', $untrustedProxyIp) . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        putenv('TRUSTED_PROXIES='); // No proxies trusted

        $middleware = new RateLimitMiddleware(2, 60);

        // We expect it to be rate limited based on the untrusted proxy IP (REMOTE_ADDR)
        $middleware->handle('req1', function($req) { return new Response(['msg' => 'ok'], 200); });
        $middleware->handle('req2', function($req) { return new Response(['msg' => 'ok'], 200); });

        $response3 = $middleware->handle('req3', function($req) { return new Response(['msg' => 'ok'], 200); });
        $this->assertEquals(429, $response3->getStatusCode());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        putenv('TRUSTED_PROXIES'); // Clear env var
    }

    public function testHandlesForwardedForHeaderIfProxyIsTrusted()
    {
        // Mock remote addr (proxy) that IS trusted
        $trustedProxyIp = '10.0.0.' . rand(1, 255);
        $_SERVER['REMOTE_ADDR'] = $trustedProxyIp;

        // Setup client IP
        $clientIp = '10.0.0.' . rand(1, 255);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $clientIp . ', 192.168.1.1'; // 192.168.1.1 is also trusted below

        $cacheFile = $this->tempDir . '/rate_limit_' . hash('sha256', $clientIp) . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        putenv('TRUSTED_PROXIES=' . $trustedProxyIp . ', 192.168.1.1');

        $middleware = new RateLimitMiddleware(2, 60);

        // We expect it to be rate limited based on the client IP
        $middleware->handle('req1', function($req) { return new Response(['msg' => 'ok'], 200); });
        $middleware->handle('req2', function($req) { return new Response(['msg' => 'ok'], 200); });

        $response3 = $middleware->handle('req3', function($req) { return new Response(['msg' => 'ok'], 200); });
        $this->assertEquals(429, $response3->getStatusCode());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        putenv('TRUSTED_PROXIES'); // Clear env var
    }
}
