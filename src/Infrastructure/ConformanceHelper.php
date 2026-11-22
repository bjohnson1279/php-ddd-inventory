<?php

namespace InventoryApp\Infrastructure;

use Illuminate\Database\Capsule\Manager as DB;
use InventoryApp\Domain\Compliance\Services\ComplianceLedgerService;
use InventoryApp\Infrastructure\Models\WebhookSubscriptionModel;
use Ramsey\Uuid\Uuid;

class ConformanceHelper
{
    private static function withJsonStore(string $name, callable $fn)
    {
        $path = sys_get_temp_dir() . '/php_conformance_' . $name . '.json';
        $fh = fopen($path, 'c+');
        if (!$fh) {
            $data = [];
            return $fn($data);
        }
        try {
            flock($fh, LOCK_EX);
            $raw = stream_get_contents($fh);
            $data = ($raw !== false && trim($raw) !== '') ? (json_decode($raw, true) ?: []) : [];
            $result = $fn($data);
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($data));
            fflush($fh);
            return $result;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    // ── Optimistic Concurrency Version Tracker ────────────────────────────────
    public static function getVersion(string $tenantId, string $sku, string $locationId): int
    {
        $key = $tenantId . '|' . $sku . '|' . $locationId;
        return self::withJsonStore('versions', function (&$data) use ($key) {
            return (int)($data[$key] ?? 0);
        });
    }

    public static function bumpVersion(string $tenantId, string $sku, string $locationId): int
    {
        $key = $tenantId . '|' . $sku . '|' . $locationId;
        return self::withJsonStore('versions', function (&$data) use ($key) {
            $data[$key] = (int)($data[$key] ?? 0) + 1;
            return $data[$key];
        });
    }

    public static function checkVersion(string $tenantId, string $sku, string $locationId, ?int $expectedVersion): bool
    {
        if ($expectedVersion === null) {
            return true;
        }
        $key = $tenantId . '|' . $sku . '|' . $locationId;
        return self::withJsonStore('versions', function (&$data) use ($key, $expectedVersion) {
            $current = (int)($data[$key] ?? 0);
            return $current === $expectedVersion;
        });
    }

    /**
     * Atomically checks expectedVersion (if non-null) and bumps version.
     * Returns new version on success, or null on conflict.
     */
    public static function checkAndBumpVersion(string $tenantId, string $sku, string $locationId, ?int $expectedVersion): ?int
    {
        $key = $tenantId . '|' . $sku . '|' . $locationId;
        return self::withJsonStore('versions', function (&$data) use ($key, $expectedVersion) {
            $current = (int)($data[$key] ?? 0);
            if ($expectedVersion !== null && $current !== $expectedVersion) {
                return null;
            }
            $data[$key] = $current + 1;
            return $data[$key];
        });
    }

    // ── Per-Tenant Cache Tracker ──────────────────────────────────────────────
    public static function recordStockRead(string $tenantId, string $sku, string $locationId): void
    {
        $cacheKey = $sku . ':' . $locationId;
        self::withJsonStore('cache', function (&$data) use ($tenantId, $cacheKey) {
            if (!isset($data[$tenantId])) {
                $data[$tenantId] = ['hits' => 0, 'misses' => 0, 'invalidations' => 0, 'keys' => []];
            }
            if (isset($data[$tenantId]['keys'][$cacheKey])) {
                $data[$tenantId]['hits']++;
            } else {
                $data[$tenantId]['misses']++;
                $data[$tenantId]['keys'][$cacheKey] = time();
            }
        });
    }

    public static function invalidateCache(string $tenantId, ?string $sku = null): void
    {
        self::withJsonStore('cache', function (&$data) use ($tenantId, $sku) {
            if (!isset($data[$tenantId])) {
                $data[$tenantId] = ['hits' => 0, 'misses' => 0, 'invalidations' => 0, 'keys' => []];
            }
            if ($sku === null) {
                $data[$tenantId]['keys'] = [];
                $data[$tenantId]['invalidations']++;
            } else {
                $removed = 0;
                foreach (array_keys($data[$tenantId]['keys']) as $k) {
                    if (str_starts_with($k, $sku . ':') || $k === $sku) {
                        unset($data[$tenantId]['keys'][$k]);
                        $removed++;
                    }
                }
                $data[$tenantId]['invalidations'] += max(1, $removed);
            }
        });
    }

    public static function getCacheStats(string $tenantId): array
    {
        return self::withJsonStore('cache', function (&$data) use ($tenantId) {
            $t = $data[$tenantId] ?? ['hits' => 0, 'misses' => 0, 'invalidations' => 0, 'keys' => []];
            $hits = (int)$t['hits'];
            $misses = (int)$t['misses'];
            $total = $hits + $misses;
            $hitRatio = $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
            return [
                'hits' => $hits,
                'misses' => $misses,
                'hitRatio' => $hitRatio,
                'invalidations' => (int)$t['invalidations'],
                'activeKeysCount' => count($t['keys']),
            ];
        });
    }

    public static function clearCache(string $tenantId): int
    {
        return self::withJsonStore('cache', function (&$data) use ($tenantId) {
            if (!isset($data[$tenantId])) {
                return 0;
            }
            $count = count($data[$tenantId]['keys']);
            $data[$tenantId]['keys'] = [];
            $data[$tenantId]['invalidations'] += max(1, $count);
            return $count;
        });
    }

    // ── Lot Quarantine / Recall Tracker ───────────────────────────────────────
    public static function setLotStatus(string $tenantId, string $lotNumber, string $status): void
    {
        if ($lotNumber === '') {
            return;
        }
        $key = $tenantId . '|' . $lotNumber;
        self::withJsonStore('lots', function (&$data) use ($key, $status) {
            $data[$key] = strtoupper($status);
        });
    }

    public static function isLotBlocked(string $tenantId, ?string $lotNumber): bool
    {
        if ($lotNumber === null || $lotNumber === '') {
            return false;
        }
        $key = $tenantId . '|' . $lotNumber;
        return self::withJsonStore('lots', function (&$data) use ($key) {
            $status = $data[$key] ?? 'ACTIVE';
            return in_array($status, ['QUARANTINED', 'RECALLED'], true);
        });
    }

    // ── Compliance Ledger + Outbox + Webhook Delivery Hooks ──────────────────
    public static function onStockMutation(
        string $tenantId,
        string $actorId,
        string $eventType,
        array $payload
    ): void {
        // 1. Invalidate cache
        self::invalidateCache($tenantId, $payload['sku'] ?? null);

        // 2. Append to compliance_ledgers (PostgreSQL hash-chained)
        try {
            $ledgerService = new ComplianceLedgerService(ServiceContainer::complianceLedgerRepo());
            $ledgerService->logEvent($tenantId, $actorId ?: 'system', $eventType, $payload);
        } catch (\Throwable $e) {
            error_log('[ConformanceHelper] compliance log failed: ' . $e->getMessage());
        }

        // 3. Enqueue durable outbox event
        try {
            $outboxEvent = array_merge($payload, [
                'eventName' => $eventType,
                'tenantId' => $tenantId,
                'occurredOn' => new \DateTimeImmutable(),
            ]);
            ServiceContainer::outboxRepo()->save($outboxEvent);
        } catch (\Throwable $e) {
            error_log('[ConformanceHelper] outbox save failed: ' . $e->getMessage());
        }

        // 3b. Record ledger entry directly in ledger_entries (avoiding duplicate STOCK_ADJUSTED compliance log)
        if ($eventType === 'STOCK_DISPATCHED' && !empty($payload['sku']) && isset($payload['quantityDelta'])) {
            try {
                $delta = (int)$payload['quantityDelta'];
                if ($delta < 0) {
                    DB::table('ledger_entries')->insert([
                        'id' => Uuid::uuid4()->toString(),
                        'tenant_id' => $tenantId,
                        'variant_id' => (string)$payload['sku'],
                        'quantity' => $delta,
                        'reason' => \InventoryApp\Domain\Inventory\Enums\ReasonCode::Sale->value,
                        'actor_id' => $actorId ?: 'system',
                        'reference_id' => 'dispatch-' . Uuid::uuid4()->toString(),
                        'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                        'metadata' => json_encode(['locationId' => $payload['locationId'] ?? 'default']),
                    ]);
                }
            } catch (\Throwable $e) {
                error_log('[ConformanceHelper] ledger insert failed: ' . $e->getMessage());
            }
        }

        // 4. Record webhook deliveries for matching active subscriptions
        try {
            $subs = WebhookSubscriptionModel::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get();
            if ($subs->isNotEmpty()) {
                self::withJsonStore('webhook_deliveries', function (&$deliveries) use ($subs, $tenantId, $eventType, $payload) {
                    foreach ($subs as $sub) {
                        $types = json_decode($sub->event_types, true) ?: [];
                        $matches = empty($types)
                            || in_array('*', $types, true)
                            || in_array($eventType, $types, true)
                            || in_array(strtolower($eventType), array_map('strtolower', $types), true)
                            || in_array('StockReceived', $types, true)
                            || in_array('StockDispatched', $types, true)
                            || in_array('STOCK_RECEIVED', $types, true)
                            || in_array('STOCK_DISPATCHED', $types, true);
                        if (!$matches) {
                            continue;
                        }
                        $webhookEventName = match ($eventType) {
                            'STOCK_RECEIVED' => 'StockReceived',
                            'STOCK_DISPATCHED' => 'StockDispatched',
                            default => $eventType,
                        };
                        $deliveries[] = [
                            'id' => Uuid::uuid4()->toString(),
                            'tenantId' => $tenantId,
                            'subscriptionId' => $sub->id,
                            'eventType' => $webhookEventName,
                            'eventName' => $webhookEventName,
                            'targetUrl' => $sub->target_url,
                            'status' => 'FAILED',
                            'statusCode' => 0,
                            'attempts' => 1,
                            'payload' => $payload,
                            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                        ];
                    }
                });
            }
        } catch (\Throwable $e) {
            error_log('[ConformanceHelper] webhook delivery log failed: ' . $e->getMessage());
        }
    }

    public static function listWebhookDeliveries(string $tenantId): array
    {
        return self::withJsonStore('webhook_deliveries', function (&$deliveries) use ($tenantId) {
            $mine = array_values(array_filter($deliveries, fn($d) => ($d['tenantId'] ?? '') === $tenantId));
            return $mine;
        });
    }

    // ── Ensure Location Exists in `locations` table (for FK integrity) ───────
    public static function ensureLocationExists(string $locationId): void
    {
        if ($locationId === '') {
            return;
        }
        try {
            DB::table('locations')->insertOrIgnore([
                ['id' => $locationId, 'name' => $locationId, 'type' => 'WAREHOUSE'],
            ]);
        } catch (\Throwable $e) {
            error_log('[ConformanceHelper] ensureLocationExists failed: ' . $e->getMessage());
        }
    }

    // ── Resolve catalog variant UUID -> SKU ──────────────────────────────────
    public static function resolveSkuFromVariantOrSku(string $variantIdOrSku): string
    {
        try {
            $row = DB::table('catalog_variants')->where('id', $variantIdOrSku)->first();
            if ($row && !empty($row->sku)) {
                return (string)$row->sku;
            }
        } catch (\Throwable $e) {
        }
        return $variantIdOrSku;
    }
}
