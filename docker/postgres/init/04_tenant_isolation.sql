-- Tenant isolation migration
-- Adds tenant_id to the core inventory tables that were missing it.
-- All existing rows (if any) will be assigned to a default tenant 'system'.

-- products
ALTER TABLE products ADD COLUMN IF NOT EXISTS tenant_id TEXT NOT NULL DEFAULT 'system';
CREATE INDEX IF NOT EXISTS idx_products_tenant ON products(tenant_id);

-- inventory_counts
ALTER TABLE inventory_counts ADD COLUMN IF NOT EXISTS tenant_id TEXT NOT NULL DEFAULT 'system';
CREATE INDEX IF NOT EXISTS idx_inventory_counts_tenant ON inventory_counts(tenant_id);

-- ledger_entries
ALTER TABLE ledger_entries ADD COLUMN IF NOT EXISTS tenant_id TEXT NOT NULL DEFAULT 'system';
CREATE INDEX IF NOT EXISTS idx_ledger_tenant ON ledger_entries(tenant_id);

-- inventory_transactions (audit trail — scope by tenant for reporting)
ALTER TABLE inventory_transactions ADD COLUMN IF NOT EXISTS tenant_id TEXT NOT NULL DEFAULT 'system';
CREATE INDEX IF NOT EXISTS idx_inventory_transactions_tenant ON inventory_transactions(tenant_id);
