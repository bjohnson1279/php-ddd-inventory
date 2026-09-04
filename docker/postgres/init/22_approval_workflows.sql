CREATE TABLE IF NOT EXISTS approval_workflows (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    trigger_event VARCHAR(100) NOT NULL,
    config JSONB NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tenant_id, trigger_event)
);

CREATE TABLE IF NOT EXISTS approval_requests (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    workflow_id UUID NOT NULL,
    reference_type VARCHAR(100) NOT NULL,
    reference_id VARCHAR(255) NOT NULL,
    requester_id UUID NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    current_step INT DEFAULT 0,
    payload JSONB NOT NULL,
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id)
);

CREATE TABLE IF NOT EXISTS approval_decisions (
    id UUID PRIMARY KEY,
    request_id UUID NOT NULL,
    step_index INT NOT NULL,
    decider_id UUID NOT NULL,
    decision VARCHAR(20) NOT NULL,
    notes TEXT,
    decided_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES approval_requests(id)
);
