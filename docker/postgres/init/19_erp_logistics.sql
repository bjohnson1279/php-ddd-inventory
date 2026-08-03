-- 19_erp_logistics.sql

CREATE TABLE IF NOT EXISTS bills_of_lading (
    id UUID PRIMARY KEY,
    bol_number VARCHAR(100) NOT NULL UNIQUE,
    carrier VARCHAR(50) NOT NULL,
    origin_address TEXT NOT NULL,
    destination_address TEXT NOT NULL,
    weight_kg NUMERIC(10,2) NOT NULL,
    total_packages INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'GENERATED',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS return_merchandise_authorizations (
    id UUID PRIMARY KEY,
    rma_number VARCHAR(100) NOT NULL UNIQUE,
    order_id VARCHAR(100) NOT NULL,
    customer_id VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'PENDING_INSPECTION',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rma_items (
    id UUID PRIMARY KEY,
    rma_id UUID NOT NULL REFERENCES return_merchandise_authorizations(id) ON DELETE CASCADE,
    sku VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    disposition VARCHAR(50) NOT NULL DEFAULT 'PENDING',
    inspected_at TIMESTAMP WITH TIME ZONE
);

CREATE TABLE IF NOT EXISTS supplier_asns (
    id UUID PRIMARY KEY,
    asn_number VARCHAR(100) NOT NULL UNIQUE,
    supplier_id VARCHAR(100) NOT NULL,
    expected_delivery TIMESTAMP WITH TIME ZONE NOT NULL,
    actual_delivery TIMESTAMP WITH TIME ZONE,
    status VARCHAR(50) NOT NULL DEFAULT 'IN_TRANSIT',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS supplier_scorecards (
    id UUID PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL,
    on_time_rate NUMERIC(5,2) NOT NULL,
    in_full_rate NUMERIC(5,2) NOT NULL,
    defect_rate NUMERIC(5,2) NOT NULL,
    otif_score NUMERIC(5,2) NOT NULL,
    evaluated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS esg_emissions_records (
    id UUID PRIMARY KEY,
    tenant_id VARCHAR(100) NOT NULL,
    transport_mode VARCHAR(50) NOT NULL,
    distance_km NUMERIC(10,2) NOT NULL,
    weight_kg NUMERIC(10,2) NOT NULL,
    ton_km NUMERIC(10,2) NOT NULL,
    co2e_kg NUMERIC(10,2) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
