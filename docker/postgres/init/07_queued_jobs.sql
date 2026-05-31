CREATE TABLE IF NOT EXISTS queued_jobs (
  id            VARCHAR(50) PRIMARY KEY,
  tenant_id     VARCHAR(50) NOT NULL,
  listener_class VARCHAR(255) NOT NULL,
  event_data    TEXT NOT NULL,
  attempts      INTEGER NOT NULL DEFAULT 0,
  reserved_at   TIMESTAMP NULL DEFAULT NULL,
  available_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_queued_jobs_available_at ON queued_jobs(available_at);
CREATE INDEX IF NOT EXISTS idx_queued_jobs_tenant_id ON queued_jobs(tenant_id);
