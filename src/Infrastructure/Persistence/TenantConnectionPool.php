<?php

namespace InventoryApp\Infrastructure\Persistence;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TenantConnectionPool for the PHP backend.
 *
 * Manages a cache of Eloquent database connections keyed by tenant ID.
 * On cache miss, adds a database connection dynamically to the global
 * Capsule manager pointing to the tenant's dedicated database.
 *
 * Part of Roadmap 6.1: Dynamic Multi-Database Tenant Provisioning.
 */
class TenantConnectionPool
{
    /** @var array<string, array{connectionName: string, lastAccessed: int}> */
    private array $cache = [];

    public function __construct(
        private readonly Capsule $capsule,
        private readonly TenantRegistry $registry,
        private readonly int $maxSize = 50,
        private readonly int $maxIdleSeconds = 300
    ) {}

    /**
     * Get an Eloquent connection for the given tenant's database.
     */
    public function getConnection(string $tenantId): \Illuminate\Database\Connection
    {
        $connectionName = 'tenant_' . $tenantId;

        if (isset($this->cache[$tenantId])) {
            $this->cache[$tenantId]['lastAccessed'] = time();
            return $this->capsule->getConnection($connectionName);
        }

        $entry = $this->registry->lookupTenant($tenantId);
        if (!$entry) {
            throw new \RuntimeException("Tenant \"{$tenantId}\" not found in registry.");
        }
        if ($entry->status !== 'ACTIVE') {
            throw new \RuntimeException("Tenant \"{$tenantId}\" is not active (status: \"{$entry->status}\").");
        }

        if (count($this->cache) >= $this->maxSize) {
            $this->evictLRU();
        }

        $this->createConnection($entry, $connectionName);
        $this->cache[$tenantId] = [
            'connectionName' => $connectionName,
            'lastAccessed' => time(),
        ];

        return $this->capsule->getConnection($connectionName);
    }

    public function has(string $tenantId): bool
    {
        return isset($this->cache[$tenantId]);
    }

    public function evict(string $tenantId): void
    {
        if (isset($this->cache[$tenantId])) {
            $connectionName = 'tenant_' . $tenantId;
            try {
                $this->capsule->getConnection($connectionName)->disconnect();
            } catch (\Throwable $_) {}
            unset($this->cache[$tenantId]);
        }
    }

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

    public function getStats(): array
    {
        return [
            'size' => count($this->cache),
            'maxSize' => $this->maxSize,
            'tenantIds' => array_keys($this->cache),
        ];
    }

    public function shutdown(): void
    {
        foreach (array_keys($this->cache) as $tenantId) {
            $this->evict($tenantId);
        }
    }

    private function createConnection(TenantRegistryEntry $entry, string $connectionName): void
    {
        // Add the connection dynamically using the Capsule instance
        $this->capsule->addConnection([
            'driver' => 'pgsql',
            'host' => $entry->dbHost,
            'port' => $entry->dbPort,
            'database' => $entry->dbName,
            'username' => $entry->dbUser,
            'password' => $entry->dbPassword,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ], $connectionName);
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
