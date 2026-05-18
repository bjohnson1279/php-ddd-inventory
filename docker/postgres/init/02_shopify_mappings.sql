-- Shopify integration mapping tables
-- Tracks which Shopify location_id corresponds to each of our internal locations,
-- and which Shopify inventory_item_id corresponds to each of our SKUs.
-- These are needed for both inbound webhook routing and outbound stock sync.

CREATE TABLE IF NOT EXISTS shopify_location_mappings (
  id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  our_location_id     VARCHAR(50) NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
  shopify_location_id VARCHAR(50) NOT NULL UNIQUE,
  created_at          TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE TABLE IF NOT EXISTS shopify_sku_mappings (
  id                        UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  sku                       TEXT NOT NULL UNIQUE REFERENCES catalog_variants(sku) ON DELETE CASCADE,
  shopify_inventory_item_id VARCHAR(50) NOT NULL UNIQUE,
  created_at                TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_shopify_location_mappings_shopify_id ON shopify_location_mappings(shopify_location_id);
CREATE INDEX IF NOT EXISTS idx_shopify_sku_mappings_sku             ON shopify_sku_mappings(sku);
