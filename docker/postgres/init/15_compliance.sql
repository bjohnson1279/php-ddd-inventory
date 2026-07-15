CREATE TABLE IF NOT EXISTS compliance_ledgers (
  id VARCHAR(50) PRIMARY KEY,
  tenant_id VARCHAR(50) NOT NULL,
  actor_id VARCHAR(50) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  sequence_number INTEGER NOT NULL,
  previous_hash VARCHAR(64) NOT NULL,
  current_hash VARCHAR(64) NOT NULL,
  signature VARCHAR(64) NOT NULL,
  payload TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);
