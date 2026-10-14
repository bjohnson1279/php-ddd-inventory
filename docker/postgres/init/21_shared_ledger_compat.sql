-- Additive compatibility for the shared development database. PHP stores
-- journal lines in JSON; Express uses normalized rows. Preserve both formats.
ALTER TABLE journal_entries ADD COLUMN IF NOT EXISTS accounting_method VARCHAR(50);
UPDATE journal_entries SET accounting_method = COALESCE(method, 'accrual') WHERE accounting_method IS NULL;
ALTER TABLE journal_entries ALTER COLUMN lines SET DEFAULT '[]'::jsonb;

ALTER TABLE journal_lines ADD COLUMN IF NOT EXISTS journal_entry_id VARCHAR(100);
ALTER TABLE journal_lines ADD COLUMN IF NOT EXISTS account_name TEXT;
ALTER TABLE journal_lines ADD COLUMN IF NOT EXISTS account_category VARCHAR(50);
ALTER TABLE journal_lines ADD COLUMN IF NOT EXISTS debit_or_credit VARCHAR(50);
UPDATE journal_lines SET journal_entry_id = entry_id WHERE journal_entry_id IS NULL;
UPDATE journal_lines SET debit_or_credit = type WHERE debit_or_credit IS NULL;
UPDATE journal_lines SET account_name = 'Account ' || account_code WHERE account_name IS NULL;
UPDATE journal_lines SET account_category = CASE LEFT(account_code, 1)
  WHEN '2' THEN 'liability' WHEN '3' THEN 'equity' WHEN '4' THEN 'revenue'
  WHEN '5' THEN 'expense' WHEN '6' THEN 'expense' ELSE 'asset' END
WHERE account_category IS NULL;
CREATE INDEX IF NOT EXISTS idx_journal_lines_entry_compat ON journal_lines(journal_entry_id);

CREATE OR REPLACE FUNCTION inventory_journal_header_aliases() RETURNS TRIGGER AS $$
BEGIN
  IF TG_OP = 'INSERT' THEN
    NEW.accounting_method := COALESCE(NEW.accounting_method, NEW.method, 'accrual');
    NEW.method := COALESCE(NEW.method, NEW.accounting_method);
  ELSE
    IF NEW.method IS DISTINCT FROM OLD.method AND NEW.accounting_method IS NOT DISTINCT FROM OLD.accounting_method THEN
      NEW.accounting_method := NEW.method;
    ELSIF NEW.accounting_method IS DISTINCT FROM OLD.accounting_method THEN
      NEW.method := NEW.accounting_method;
    END IF;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS inventory_journal_header_aliases ON journal_entries;
CREATE TRIGGER inventory_journal_header_aliases BEFORE INSERT OR UPDATE ON journal_entries
FOR EACH ROW EXECUTE FUNCTION inventory_journal_header_aliases();

CREATE OR REPLACE FUNCTION inventory_journal_line_aliases() RETURNS TRIGGER AS $$
BEGIN
  IF TG_OP = 'UPDATE' THEN
    IF NEW.entry_id IS DISTINCT FROM OLD.entry_id AND NEW.journal_entry_id IS NOT DISTINCT FROM OLD.journal_entry_id THEN
      NEW.journal_entry_id := NEW.entry_id;
    END IF;
    IF NEW.type IS DISTINCT FROM OLD.type AND NEW.debit_or_credit IS NOT DISTINCT FROM OLD.debit_or_credit THEN
      NEW.debit_or_credit := NEW.type;
    END IF;
  END IF;
  NEW.journal_entry_id := COALESCE(NEW.journal_entry_id, NEW.entry_id);
  NEW.entry_id := NEW.journal_entry_id;
  NEW.debit_or_credit := COALESCE(NEW.debit_or_credit, NEW.type);
  NEW.type := NEW.debit_or_credit;
  NEW.account_name := COALESCE(NEW.account_name, 'Account ' || NEW.account_code);
  NEW.account_category := COALESCE(NEW.account_category, CASE LEFT(NEW.account_code, 1)
    WHEN '2' THEN 'liability' WHEN '3' THEN 'equity' WHEN '4' THEN 'revenue'
    WHEN '5' THEN 'expense' WHEN '6' THEN 'expense' ELSE 'asset' END);
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS inventory_journal_line_aliases ON journal_lines;
CREATE TRIGGER inventory_journal_line_aliases BEFORE INSERT OR UPDATE ON journal_lines
FOR EACH ROW EXECUTE FUNCTION inventory_journal_line_aliases();

-- Import historical PHP JSON without replacing any existing normalized lines.
INSERT INTO journal_lines(id, entry_id, journal_entry_id, account_code, amount_cents, type, debit_or_credit, memo)
SELECT COALESCE(line->>'id', MD5(entry.id || ':legacy:' || ordinal::text)), entry.id, entry.id,
  COALESCE(line->>'account', line->>'accountCode'),
  COALESCE(line->>'amount', line->>'amountCents')::integer,
  line->>'type', line->>'type', line->>'memo'
FROM journal_entries entry CROSS JOIN LATERAL jsonb_array_elements(entry.lines) WITH ORDINALITY AS item(line, ordinal)
WHERE NOT EXISTS (SELECT 1 FROM journal_lines existing WHERE existing.journal_entry_id = entry.id)
ON CONFLICT (id) DO NOTHING;

CREATE OR REPLACE FUNCTION inventory_journal_json_to_lines() RETURNS TRIGGER AS $$
BEGIN
  IF pg_trigger_depth() > 1 THEN RETURN NEW; END IF;
  IF TG_OP = 'UPDATE' AND NEW.lines IS NOT DISTINCT FROM OLD.lines THEN RETURN NEW; END IF;
  DELETE FROM journal_lines WHERE journal_entry_id = NEW.id;
  INSERT INTO journal_lines(id, journal_entry_id, account_code, amount_cents, debit_or_credit, memo)
  SELECT COALESCE(line->>'id', MD5(NEW.id || ':legacy:' || ordinal::text)), NEW.id,
    COALESCE(line->>'account', line->>'accountCode'), COALESCE(line->>'amount', line->>'amountCents')::integer,
    line->>'type', line->>'memo'
  FROM jsonb_array_elements(NEW.lines) WITH ORDINALITY AS item(line, ordinal);
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS inventory_journal_json_to_lines ON journal_entries;
CREATE TRIGGER inventory_journal_json_to_lines AFTER INSERT OR UPDATE OF lines ON journal_entries
FOR EACH ROW EXECUTE FUNCTION inventory_journal_json_to_lines();

CREATE OR REPLACE FUNCTION inventory_journal_lines_to_json() RETURNS TRIGGER AS $$
DECLARE identifier VARCHAR(100);
BEGIN
  IF pg_trigger_depth() > 1 THEN RETURN NULL; END IF;
  identifier := CASE WHEN TG_OP = 'DELETE' THEN OLD.journal_entry_id ELSE NEW.journal_entry_id END;
  UPDATE journal_entries SET lines = COALESCE((SELECT jsonb_agg(jsonb_build_object(
    'id', id, 'account', account_code, 'amount', amount_cents, 'type', debit_or_credit, 'memo', memo) ORDER BY id)
    FROM journal_lines WHERE journal_entry_id = identifier), '[]'::jsonb)
  WHERE id = identifier;
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS inventory_journal_lines_to_json ON journal_lines;
CREATE TRIGGER inventory_journal_lines_to_json AFTER INSERT OR UPDATE OR DELETE ON journal_lines
FOR EACH ROW EXECUTE FUNCTION inventory_journal_lines_to_json();
