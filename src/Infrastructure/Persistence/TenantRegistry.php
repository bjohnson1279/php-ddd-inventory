<?php

namespace InventoryApp\Infrastructure\Persistence;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TenantRegistryEntry represents a tenant's dedicated database metadata.
 */
class TenantRegistryEntry
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $status,
        public readonly \DateTimeImmutable $provisionedAt,
        public readonly string $migratedVersion
    ) {}
}

/**
 * TenantRegistry for the PHP backend.
 * Each tenant gets its own PostgreSQL database.
 *
 * Part of Roadmap 6.1: Dynamic Multi-Database Tenant Provisioning.
 */
class TenantRegistry
{
    public function __construct(private readonly Capsule $capsule) {}

    public function registerTenant(
        string $tenantId,
        ?string $dbHost = null,
        ?int $dbPort = null,
        ?string $dbName = null,
        ?string $dbUser = null,
        ?string $dbPassword = null
    ): TenantRegistryEntry {
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $tenantId);
        $host = $dbHost ?? (getenv('DB_HOST') ?: 'db');
        $port = $dbPort ?? (int)(getenv('DB_PORT') ?: 5432);
        $name = $dbName ?? "inventory_tenant_{$safeName}";
        $user = $dbUser ?? (getenv('DB_USERNAME') ?: 'ddd_user');
        $password = $dbPassword ?? (getenv('DB_PASSWORD') ?: 'secret');

        $existing = $this->lookupTenant($tenantId);
        if ($existing && $existing->status !== 'DEPROVISIONED') {
            throw new \RuntimeException("Tenant \"{$tenantId}\" is already registered with status \"{$existing->status}\".");
        }

        $this->capsule->getConnection()->statement("
            INSERT INTO tenant_registry (tenant_id, db_host, db_port, db_name, db_user, db_password, status, provisioned_at, migrated_version)
            VALUES (?, ?, ?, ?, ?, ?, 'PROVISIONING', NOW(), '0')
            ON CONFLICT (tenant_id) DO UPDATE SET
                db_host = EXCLUDED.db_host,
                db_port = EXCLUDED.db_port,
                db_name = EXCLUDED.db_name,
                db_user = EXCLUDED.db_user,
                db_password = EXCLUDED.db_password,
                status = EXCLUDED.status,
                provisioned_at = NOW(),
                migrated_version = EXCLUDED.migrated_version
        ", [$tenantId, $host, $port, $name, $user, $password]);

        return new TenantRegistryEntry(
            $tenantId, $host, $port, $name, $user, $password,
            'PROVISIONING', new \DateTimeImmutable(), '0'
        );
    }

    public function lookupTenant(string $tenantId): ?TenantRegistryEntry
    {
        try {
            $row = $this->capsule->getConnection()->selectOne(
                "SELECT * FROM tenant_registry WHERE tenant_id = ?",
                [$tenantId]
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row) return null;

        return new TenantRegistryEntry(
            $row->tenant_id,
            $row->db_host,
            (int)$row->db_port,
            $row->db_name,
            $row->db_user,
            $row->db_password,
            $row->status,
            new \DateTimeImmutable($row->provisioned_at),
            $row->migrated_version
        );
    }

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
            $row->db_host,
            (int)$row->db_port,
            $row->db_name,
            $row->db_user,
            $row->db_password,
            $row->status,
            new \DateTimeImmutable($row->provisioned_at),
            $row->migrated_version
        ), $rows);
    }

    public function updateStatus(string $tenantId, string $status): void
    {
        $this->capsule->getConnection()->update(
            "UPDATE tenant_registry SET status = ? WHERE tenant_id = ?",
            [$status, $tenantId]
        );
    }

    public function updateMigratedVersion(string $tenantId, string $version): void
    {
        $this->capsule->getConnection()->update(
            "UPDATE tenant_registry SET migrated_version = ? WHERE tenant_id = ?",
            [$version, $tenantId]
        );
    }

    public function deprovisionTenant(string $tenantId): void
    {
        $this->updateStatus($tenantId, 'DEPROVISIONED');
    }
}
