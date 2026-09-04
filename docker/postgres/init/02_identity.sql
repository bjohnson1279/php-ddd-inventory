-- Identity & Access Management schema

CREATE TABLE IF NOT EXISTS users (
  id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  tenant_id     VARCHAR(50) NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  email         TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  name          TEXT NOT NULL,
  active        BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP WITH TIME ZONE DEFAULT now(),
  UNIQUE (tenant_id, email)
);

CREATE TABLE IF NOT EXISTS roles (
  id   VARCHAR(20) PRIMARY KEY,
  name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS user_roles (
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role_id VARCHAR(20) NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
  PRIMARY KEY (user_id, role_id)
);

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id    VARCHAR(20) NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
  permission TEXT NOT NULL,
  PRIMARY KEY (role_id, permission)
);

CREATE TABLE IF NOT EXISTS api_tokens (
  id         TEXT PRIMARY KEY,
  user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  tenant_id  VARCHAR(50) NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_api_tokens_hash      ON api_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_users_tenant_email   ON users(tenant_id, email);

-- Seed the three default roles and their permissions
INSERT INTO roles (id, name) VALUES ('admin',   'Administrator') ON CONFLICT DO NOTHING;
INSERT INTO roles (id, name) VALUES ('manager', 'Manager')       ON CONFLICT DO NOTHING;
INSERT INTO roles (id, name) VALUES ('staff',   'Staff')         ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission) VALUES
  ('admin',   'inventory:receive'),   ('admin',   'inventory:dispatch'),
  ('admin',   'inventory:transfer'),  ('admin',   'inventory:reconcile'),
  ('admin',   'inventory:read'),      ('admin',   'sales:process'),
  ('admin',   'returns:process'),     ('admin',   'catalog:manage'),
  ('admin',   'catalog:read'),        ('admin',   'reports:view'),
  ('admin',   'integrations:manage'), ('admin',   'users:manage'),
  ('manager', 'inventory:receive'),   ('manager', 'inventory:dispatch'),
  ('manager', 'inventory:transfer'),  ('manager', 'inventory:reconcile'),
  ('manager', 'inventory:read'),      ('manager', 'sales:process'),
  ('manager', 'returns:process'),     ('manager', 'catalog:manage'),
  ('manager', 'catalog:read'),        ('manager', 'reports:view'),
  ('staff',   'inventory:read'),      ('staff',   'sales:process'),
  ('staff',   'returns:process'),     ('staff',   'catalog:read')
ON CONFLICT DO NOTHING;
