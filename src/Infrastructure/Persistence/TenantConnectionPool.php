<?php

namespace InventoryApp\Infrastructure\Persistence;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TenantConnectionPool for the PHP backend.
 *
 * Manages a cache of Eloquent database connections keyed by tenant ID.
 * Each tenant gets a separate Capsule connection pointing at its
 * isolated PostgreSQL schema.
 *
 * Part of Roadmap 6.1: Dynamic Multi-Database Tenant Provisioning.
 */
class TenantConnectionPool
{
    /** @var array<string, array{capsule: Capsule, lastAccessed: int}> */
    private array $cache = [];

    public function __construct(
        private readonly TenantRegistry $registry,
        private readonly int $maxSize = 50,
        private readonly int $maxIdleSeconds = 300
    ) {}

    /**
     * Get an Eloquent connection for the given tenant.
     */
    public function getConnection(string $tenantId): \Illuminate\Database\Connection
    {
        // Cache hit
        if (isset($this->cache[$tenantId])) {
            $this->cache[$tenantId]['lastAccessed'] = time();
            return $this->cache[$tenantId]['capsule']->getConnection('tenant');
        }

        // Cache miss — look up tenant
        $entry = $this->registry->lookupTenant($tenantId);
        if (!$entry) {
            throw new \RuntimeException("Tenant \"{$tenantId}\" not found in registry.");
        }
        if ($entry->status !== 'ACTIVE') {
            throw new \RuntimeException("Tenant \"{$tenantId}\" is not active (status: \"{$entry->status}\").");
        }

        // Evict if at capacity
        if (count($this->cache) >= $this->maxSize) {
            $this->evictLRU();
        }

        // Create new connection
        $capsule = $this->createConnection($entry);
        $this->cache[$tenantId] = [
            'capsule' => $capsule,
            'lastAccessed' => time(),
        ];

        return $capsule->getConnection('tenant');
    }

    /**
     * Check if a tenant has a cached connection.
     */
    public function has(string $tenantId): bool
    {
        return isset($this->cache[$tenantId]);
    }

    /**
     * Evict a specific tenant.
     */
    public function evict(string $tenantId): void
    {
        if (isset($this->cache[$tenantId])) {
            $this->cache[$tenantId]['capsule']->getConnection('tenant')->disconnect();
            unset($this->cache[$tenantId]);
        }
    }

    /**
     * Evict all idle connections.
     */
    public function evictIdle(): int
    {
        $now = time();
        $evicted = 0;

        foreach ($this->cache as $tenantId => $entry) {
            if (($now - $entry['lastAccessed']) > $this->maxIdleSeconds) {
                $this->evict($tenantId);
                $evicted++;
            }
        }

        return $evicted;
    }

    /**
     * Get pool statistics.
     */
    public function getStats(): array
    {
        return [
            'size' => count($this->cache),
            'maxSize' => $this->maxSize,
            'tenantIds' => array_keys($this->cache),
        ];
    }

    /**
     * Shut down all connections.
     */
    public function shutdown(): void
    {
        foreach (array_keys($this->cache) as $tenantId) {
            $this->evict($tenantId);
        }
    }

    // ──────────────────────────────────────────────

    private function createConnection(TenantRegistryEntry $entry): Capsule
    {
        $capsule = new Capsule();

        $capsule->addConnection([
            'driver' => 'pgsql',
            'host' => $entry->dbHost,
            'port' => $entry->dbPort,
            'database' => $entry->dbName,
            'username' => getenv('DB_USERNAME') ?: 'ddd_user',
            'password' => getenv('DB_PASSWORD') ?: 'secret',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => $entry->schemaName,
        ], 'tenant');

        return $capsule;
    }

    private function evictLRU(): void
    {
        $oldest = null;
        $oldestKey = null;

        foreach ($this->cache as $tenantId => $entry) {
            if ($oldest === null || $entry['lastAccessed'] < $oldest['lastAccessed']) {
                $oldest = $entry;
                $oldestKey = $tenantId;
            }
        }

        if ($oldestKey !== null) {
            $this->evict($oldestKey);
        }
    }
}
