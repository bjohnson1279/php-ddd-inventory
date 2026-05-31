-- Cost Layers Table
CREATE TABLE IF NOT EXISTS inventory_cost_layers (
    id VARCHAR(50) PRIMARY KEY,
    tenant_id VARCHAR(50) NOT NULL,
    variant_id VARCHAR(50) NOT NULL,
    original_quantity INTEGER NOT NULL,
    remaining_quantity INTEGER NOT NULL,
    unit_cost_cents BIGINT NOT NULL,
    purchase_order_id VARCHAR(50),
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    serial_number VARCHAR(100)
);

CREATE INDEX IF NOT EXISTS idx_cost_layers_tenant ON inventory_cost_layers(tenant_id);
CREATE INDEX IF NOT EXISTS idx_cost_layers_variant ON inventory_cost_layers(variant_id);
CREATE INDEX IF NOT EXISTS idx_cost_layers_serial ON inventory_cost_layers(serial_number);
