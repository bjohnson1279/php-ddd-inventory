<?php

namespace InventoryApp\Infrastructure\Http\Middleware;

use InventoryApp\Infrastructure\Http\Response;

class RateLimitMiddleware
{
    private int $limit;
    private int $windowSeconds;

    public function __construct(int $limit = 5, int $windowSeconds = 60)
    {
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle($request, \Closure $next)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($ip) . '.json';

        $now = time();
        $requests = [];

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $requests = array_filter($data, function($timestamp) use ($now) {
                    return ($now - $timestamp) < $this->windowSeconds;
                });
            }
        }

        if (count($requests) >= $this->limit) {
            return new Response(['error' => 'Too Many Requests'], 429);
        }

        $requests[] = $now;
        file_put_contents($cacheFile, json_encode(array_values($requests)));

        return $next($request);
    }
}
