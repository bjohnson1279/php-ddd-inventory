<?php

namespace InventoryApp\Infrastructure\Http\Middleware;

use InventoryApp\Infrastructure\Telemetry\TraceContext;

class TraceMiddleware
{
    public static function handle(): void
    {
        $traceId = null;
        if (isset($_SERVER['HTTP_X_TRACE_ID'])) {
            $traceId = $_SERVER['HTTP_X_TRACE_ID'];
        } elseif (isset($_SERVER['HTTP_TRACEPARENT'])) {
            $traceId = $_SERVER['HTTP_TRACEPARENT'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $traceId = $headers['X-Trace-Id'] ?? $headers['traceparent'] ?? null;
        }

        if ($traceId !== null && !preg_match('/^[a-zA-Z0-9\-_]{1,255}$/', $traceId)) {
            $traceId = null;
        }

        if (!$traceId) {
            $traceId = TraceContext::generateTraceId();
        }

        TraceContext::setTraceId($traceId);

        if (!headers_sent()) {
            header("X-Trace-Id: {$traceId}");
        }
    }
}
