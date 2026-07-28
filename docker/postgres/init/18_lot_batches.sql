-- Lot Batches Table
CREATE TABLE IF NOT EXISTS lot_batches (
    id VARCHAR(50) PRIMARY KEY,
    tenant_id VARCHAR(50) NOT NULL,
    lot_number VARCHAR(100) NOT NULL,
    variant_id VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
    manufactured_date TIMESTAMP,
    expiration_date TIMESTAMP,
    supplier_id VARCHAR(50),
    quarantined_at TIMESTAMP,
    quarantine_reason TEXT,
    recalled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unq_lot_tenant_variant UNIQUE (tenant_id, lot_number, variant_id)
);
