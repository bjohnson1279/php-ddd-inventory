<?php

namespace InventoryApp\Infrastructure\Persistence;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TenantProvisioner for the PHP backend.
 *
 * Provisions isolated PostgreSQL databases per tenant, running DDL
 * migrations and seeding default data.
 *
 * Part of Roadmap 6.1: Dynamic Multi-Database Tenant Provisioning.
 */
class TenantProvisioner
{
    public function __construct(
        private readonly Capsule $capsule,
        private readonly TenantRegistry $registry
    ) {}

    /**
     * Provision a new tenant: create database, run migrations, seed data.
     */
    public function provisionTenant(string $tenantId): string
    {
        $entry = $this->registry->registerTenant($tenantId);
        $dbName = $entry->dbName;

        try {
            // Create the tenant's dedicated database.
            // Since CREATE DATABASE cannot run inside a transaction, we run it on the control connection.
            $this->capsule->getConnection()->statement("CREATE DATABASE \"{$dbName}\"");

            // Connect to the new database and run migrations
            $this->runMigrationsOnTenantDb($entry);

            // Seed defaults
            $this->seedDefaults($entry, $tenantId);

            // Mark active
            $this->registry->updateStatus($tenantId, 'ACTIVE');
            $this->registry->updateMigratedVersion($tenantId, '1');

            return $dbName;

        } catch (\Throwable $e) {
            // Cleanup on failure
            try {
                $this->capsule->getConnection()->statement("DROP DATABASE IF EXISTS \"{$dbName}\"");
            } catch (\Throwable $_) {}

            $this->registry->updateStatus($tenantId, 'DEPROVISIONED');
            throw $e;
        }
    }

    /**
     * Deprovision a tenant: drop database and mark as deprovisioned.
     */
    public function deprovisionTenant(string $tenantId): void
    {
        $entry = $this->registry->lookupTenant($tenantId);
        if (!$entry) {
            throw new \RuntimeException("Tenant \"{$tenantId}\" not found in registry.");
        }

        // Terminate active connections to the tenant database
        try {
            $this->capsule->getConnection()->statement("
                SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = '{$entry->dbName}'
                  AND pid <> pg_backend_pid()
            ");
        } catch (\Throwable $_) {}

        $this->capsule->getConnection()->statement("DROP DATABASE IF EXISTS \"{$entry->dbName}\"");
        $this->registry->deprovisionTenant($tenantId);
    }

    // ──────────────────────────────────────────────

    /**
     * Create database connection for a tenant's database.
     * Can be overridden or mocked in test suites to avoid hitting network DB.
     */
    protected function getTenantConnection(TenantRegistryEntry $entry): \Illuminate\Database\Connection
    {
        $tenantCapsule = new Capsule();
        $tenantCapsule->addConnection([
            'driver' => 'pgsql',
            'host' => $entry->dbHost,
            'port' => $entry->dbPort,
            'database' => $entry->dbName,
            'username' => $entry->dbUser,
            'password' => $entry->dbPassword,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
        return $tenantCapsule->getConnection();
    }

    private function runMigrationsOnTenantDb(TenantRegistryEntry $entry): void
    {
        $conn = $this->getTenantConnection($entry);

        try {
            // Core tables — no schema prefix needed (each tenant has own database)
            $conn->statement("
                CREATE TABLE IF NOT EXISTS inventory_items (
                    id TEXT PRIMARY KEY,
                    sku TEXT NOT NULL,
                    location_id TEXT NOT NULL,
                    quantity INTEGER NOT NULL,
                    allocated INTEGER NOT NULL DEFAULT 0,
                    in_transit INTEGER NOT NULL DEFAULT 0,
                    version INTEGER NOT NULL,
                    UNIQUE(sku, location_id)
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS products (
                    id UUID PRIMARY KEY,
                    name TEXT NOT NULL,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS product_variants (
                    id UUID PRIMARY KEY,
                    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                    sku TEXT NOT NULL UNIQUE,
                    tracking_mode TEXT NOT NULL DEFAULT 'quantity',
                    costing_method TEXT NOT NULL DEFAULT 'fifo',
                    weight_grams INTEGER,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS ledger_entries (
                    id UUID NOT NULL,
                    tenant_id TEXT NOT NULL,
                    location_id TEXT NOT NULL,
                    variant_id UUID NOT NULL,
                    quantity INTEGER NOT NULL,
                    reason TEXT NOT NULL,
                    actor_id TEXT NOT NULL,
                    occurred_at TIMESTAMPTZ NOT NULL,
                    reference_id TEXT,
                    metadata JSONB,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    PRIMARY KEY (id, occurred_at)
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS kits (
                    id UUID PRIMARY KEY,
                    sku TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS kit_components (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    kit_id UUID NOT NULL REFERENCES kits(id) ON DELETE CASCADE,
                    variant_id UUID NOT NULL,
                    quantity INTEGER NOT NULL,
                    UNIQUE(kit_id, variant_id)
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS warehouse_locations (
                    id TEXT PRIMARY KEY,
                    warehouse_id TEXT NOT NULL,
                    zone TEXT NOT NULL,
                    aisle TEXT NOT NULL,
                    rack TEXT NOT NULL,
                    shelf TEXT NOT NULL,
                    bin TEXT NOT NULL,
                    max_weight_grams INTEGER NOT NULL,
                    max_volume_cubic_meters DOUBLE PRECISION NOT NULL,
                    grid_x INTEGER NOT NULL DEFAULT 0,
                    grid_y INTEGER NOT NULL DEFAULT 0,
                    width INTEGER NOT NULL DEFAULT 1,
                    height INTEGER NOT NULL DEFAULT 1,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    UNIQUE(warehouse_id, zone, aisle, rack, shelf, bin)
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS outbox_events (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    event_type TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'Pending',
                    attempts INTEGER NOT NULL DEFAULT 0,
                    last_error TEXT,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    processed_at TIMESTAMPTZ,
                    next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
            ");

        } finally {
            $conn->disconnect();
        }
    }

    private function seedDefaults(TenantRegistryEntry $entry, string $tenantId): void
    {
        // Seed is intentionally minimal — additional data added via application setup flows
    }
}
