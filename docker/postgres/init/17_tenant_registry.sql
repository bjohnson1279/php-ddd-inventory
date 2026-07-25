-- Tenant Registry Table for Dynamic Connection Pooling
CREATE TABLE IF NOT EXISTS tenant_registry (
  tenant_id        VARCHAR(50) PRIMARY KEY,
  db_host          TEXT NOT NULL,
  db_port          INTEGER NOT NULL DEFAULT 5432,
  db_name          TEXT NOT NULL,
  db_user          TEXT NOT NULL,
  db_password      TEXT NOT NULL,
  status           VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
  provisioned_at   TIMESTAMP WITH TIME ZONE DEFAULT now(),
  migrated_version VARCHAR(50) NOT NULL DEFAULT '1'
);

INSERT INTO tenant_registry (tenant_id, db_host, db_port, db_name, db_user, db_password, status, provisioned_at, migrated_version)
VALUES 
  ('test-tenant', 'localhost', 5432, 'ddd_inventory', 'ddd_user', 'secret', 'ACTIVE', now(), '1'),
  ('system', 'localhost', 5432, 'ddd_inventory', 'ddd_user', 'secret', 'ACTIVE', now(), '1')
ON CONFLICT (tenant_id) DO UPDATE SET status = 'ACTIVE';
