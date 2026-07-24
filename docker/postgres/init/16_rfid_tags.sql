CREATE TABLE IF NOT EXISTS rfid_tags (
  epc           VARCHAR(255) PRIMARY KEY,
  sku           VARCHAR(255) NOT NULL,
  serial_number VARCHAR(255) NOT NULL,
  status        VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
  last_seen_at  TIMESTAMP,
  last_location VARCHAR(255),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
