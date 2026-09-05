<?php

namespace InventoryApp\Infrastructure\Persistence;

class SqliteSetup
{
    public static function createSchema($connection): void
    {
        $queries = array_merge(
            self::getIdentityQueries(),
            self::getCatalogQueries(),
            self::getLocationQueries(),
            self::getInventoryQueries(),
            self::getAccountingQueries(),
            self::getIntegrationQueries(),
            self::getSystemQueries(),
            self::getReturnsQueries(),
            self::getForecastingQueries(),
            self::getShippingQueries(),
            self::getComplianceQueries(),
            self::getRfidQueries(),
            self::getLogisticsErpQueries(),
            self::getReportingQueries(),
            self::getApprovalQueries()
        );

        foreach ($queries as $q) {
            $connection->statement($q);
        }
    }

    private static function getIdentityQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS tenants (
              id         VARCHAR(50) PRIMARY KEY,
              name       TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
            )"
        ];
    }

    private static function getCatalogQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS catalog_products (
              id TEXT PRIMARY KEY,
              name TEXT NOT NULL,
              description TEXT,
              department TEXT NOT NULL,
              tenant_id VARCHAR(50),
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
            "CREATE TABLE IF NOT EXISTS products (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              sku TEXT NOT NULL,
              name TEXT NOT NULL,
              department TEXT NOT NULL,
              reorder_threshold INTEGER NOT NULL DEFAULT 10,
              version_id INTEGER NOT NULL DEFAULT 1,
              weight_grams INTEGER,
              volume_cubic_meters NUMERIC,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE(tenant_id, sku)
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
            "CREATE TABLE IF NOT EXISTS barcodes (
              id TEXT PRIMARY KEY,
              value TEXT NOT NULL UNIQUE,
              variant_id TEXT NOT NULL,
              symbology VARCHAR(50),
              source VARCHAR(50),
              is_primary BOOLEAN DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];
    }

    private static function getLocationQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS locations (
              id VARCHAR(50) PRIMARY KEY,
              name TEXT NOT NULL,
              type VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS product_locations (
              product_id TEXT NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              stock_quantity INTEGER NOT NULL DEFAULT 0,
              open_box_quantity INTEGER NOT NULL DEFAULT 0,
              damaged_quantity INTEGER NOT NULL DEFAULT 0,
              allocated_quantity INTEGER NOT NULL DEFAULT 0,
              in_transit_quantity INTEGER NOT NULL DEFAULT 0,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (product_id, location_id)
            )",
            "CREATE TABLE IF NOT EXISTS warehouse_locations (
              id VARCHAR(50) PRIMARY KEY,
              warehouse_id VARCHAR(50) NOT NULL,
              zone VARCHAR(50) NOT NULL,
              aisle VARCHAR(50) NOT NULL,
              rack VARCHAR(50) NOT NULL,
              shelf VARCHAR(50) NOT NULL,
              bin VARCHAR(50) NOT NULL,
              max_weight_grams INTEGER NOT NULL,
              max_volume_cubic_meters NUMERIC NOT NULL,
              grid_x INTEGER NOT NULL DEFAULT 0,
              grid_y INTEGER NOT NULL DEFAULT 0,
              width INTEGER NOT NULL DEFAULT 1,
              height INTEGER NOT NULL DEFAULT 1,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE(warehouse_id, zone, aisle, rack, shelf, bin)
            )",
            "CREATE TABLE IF NOT EXISTS purchase_orders (
              id VARCHAR(50) PRIMARY KEY,
              purchase_order_number VARCHAR(100) NOT NULL UNIQUE,
              vendor_id VARCHAR(50) NOT NULL,
              tenant_id VARCHAR(50) NOT NULL,
              status VARCHAR(50) NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS purchase_order_items (
              id VARCHAR(50) PRIMARY KEY,
              purchase_order_id VARCHAR(50) NOT NULL,
              variant_id VARCHAR(50) NOT NULL,
              quantity INTEGER NOT NULL,
              received_quantity INTEGER NOT NULL DEFAULT 0,
              unit_cost_cents INTEGER NOT NULL,
              FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS reorder_policies (
              id VARCHAR(50) PRIMARY KEY,
              sku VARCHAR(50) NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              reorder_point INTEGER NOT NULL,
              reorder_quantity INTEGER NOT NULL,
              safety_stock INTEGER NOT NULL,
              dynamic_rop_enabled BOOLEAN NOT NULL DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE(sku, location_id)
            )"
        ];
    }

    private static function getInventoryQueries(): array
    {
        return [
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
              location_id VARCHAR(50) NOT NULL,
              counted_quantity INTEGER NOT NULL DEFAULT 0,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE(inventory_count_id, sku, location_id)
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
            "CREATE TABLE IF NOT EXISTS inventory_cost_layers (
              id                        TEXT PRIMARY KEY,
              tenant_id                 VARCHAR(50) NOT NULL,
              variant_id                TEXT NOT NULL,
              original_quantity         INTEGER NOT NULL,
              remaining_quantity        INTEGER NOT NULL,
              unit_cost_cents           INTEGER NOT NULL,
              purchase_order_id         VARCHAR(50),
              received_at               DATETIME DEFAULT CURRENT_TIMESTAMP,
              serial_number             VARCHAR(100),
              lot_number                VARCHAR(100),
              expiration_date           DATETIME
            )"
        ];
    }

    private static function getAccountingQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS journal_entries (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              entry_date DATE NOT NULL,
              description TEXT,
              reference_id TEXT,
              method VARCHAR(50),
              lines TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];
    }

    private static function getIntegrationQueries(): array
    {
        return [
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
            "CREATE TABLE IF NOT EXISTS quickbooks_journal_mappings (
              id                   TEXT PRIMARY KEY,
              journal_entry_id     TEXT NOT NULL UNIQUE,
              quickbooks_journal_id VARCHAR(50) NOT NULL UNIQUE,
              created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id)
            )",
            "CREATE TABLE IF NOT EXISTS xero_journal_mappings (
              id                   TEXT PRIMARY KEY,
              journal_entry_id     TEXT NOT NULL UNIQUE,
              xero_journal_id      VARCHAR(50) NOT NULL UNIQUE,
              created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id)
            )",
            "CREATE TABLE IF NOT EXISTS netsuite_journal_mappings (
              id                  TEXT PRIMARY KEY,
              journal_entry_id    TEXT NOT NULL UNIQUE,
              netsuite_journal_id VARCHAR(50) NOT NULL UNIQUE,
              created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id)
            )"
        ];
    }

    private static function getSystemQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS notifications (
              id                        TEXT PRIMARY KEY,
              tenant_id                 VARCHAR(50) NOT NULL,
              title                     TEXT NOT NULL,
              message                   TEXT NOT NULL,
              type                      VARCHAR(50) NOT NULL,
              is_read                   BOOLEAN NOT NULL DEFAULT 0,
              created_at                DATETIME DEFAULT CURRENT_TIMESTAMP
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
            "CREATE TABLE IF NOT EXISTS rfid_tags (
              epc           TEXT PRIMARY KEY,
              sku           TEXT NOT NULL,
              serial_number TEXT NOT NULL,
              status        TEXT NOT NULL DEFAULT 'ACTIVE',
              last_seen_at  DATETIME,
              last_location TEXT,
              created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];
    }

    private static function getReturnsQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS rmas (
              id TEXT PRIMARY KEY,
              rma_number TEXT NOT NULL UNIQUE,
              tenant_id VARCHAR(50) NOT NULL,
              customer_id TEXT NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              status VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS rma_items (
              id TEXT PRIMARY KEY,
              rma_id TEXT NOT NULL,
              variant_id TEXT NOT NULL,
              quantity INTEGER NOT NULL,
              received_quantity INTEGER NOT NULL DEFAULT 0,
              unit_cost_cents INTEGER NOT NULL,
              status VARCHAR(50) NOT NULL,
              disposition VARCHAR(50) DEFAULT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (rma_id) REFERENCES rmas (id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS quarantine_items (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              variant_id TEXT NOT NULL,
              quantity INTEGER NOT NULL,
              reason TEXT NOT NULL,
              status VARCHAR(50) NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              resolved_at DATETIME DEFAULT NULL
            )"
        ];
    }

    private static function getForecastingQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS demand_forecasts (
              id TEXT PRIMARY KEY,
              sku TEXT NOT NULL,
              location_id VARCHAR(50) NOT NULL,
              forecasted_quantity INTEGER NOT NULL,
              period_start DATETIME NOT NULL,
              period_end DATETIME NOT NULL,
              confidence_level NUMERIC NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              UNIQUE (sku, location_id, period_start, period_end)
            )"
        ];
    }

    private static function getShippingQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS shipments (
              id VARCHAR(50) PRIMARY KEY,
              sku TEXT NOT NULL,
              quantity INTEGER NOT NULL,
              destination_address TEXT NOT NULL,
              carrier VARCHAR(50) NOT NULL,
              tracking_number VARCHAR(100),
              label_url TEXT,
              shipping_rate_cents INTEGER NOT NULL,
              status VARCHAR(50) NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS outbox_events (
              id VARCHAR(50) PRIMARY KEY,
              event_name VARCHAR(255) NOT NULL,
              payload TEXT NOT NULL,
              occurred_on DATETIME NOT NULL,
              processed_at DATETIME DEFAULT NULL,
              attempts INTEGER NOT NULL DEFAULT 0,
              last_error TEXT DEFAULT NULL,
              next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS audit_discrepancies (
              id VARCHAR(255) PRIMARY KEY,
              tenant_id VARCHAR(255) NOT NULL,
              type VARCHAR(255) NOT NULL,
              reference_id VARCHAR(255) NOT NULL,
              external_ref_id VARCHAR(255),
              description TEXT NOT NULL,
              status VARCHAR(50) DEFAULT 'OPEN',
              occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              resolved_at DATETIME,
              resolution_notes TEXT
            )"
        ];
    }

    private static function getComplianceQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS compliance_ledgers (
              id VARCHAR(50) PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              actor_id VARCHAR(50) NOT NULL,
              event_type VARCHAR(100) NOT NULL,
              sequence_number INTEGER NOT NULL,
              previous_hash VARCHAR(64) NOT NULL,
              current_hash VARCHAR(64) NOT NULL,
              signature VARCHAR(64) NOT NULL,
              payload TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS webhook_subscriptions (
              id VARCHAR(255) PRIMARY KEY,
              tenant_id VARCHAR(255) NOT NULL,
              target_url VARCHAR(500) NOT NULL,
              secret VARCHAR(255) NOT NULL,
              event_types TEXT NOT NULL,
              is_active BOOLEAN NOT NULL DEFAULT 1,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS webhook_deliveries (
              id VARCHAR(255) PRIMARY KEY,
              tenant_id VARCHAR(255) NOT NULL,
              subscription_id VARCHAR(255) NOT NULL,
              event_type VARCHAR(255) NOT NULL,
              payload TEXT NOT NULL,
              status VARCHAR(50) NOT NULL DEFAULT 'Pending',
              attempts INTEGER NOT NULL DEFAULT 0,
              last_error TEXT,
              next_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              processed_at DATETIME,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];
    }

    private static function getRfidQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS rfid_tags (
              epc VARCHAR(100) PRIMARY KEY,
              sku VARCHAR(100) NOT NULL,
              serial_number VARCHAR(100) NOT NULL,
              status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
              last_seen_at DATETIME,
              last_location VARCHAR(50),
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];
    }

    private static function getLogisticsErpQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS bills_of_lading (
              id TEXT PRIMARY KEY,
              bol_number TEXT NOT NULL UNIQUE,
              carrier TEXT NOT NULL,
              origin_address TEXT NOT NULL,
              destination_address TEXT NOT NULL,
              weight_kg NUMERIC NOT NULL,
              total_packages INTEGER NOT NULL,
              status TEXT NOT NULL DEFAULT 'GENERATED',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS return_merchandise_authorizations (
              id TEXT PRIMARY KEY,
              rma_number TEXT NOT NULL UNIQUE,
              order_id TEXT NOT NULL,
              customer_id TEXT NOT NULL,
              status TEXT NOT NULL DEFAULT 'PENDING_INSPECTION',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS rma_items (
              id TEXT PRIMARY KEY,
              rma_id TEXT NOT NULL,
              sku TEXT NOT NULL,
              quantity INTEGER NOT NULL,
              reason TEXT NOT NULL,
              disposition TEXT NOT NULL DEFAULT 'PENDING',
              inspected_at DATETIME
            )",
            "CREATE TABLE IF NOT EXISTS supplier_asns (
              id TEXT PRIMARY KEY,
              asn_number TEXT NOT NULL UNIQUE,
              supplier_id TEXT NOT NULL,
              expected_delivery DATETIME NOT NULL,
              actual_delivery DATETIME,
              status TEXT NOT NULL DEFAULT 'IN_TRANSIT',
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS supplier_scorecards (
              id TEXT PRIMARY KEY,
              supplier_id TEXT NOT NULL,
              on_time_rate NUMERIC NOT NULL,
              in_full_rate NUMERIC NOT NULL,
              defect_rate NUMERIC NOT NULL,
              otif_score NUMERIC NOT NULL,
              evaluated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS esg_emissions_records (
              id TEXT PRIMARY KEY,
              tenant_id TEXT NOT NULL,
              transport_mode TEXT NOT NULL,
              distance_km NUMERIC NOT NULL,
              weight_kg NUMERIC NOT NULL,
              ton_km NUMERIC NOT NULL,
              co2e_kg NUMERIC NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];
    }

    private static function getReportingQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS report_definitions (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              name TEXT NOT NULL,
              description TEXT,
              type VARCHAR(50) NOT NULL,
              filters TEXT NOT NULL,
              grouping TEXT NOT NULL,
              created_by TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS report_schedules (
              id TEXT PRIMARY KEY,
              report_definition_id TEXT NOT NULL,
              cron_expression VARCHAR(100) NOT NULL,
              delivery_method VARCHAR(50) NOT NULL,
              next_run_at DATETIME NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (report_definition_id) REFERENCES report_definitions(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS report_executions (
              id TEXT PRIMARY KEY,
              report_definition_id TEXT NOT NULL,
              status VARCHAR(50) NOT NULL,
              format VARCHAR(20) NOT NULL,
              file_url TEXT,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (report_definition_id) REFERENCES report_definitions(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS shared_report_links (
              id TEXT PRIMARY KEY,
              report_execution_id TEXT NOT NULL,
              token VARCHAR(255) NOT NULL UNIQUE,
              expires_at DATETIME NOT NULL,
              viewer_permissions TEXT,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (report_execution_id) REFERENCES report_executions(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS dashboard_widgets (
              id TEXT PRIMARY KEY,
              tenant_id VARCHAR(50) NOT NULL,
              type VARCHAR(50) NOT NULL,
              config TEXT NOT NULL,
              layout_x INTEGER NOT NULL DEFAULT 0,
              layout_y INTEGER NOT NULL DEFAULT 0,
              width INTEGER NOT NULL DEFAULT 1,
              height INTEGER NOT NULL DEFAULT 1
            )"
        ];
    }


    private static function getApprovalQueries(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS approval_workflows (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                name VARCHAR(255) NOT NULL,
                trigger_event VARCHAR(100) NOT NULL,
                config TEXT NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(tenant_id, trigger_event)
            )",
            "CREATE TABLE IF NOT EXISTS approval_requests (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                workflow_id TEXT NOT NULL,
                reference_type VARCHAR(100) NOT NULL,
                reference_id VARCHAR(255) NOT NULL,
                requester_id TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'PENDING',
                current_step INT DEFAULT 0,
                payload TEXT NOT NULL,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id)
            )",
            "CREATE TABLE IF NOT EXISTS approval_decisions (
                id TEXT PRIMARY KEY,
                request_id TEXT NOT NULL,
                step_index INT NOT NULL,
                decider_id TEXT NOT NULL,
                decision VARCHAR(20) NOT NULL,
                notes TEXT,
                decided_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (request_id) REFERENCES approval_requests(id)
            )"
        ];
    }
}
