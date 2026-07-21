<?php

namespace InventoryApp\Infrastructure\Telemetry;

use Ramsey\Uuid\Uuid;

class TraceContext
{
    private static ?string $traceId = null;

    public static function setTraceId(string $traceId): void
    {
        self::$traceId = $traceId;
    }

    public static function getTraceId(): string
    {
        if (self::$traceId === null) {
            self::$traceId = Uuid::uuid4()->toString();
        }
        return self::$traceId;
    }

    public static function generateTraceId(): string
    {
        return Uuid::uuid4()->toString();
    }
}
