<?php

namespace Tests\Unit\Infrastructure\Telemetry;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Telemetry\TraceContext;

class TraceContextTest extends TestCase
{
    public function testGenerateTraceId(): void
    {
        $traceId = TraceContext::generateTraceId();
        $this->assertNotEmpty($traceId);
        $this->assertTrue(is_string($traceId));
    }

    public function testGetAndSetTraceId(): void
    {
        $customId = 'my-custom-trace-id-999';
        TraceContext::setTraceId($customId);
        
        $this->assertEquals($customId, TraceContext::getTraceId());
    }

    public function testGetGeneratesDefaultId(): void
    {
        $first = TraceContext::getTraceId();
        $this->assertNotEmpty($first);
        
        $second = TraceContext::getTraceId();
        $this->assertEquals($first, $second);
    }
}
