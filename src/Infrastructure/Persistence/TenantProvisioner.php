<?php

namespace InventoryApp\Infrastructure\Persistence;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TenantProvisioner for the PHP backend.
 *
 * Provisions isolated PostgreSQL schemas per tenant, running DDL migrations
 * and seeding default data using the Eloquent schema builder.
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
     * Provision a new tenant: create schema, run migrations, seed data.
     */
    public function provisionTenant(string $tenantId): string
    {
        $entry = $this->registry->registerTenant($tenantId);
        $schemaName = $entry->schemaName;
        $conn = $this->capsule->getConnection();

        try {
            // Create schema
            $conn->statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");

            // Run migrations
            $this->runMigrations($conn, $schemaName);

            // Seed defaults
            $this->seedDefaults($conn, $schemaName, $tenantId);

            // Mark active
            $this->registry->updateStatus($tenantId, 'ACTIVE');
            $this->registry->updateMigratedVersion($tenantId, '1');

            return $schemaName;

        } catch (\Throwable $e) {
            // Cleanup on failure
            try {
                $conn->statement("DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE");
            } catch (\Throwable $_) {}

            $this->registry->updateStatus($tenantId, 'DEPROVISIONED');
            throw $e;
        }
    }

    /**
     * Deprovision a tenant: drop schema and mark as deprovisioned.
     */
    public function deprovisionTenant(string $tenantId): void
    {
        $entry = $this->registry->lookupTenant($tenantId);
        if (!$entry) {
            throw new \RuntimeException("Tenant \"{$tenantId}\" not found in registry.");
        }

        $this->capsule->getConnection()->statement(
            "DROP SCHEMA IF EXISTS \"{$entry->schemaName}\" CASCADE"
        );

        $this->registry->deprovisionTenant($tenantId);
    }

    // ──────────────────────────────────────────────

    private function runMigrations(\Illuminate\Database\Connection $conn, string $schemaName): void
    {
        $conn->statement("SET search_path TO \"{$schemaName}\"");

        try {
            $conn->statement("
                CREATE TABLE IF NOT EXISTS \"{$schemaName}\".inventory_items (
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
                CREATE TABLE IF NOT EXISTS \"{$schemaName}\".products (
                    id UUID PRIMARY KEY,
                    name TEXT NOT NULL,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS \"{$schemaName}\".product_variants (
                    id UUID PRIMARY KEY,
                    product_id UUID NOT NULL REFERENCES \"{$schemaName}\".products(id) ON DELETE CASCADE,
                    sku TEXT NOT NULL UNIQUE,
                    tracking_mode TEXT NOT NULL DEFAULT 'quantity',
                    costing_method TEXT NOT NULL DEFAULT 'fifo',
                    weight_grams INTEGER,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS \"{$schemaName}\".ledger_entries (
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
                CREATE TABLE IF NOT EXISTS \"{$schemaName}\".kits (
                    id UUID PRIMARY KEY,
                    sku TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS \"{$schemaName}\".kit_components (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    kit_id UUID NOT NULL REFERENCES \"{$schemaName}\".kits(id) ON DELETE CASCADE,
                    variant_id UUID NOT NULL,
                    quantity INTEGER NOT NULL,
                    UNIQUE(kit_id, variant_id)
                )
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS \"{$schemaName}\".warehouse_locations (
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

        } finally {
            $conn->statement("SET search_path TO \"public\"");
        }
    }

    private function seedDefaults(\Illuminate\Database\Connection $conn, string $schemaName, string $tenantId): void
    {
        // Seed is intentionally minimal — additional data added via application setup flows
    }
}
