<?php

namespace InventoryApp\Infrastructure\Persistence;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TenantRegistryEntry represents a tenant's database schema metadata.
 */
class TenantRegistryEntry
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $schemaName,
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbName,
        public readonly string $status,
        public readonly \DateTimeImmutable $provisionedAt,
        public readonly string $migratedVersion
    ) {}
}

/**
 * TenantRegistry manages the mapping between tenant IDs and their
 * isolated database schemas in the PHP backend.
 *
 * Part of Roadmap 6.1: Dynamic Multi-Database Tenant Provisioning.
 */
class TenantRegistry
{
    public function __construct(private readonly Capsule $capsule) {}

    /**
     * Register a new tenant in the control-plane registry.
     */
    public function registerTenant(
        string $tenantId,
        ?string $dbHost = null,
        ?int $dbPort = null,
        ?string $dbName = null
    ): TenantRegistryEntry {
        $schemaName = 'tenant_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $tenantId);
        $host = $dbHost ?? (getenv('DB_HOST') ?: 'db');
        $port = $dbPort ?? (int)(getenv('DB_PORT') ?: 5432);
        $name = $dbName ?? (getenv('DB_DATABASE') ?: 'ddd_inventory');

        $existing = $this->lookupTenant($tenantId);
        if ($existing && $existing->status !== 'DEPROVISIONED') {
            throw new \RuntimeException("Tenant \"{$tenantId}\" is already registered with status \"{$existing->status}\".");
        }

        $this->capsule->getConnection()->statement("
            INSERT INTO tenant_registry (tenant_id, schema_name, db_host, db_port, db_name, status, provisioned_at, migrated_version)
            VALUES (?, ?, ?, ?, ?, 'PROVISIONING', NOW(), '0')
            ON CONFLICT (tenant_id) DO UPDATE SET
                schema_name = EXCLUDED.schema_name,
                db_host = EXCLUDED.db_host,
                db_port = EXCLUDED.db_port,
                db_name = EXCLUDED.db_name,
                status = EXCLUDED.status,
                provisioned_at = NOW(),
                migrated_version = EXCLUDED.migrated_version
        ", [$tenantId, $schemaName, $host, $port, $name]);

        return new TenantRegistryEntry(
            $tenantId, $schemaName, $host, $port, $name,
            'PROVISIONING', new \DateTimeImmutable(), '0'
        );
    }

    /**
     * Look up a tenant by ID.
     */
    public function lookupTenant(string $tenantId): ?TenantRegistryEntry
    {
        $row = $this->capsule->getConnection()->selectOne(
            "SELECT * FROM tenant_registry WHERE tenant_id = ?",
            [$tenantId]
        );

        if (!$row) return null;

        return new TenantRegistryEntry(
            $row->tenant_id,
            $row->schema_name,
            $row->db_host,
            (int)$row->db_port,
            $row->db_name,
            $row->status,
            new \DateTimeImmutable($row->provisioned_at),
            $row->migrated_version
        );
    }

    /**
     * List all tenants, optionally filtered by status.
     */
    public function listTenants(?string $status = null): array
    {
        $query = "SELECT * FROM tenant_registry";
        $params = [];

        if ($status !== null) {
            $query .= " WHERE status = ?";
            $params[] = $status;
        }

        $query .= " ORDER BY provisioned_at DESC";

        $rows = $this->capsule->getConnection()->select($query, $params);

        return array_map(fn($row) => new TenantRegistryEntry(
            $row->tenant_id,
            $row->schema_name,
            $row->db_host,
            (int)$row->db_port,
            $row->db_name,
            $row->status,
            new \DateTimeImmutable($row->provisioned_at),
            $row->migrated_version
        ), $rows);
    }

    /**
     * Update tenant status.
     */
    public function updateStatus(string $tenantId, string $status): void
    {
        $this->capsule->getConnection()->update(
            "UPDATE tenant_registry SET status = ? WHERE tenant_id = ?",
            [$status, $tenantId]
        );
    }

    /**
     * Update migrated version.
     */
    public function updateMigratedVersion(string $tenantId, string $version): void
    {
        $this->capsule->getConnection()->update(
            "UPDATE tenant_registry SET migrated_version = ? WHERE tenant_id = ?",
            [$version, $tenantId]
        );
    }

    /**
     * Mark tenant as deprovisioned.
     */
    public function deprovisionTenant(string $tenantId): void
    {
        $this->updateStatus($tenantId, 'DEPROVISIONED');
    }
}
