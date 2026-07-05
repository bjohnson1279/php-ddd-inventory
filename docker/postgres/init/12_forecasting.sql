-- Demand Forecasting
CREATE TABLE IF NOT EXISTS demand_forecasts (
    id VARCHAR(50) PRIMARY KEY,
    sku VARCHAR(50) NOT NULL,
    location_id VARCHAR(50) NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
    forecasted_quantity INTEGER NOT NULL,
    period_start TIMESTAMP NOT NULL,
    period_end TIMESTAMP NOT NULL,
    confidence_level NUMERIC NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (sku, location_id, period_start, period_end)
);

CREATE INDEX IF NOT EXISTS idx_demand_forecasts_sku_loc ON demand_forecasts(sku, location_id);
