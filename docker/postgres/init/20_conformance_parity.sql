-- 20_conformance_parity.sql
-- Harmonizes tables across GraphQL, Express REST, and PHP REST for end-to-end conformance parity

-- Webhook events (for background worker polling)
CREATE TABLE IF NOT EXISTS webhook_events (
  id VARCHAR(255) PRIMARY KEY,
  topic VARCHAR(255) NOT NULL,
  shop_domain VARCHAR(255) NOT NULL,
  payload TEXT NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Pending',
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  occurred_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Dispatch records (for hypertable & dispatch logs)
CREATE TABLE IF NOT EXISTS dispatch_records (
  id VARCHAR(255) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  location_id VARCHAR(100) NOT NULL,
  quantity INTEGER NOT NULL,
  dispatched_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lot_number VARCHAR(100),
  PRIMARY KEY (id, dispatched_at)
);

-- Tenant accounting configuration
CREATE TABLE IF NOT EXISTS tenant_accounting_configs (
  tenant_id VARCHAR(100) PRIMARY KEY,
  accounting_method VARCHAR(50) NOT NULL DEFAULT 'accrual',
  costing_method VARCHAR(50) NOT NULL DEFAULT 'fifo'
);

-- Third-party Integration connections & external entity mappings
CREATE TABLE IF NOT EXISTS integration_connections (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  tenant_id VARCHAR(100) NOT NULL,
  platform VARCHAR(50) NOT NULL,
  store_domain VARCHAR(255) NOT NULL,
  access_token TEXT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS external_mappings (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  tenant_id VARCHAR(100) NOT NULL,
  integration_id VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  internal_id VARCHAR(255) NOT NULL,
  external_id VARCHAR(255) NOT NULL,
  external_secondary_id VARCHAR(255),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Journal lines for financial entries
CREATE TABLE IF NOT EXISTS journal_lines (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  entry_id VARCHAR(100) NOT NULL,
  account_code VARCHAR(100) NOT NULL,
  amount_cents INTEGER NOT NULL,
  type VARCHAR(50) NOT NULL,
  memo TEXT
);

-- Replenishment rules for automated purchasing
CREATE TABLE IF NOT EXISTS replenishment_rules (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  tenant_id VARCHAR(100) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  location_id VARCHAR(100) NOT NULL,
  min_stock_level INTEGER NOT NULL,
  max_stock_level INTEGER NOT NULL,
  reorder_quantity INTEGER NOT NULL,
  lead_time_days INTEGER NOT NULL DEFAULT 7,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Stock transfers across warehouse nodes
CREATE TABLE IF NOT EXISTS stock_transfers (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  tenant_id VARCHAR(100) NOT NULL,
  source_location_id VARCHAR(100) NOT NULL,
  destination_location_id VARCHAR(100) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
  created_by VARCHAR(100) NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_transfer_items (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  transfer_id VARCHAR(100) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  quantity INTEGER NOT NULL,
  received_quantity INTEGER DEFAULT 0
);

-- Inventory physical audits
CREATE TABLE IF NOT EXISTS inventory_audits (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  audit_number VARCHAR(100) NOT NULL UNIQUE,
  tenant_id VARCHAR(100) NOT NULL,
  location_id VARCHAR(100) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_audit_items (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  inventory_audit_id VARCHAR(100) NOT NULL REFERENCES inventory_audits(id) ON DELETE CASCADE,
  variant_id VARCHAR(100) NOT NULL,
  expected_quantity INTEGER NOT NULL,
  counted_quantity INTEGER,
  is_counted BOOLEAN NOT NULL DEFAULT FALSE
);

-- Serialized item state history
CREATE TABLE IF NOT EXISTS serialized_item_history (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  serial_number VARCHAR(100) NOT NULL,
  tenant_id VARCHAR(100) NOT NULL,
  from_status VARCHAR(50),
  to_status VARCHAR(50) NOT NULL,
  changed_by VARCHAR(100) NOT NULL,
  changed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  notes TEXT
);

-- UOM conversion rules
CREATE TABLE IF NOT EXISTS conversion_rules (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  config_sku VARCHAR(100) NOT NULL,
  from_unit VARCHAR(50) NOT NULL,
  to_unit VARCHAR(50) NOT NULL,
  multiplier NUMERIC(15, 6) NOT NULL
);

-- Inventory items table
CREATE TABLE IF NOT EXISTS inventory_items (
  id VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  sku VARCHAR(100) NOT NULL,
  location_id VARCHAR(100) NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 0,
  allocated INTEGER NOT NULL DEFAULT 0,
  in_transit INTEGER NOT NULL DEFAULT 0,
  version INTEGER NOT NULL DEFAULT 1,
  UNIQUE(sku, location_id)
);

-- Product variants & attributes
CREATE TABLE IF NOT EXISTS product_variants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id VARCHAR(100) NOT NULL,
  sku VARCHAR(100) NOT NULL UNIQUE,
  tracking_mode VARCHAR(50) NOT NULL DEFAULT 'quantity',
  costing_method VARCHAR(50) NOT NULL DEFAULT 'fifo',
  weight_grams INTEGER,
  volume_cubic_meters NUMERIC,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS variant_attributes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  variant_id UUID NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
  name VARCHAR(100) NOT NULL,
  value TEXT NOT NULL,
  UNIQUE(variant_id, name)
);
