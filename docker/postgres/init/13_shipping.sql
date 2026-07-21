-- Shipping & Outbox Integration
CREATE TABLE IF NOT EXISTS shipments (
    id VARCHAR(50) PRIMARY KEY,
    sku VARCHAR(50) NOT NULL,
    quantity INTEGER NOT NULL,
    destination_address TEXT NOT NULL,
    carrier VARCHAR(50) NOT NULL,
    tracking_number VARCHAR(100),
    label_url TEXT,
    shipping_rate_cents INTEGER NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS outbox_events (
    id VARCHAR(50) PRIMARY KEY,
    event_name VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    occurred_on TIMESTAMP NOT NULL,
    processed_at TIMESTAMP DEFAULT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    next_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
