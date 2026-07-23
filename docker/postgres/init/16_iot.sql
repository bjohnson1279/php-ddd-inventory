CREATE TABLE IF NOT EXISTS rfid_tags (
  epc TEXT PRIMARY KEY,
  sku TEXT NOT NULL,
  serial_number TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'ACTIVE',
  last_seen_at TIMESTAMP,
  last_location TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
