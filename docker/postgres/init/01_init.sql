-- Initial PostgreSQL schema for DDD Inventory

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Tenants table
CREATE TABLE IF NOT EXISTS tenants (
  id         VARCHAR(50) PRIMARY KEY,
  name       TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Catalog Context
CREATE TABLE IF NOT EXISTS catalog_products (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  name TEXT NOT NULL,
  description TEXT,
  department TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE IF NOT EXISTS catalog_variants (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  product_id UUID NOT NULL REFERENCES catalog_products(id) ON DELETE CASCADE,
  sku TEXT NOT NULL UNIQUE,
  attributes JSONB NOT NULL DEFAULT '{}',
  price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Locations
CREATE TABLE IF NOT EXISTS locations (
  id VARCHAR(50) PRIMARY KEY,
  name TEXT NOT NULL,
  type VARCHAR(50) NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  sku TEXT NOT NULL,
  name TEXT NOT NULL,
  department TEXT NOT NULL,
  reorder_threshold INTEGER NOT NULL DEFAULT 10,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(tenant_id, sku)
);

-- Product Locations (Stock)
CREATE TABLE IF NOT EXISTS product_locations (
  product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  location_id VARCHAR(50) NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
  stock_quantity INTEGER NOT NULL DEFAULT 0,
  open_box_quantity INTEGER NOT NULL DEFAULT 0,
  damaged_quantity INTEGER NOT NULL DEFAULT 0,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  PRIMARY KEY (product_id, location_id)
);

-- Inventory transactions (Ledger)
CREATE TABLE IF NOT EXISTS inventory_transactions (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  type VARCHAR(50) NOT NULL,
  quantity_change INTEGER NOT NULL,
  condition VARCHAR(50) NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  reference_id TEXT
);

-- Inventory counts
CREATE TABLE IF NOT EXISTS inventory_counts (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  status VARCHAR(50) NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  completed_at TIMESTAMP WITH TIME ZONE
);

-- Inventory count items
CREATE TABLE IF NOT EXISTS inventory_count_items (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  inventory_count_id UUID NOT NULL REFERENCES inventory_counts(id) ON DELETE CASCADE,
  product_id UUID REFERENCES products(id) ON DELETE SET NULL,
  sku TEXT NOT NULL,
  counted_quantity INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_inventory_count_items_inventory_count_id ON inventory_count_items(inventory_count_id);
CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku);

-- Seed basic locations
INSERT INTO locations (id, name, type) VALUES ('LOC-STOREFRONT', 'Sales Floor', 'STOREFRONT') ON CONFLICT DO NOTHING;
INSERT INTO locations (id, name, type) VALUES ('LOC-BACKROOM', 'Backroom Storage', 'BACKROOM') ON CONFLICT DO NOTHING;

-- Ledger entries (append-only)
CREATE TABLE IF NOT EXISTS ledger_entries (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  variant_id TEXT NOT NULL,
  quantity INTEGER NOT NULL,
  reason VARCHAR(50) NOT NULL,
  actor_id TEXT NOT NULL,
  reference_id TEXT,
  occurred_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_ledger_variant ON ledger_entries(variant_id);
CREATE INDEX IF NOT EXISTS idx_ledger_tenant ON ledger_entries(tenant_id);

-- Serialized item tracking
CREATE TABLE IF NOT EXISTS serialized_items (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  variant_id TEXT NOT NULL,
  serial_number TEXT NOT NULL,
  tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  location_id VARCHAR(50),
  status VARCHAR(50) NOT NULL,
  history JSONB DEFAULT '[]',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE(serial_number, tenant_id)
);
CREATE INDEX IF NOT EXISTS idx_serial_variant ON serialized_items(variant_id);

-- Barcode registry
CREATE TABLE IF NOT EXISTS barcodes (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  value TEXT NOT NULL UNIQUE,
  variant_id TEXT NOT NULL,
  symbology VARCHAR(50),
  source VARCHAR(50),
  is_primary BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_barcodes_variant ON barcodes(variant_id);

-- Stock onboarding (opening balance)
CREATE TABLE IF NOT EXISTS stock_onboardings (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  location_id VARCHAR(50) NOT NULL,
  as_of_date DATE NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE IF NOT EXISTS stock_onboarding_items (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  onboarding_id UUID NOT NULL REFERENCES stock_onboardings(id) ON DELETE CASCADE,
  variant_id TEXT NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 0,
  unit_cost_cents INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_onboarding_variant ON stock_onboarding_items(variant_id);

-- Accounting journal entries (simple storage of lines as JSON)
CREATE TABLE IF NOT EXISTS journal_entries (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  entry_date DATE NOT NULL,
  description TEXT,
  reference_id TEXT,
  method VARCHAR(50),
  lines JSONB NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- UoM configuration and conversion rules
CREATE TABLE IF NOT EXISTS product_uom_configurations (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  variant_id TEXT NOT NULL,
  base_unit VARCHAR(50) NOT NULL,
  purchase_unit VARCHAR(50),
  sale_unit VARCHAR(50),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE IF NOT EXISTS uom_conversion_rules (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  configuration_id UUID NOT NULL REFERENCES product_uom_configurations(id) ON DELETE CASCADE,
  unit VARCHAR(50) NOT NULL,
  factor_to_base NUMERIC NOT NULL,
  label TEXT
);

-- Kits and components
CREATE TABLE IF NOT EXISTS kits (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  sku TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE IF NOT EXISTS kit_components (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  kit_id UUID NOT NULL REFERENCES kits(id) ON DELETE CASCADE,
  variant_id TEXT NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 1
);

-- Convenience indexes
CREATE INDEX IF NOT EXISTS idx_ledger_variant ON ledger_entries(variant_id);
CREATE INDEX IF NOT EXISTS idx_serial_tenant ON serialized_items(tenant_id);
CREATE INDEX IF NOT EXISTS idx_barcodes_value ON barcodes(value);
