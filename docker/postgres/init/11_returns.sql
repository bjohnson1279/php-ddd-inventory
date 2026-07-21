-- Returns and Quarantine Management
CREATE TABLE IF NOT EXISTS rmas (
    id VARCHAR(50) PRIMARY KEY,
    rma_number VARCHAR(100) NOT NULL UNIQUE,
    tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    customer_id VARCHAR(100) NOT NULL,
    location_id VARCHAR(50) NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rma_items (
    id VARCHAR(50) PRIMARY KEY,
    rma_id VARCHAR(50) NOT NULL REFERENCES rmas(id) ON DELETE CASCADE,
    variant_id VARCHAR(50) NOT NULL,
    quantity INTEGER NOT NULL,
    received_quantity INTEGER NOT NULL DEFAULT 0,
    unit_cost_cents INTEGER NOT NULL,
    status VARCHAR(50) NOT NULL,
    disposition VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quarantine_items (
    id VARCHAR(50) PRIMARY KEY,
    tenant_id VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    variant_id VARCHAR(50) NOT NULL,
    quantity INTEGER NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,
    location_id VARCHAR(50) NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_rmas_tenant ON rmas(tenant_id);
CREATE INDEX IF NOT EXISTS idx_rma_items_rma ON rma_items(rma_id);
CREATE INDEX IF NOT EXISTS idx_quarantine_items_tenant ON quarantine_items(tenant_id);
