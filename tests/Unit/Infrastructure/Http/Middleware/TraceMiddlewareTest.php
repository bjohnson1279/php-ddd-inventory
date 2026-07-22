<?php

namespace Tests\Unit\Infrastructure\Http\Middleware;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Middleware\TraceMiddleware;
use InventoryApp\Infrastructure\Telemetry\TraceContext;

class TraceMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear global variables before each test
        unset($_SERVER['HTTP_X_TRACE_ID']);
        unset($_SERVER['HTTP_TRACEPARENT']);
        TraceContext::setTraceId('');
    }

    public function test_it_accepts_valid_trace_id(): void
    {
        $validId = 'my-valid-trace-123';
        $_SERVER['HTTP_X_TRACE_ID'] = $validId;

        TraceMiddleware::handle();

        $this->assertEquals($validId, TraceContext::getTraceId());
    }

    public function test_it_rejects_invalid_trace_id_with_crlf(): void
    {
        $invalidId = "123\r\nHeader: value";
        $_SERVER['HTTP_X_TRACE_ID'] = $invalidId;

        TraceMiddleware::handle();

        $this->assertNotEquals($invalidId, TraceContext::getTraceId());
        $this->assertNotEmpty(TraceContext::getTraceId());
    }

    public function test_it_rejects_invalid_trace_id_with_xss(): void
    {
        $invalidId = "<script>alert(1)</script>";
        $_SERVER['HTTP_X_TRACE_ID'] = $invalidId;

        TraceMiddleware::handle();

        $this->assertNotEquals($invalidId, TraceContext::getTraceId());
        $this->assertNotEmpty(TraceContext::getTraceId());
    }

    public function test_it_generates_trace_id_if_missing(): void
    {
        TraceMiddleware::handle();

        $this->assertNotEmpty(TraceContext::getTraceId());
    }
}
