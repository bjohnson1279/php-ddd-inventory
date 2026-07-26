CREATE TABLE audit_discrepancies (
    id VARCHAR(255) PRIMARY KEY,
    tenant_id VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    reference_id VARCHAR(255) NOT NULL,
    external_ref_id VARCHAR(255),
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'OPEN',
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    resolution_notes TEXT
);
