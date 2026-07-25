-- Xero integration mapping tables
-- Tracks the mapping between our local journal entries and Xero journal entries.

CREATE TABLE IF NOT EXISTS xero_journal_mappings (
  id               VARCHAR(100) PRIMARY KEY DEFAULT gen_random_uuid(),
  journal_entry_id VARCHAR(100) NOT NULL UNIQUE REFERENCES journal_entries(id) ON DELETE CASCADE,
  xero_journal_id  VARCHAR(50) NOT NULL UNIQUE,
  created_at       TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_xero_journal_mappings_local ON xero_journal_mappings(journal_entry_id);
