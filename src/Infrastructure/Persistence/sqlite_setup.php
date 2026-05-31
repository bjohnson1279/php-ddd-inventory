<?php

namespace InventoryApp\Infrastructure\Persistence;

class SqliteSetup
{
    public static function createSchema($connection): void
    {
        $queries = [
            "CREATE TABLE IF NOT EXISTS tenants (
              id         VARCHAR(50) PRIMARY KEY,
              name       TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS catalog_products (
              id TEXT PRIMARY KEY,
              name TEXT NOT NULL,
              description TEXT,
              department TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS catalog_variants (
              id TEXT PRIMARY KEY,
              product_id TEXT NOT NULL,
              sku TEXT NOT NULL UNIQUE,
              attributes TEXT NOT NULL DEFAULT '{}',
              price NUMERIC NOT NULL DEFAULT 0.00,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS locations (
              id VARCHAR(50) PRIMARY KEY,
              name TEXT NOT NULL,
              type VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS products (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              sku TEXT NOT NULL,
              name TEXT NOT NULL,
              department TEXT NOT NULL,
              reorder_threshold INTEGER NOT NULL DEFAULT 10,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE(tenant_id, sku)
            )",
            "CREATE TABLE IF NOT EXISTS product_locations (
              product_id TEXT NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              stock_quantity INTEGER NOT NULL DEFAULT 0,
              open_box_quantity INTEGER NOT NULL DEFAULT 0,
              damaged_quantity INTEGER NOT NULL DEFAULT 0,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (product_id, location_id)
            )",
            "CREATE TABLE IF NOT EXISTS inventory_transactions (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              product_id TEXT NOT NULL,
              type VARCHAR(50) NOT NULL,
              quantity_change INTEGER NOT NULL,
              condition VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              reference_id TEXT
            )",
            "CREATE TABLE IF NOT EXISTS inventory_counts (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              status VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              completed_at DATETIME
            )",
            "CREATE TABLE IF NOT EXISTS inventory_count_items (
              id TEXT PRIMARY KEY,
              inventory_count_id TEXT NOT NULL,
              product_id TEXT,
              sku TEXT NOT NULL,
              counted_quantity INTEGER NOT NULL DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS ledger_entries (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              variant_id TEXT NOT NULL,
              quantity INTEGER NOT NULL,
              reason VARCHAR(50) NOT NULL,
              actor_id TEXT NOT NULL,
              reference_id TEXT,
              occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              metadata TEXT DEFAULT '{}',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS serialized_items (
              id TEXT PRIMARY KEY,
              variant_id TEXT NOT NULL,
              serial_number TEXT NOT NULL,
              tenant_id VARCHAR(50) NOT NULL,
              location_id VARCHAR(50),
              status VARCHAR(50) NOT NULL,
              history TEXT DEFAULT '[]',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE(serial_number, tenant_id)
            )",
            "CREATE TABLE IF NOT EXISTS barcodes (
              id TEXT PRIMARY KEY,
              value TEXT NOT NULL UNIQUE,
              variant_id TEXT NOT NULL,
              symbology VARCHAR(50),
              source VARCHAR(50),
              is_primary BOOLEAN DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS stock_onboardings (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              as_of_date DATE NOT NULL,
              status VARCHAR(50) NOT NULL DEFAULT 'draft',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS stock_onboarding_items (
              id TEXT PRIMARY KEY,
              onboarding_id TEXT NOT NULL,
              variant_id TEXT NOT NULL,
              quantity INTEGER NOT NULL DEFAULT 0,
              unit_cost_cents INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS journal_entries (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              entry_date DATE NOT NULL,
              description TEXT,
              reference_id TEXT,
              method VARCHAR(50),
              lines TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS product_uom_configurations (
              id TEXT PRIMARY KEY,
              variant_id TEXT NOT NULL,
              base_unit VARCHAR(50) NOT NULL,
              purchase_unit VARCHAR(50),
              sale_unit VARCHAR(50),
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS uom_conversion_rules (
              id TEXT PRIMARY KEY,
              configuration_id TEXT NOT NULL,
              unit VARCHAR(50) NOT NULL,
              factor_to_base NUMERIC NOT NULL,
              label TEXT
            )",
            "CREATE TABLE IF NOT EXISTS kits (
              id TEXT PRIMARY KEY,
              sku TEXT NOT NULL UNIQUE,
              name TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS kit_components (
              id TEXT PRIMARY KEY,
              kit_id TEXT NOT NULL,
              variant_id TEXT NOT NULL,
              quantity INTEGER NOT NULL DEFAULT 1
            )",
            "CREATE TABLE IF NOT EXISTS users (
              id            TEXT PRIMARY KEY,
              tenant_id     VARCHAR(50) NOT NULL,
              email         TEXT NOT NULL,
              password_hash TEXT NOT NULL,
              name          TEXT NOT NULL,
              active        BOOLEAN NOT NULL DEFAULT 1,
              created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE (tenant_id, email)
            )",
            "CREATE TABLE IF NOT EXISTS roles (
              id   VARCHAR(20) PRIMARY KEY,
              name TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS user_roles (
              user_id TEXT NOT NULL,
              role_id VARCHAR(20) NOT NULL,
              PRIMARY KEY (user_id, role_id)
            )",
            "CREATE TABLE IF NOT EXISTS role_permissions (
              role_id    VARCHAR(20) NOT NULL,
              permission TEXT NOT NULL,
              PRIMARY KEY (role_id, permission)
            )",
            "CREATE TABLE IF NOT EXISTS api_tokens (
              id         TEXT PRIMARY KEY,
              user_id    TEXT NOT NULL,
              tenant_id  VARCHAR(50) NOT NULL,
              token_hash TEXT NOT NULL UNIQUE,
              expires_at DATETIME NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS shopify_location_mappings (
              id                  TEXT PRIMARY KEY,
              our_location_id     VARCHAR(50) NOT NULL,
              shopify_location_id VARCHAR(50) NOT NULL UNIQUE,
              created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS shopify_sku_mappings (
              id                        TEXT PRIMARY KEY,
              sku                       TEXT NOT NULL UNIQUE,
              shopify_inventory_item_id VARCHAR(50) NOT NULL UNIQUE,
              created_at                DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS shopify_sync_failures (
              id                        TEXT PRIMARY KEY,
              tenant_id                 VARCHAR(50) NOT NULL,
              sku                       TEXT NOT NULL,
              location_id               VARCHAR(50) NOT NULL,
              quantity                  INTEGER NOT NULL,
              attempts                  INTEGER NOT NULL DEFAULT 0,
              last_error                TEXT,
              status                    VARCHAR(50) NOT NULL DEFAULT 'pending',
              created_at                DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at                DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS notifications (
              id                        TEXT PRIMARY KEY,
              tenant_id                 VARCHAR(50) NOT NULL,
              title                     TEXT NOT NULL,
              message                   TEXT NOT NULL,
              type                      VARCHAR(50) NOT NULL,
              is_read                   BOOLEAN NOT NULL DEFAULT 0,
              created_at                DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS inventory_cost_layers (
              id                        TEXT PRIMARY KEY,
              tenant_id                 VARCHAR(50) NOT NULL,
              variant_id                TEXT NOT NULL,
              original_quantity         INTEGER NOT NULL,
              remaining_quantity        INTEGER NOT NULL,
              unit_cost_cents           INTEGER NOT NULL,
              purchase_order_id         VARCHAR(50),
              received_at               DATETIME DEFAULT CURRENT_TIMESTAMP,
              serial_number             VARCHAR(100)
            )",
            "CREATE TABLE IF NOT EXISTS queued_jobs (
              id            VARCHAR(50) PRIMARY KEY,
              tenant_id     VARCHAR(50) NOT NULL,
              listener_class VARCHAR(255) NOT NULL,
              event_data    TEXT NOT NULL,
              attempts      INTEGER NOT NULL DEFAULT 0,
              reserved_at   DATETIME DEFAULT NULL,
              available_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS quickbooks_journal_mappings (
              id                   TEXT PRIMARY KEY,
              journal_entry_id     TEXT NOT NULL UNIQUE,
              quickbooks_journal_id VARCHAR(50) NOT NULL UNIQUE,
              created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id)
            )"
        ];

        foreach ($queries as $q) {
            $connection->statement($q);
        }
    }
}
