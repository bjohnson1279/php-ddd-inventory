import React, { useEffect, useState } from 'react';
import api from '../api/client';
import Spinner from '../components/Spinner';

type LocationValuation = {
  location_id: string;
  name: string;
  valuation: number; // in cents
};

type RecentActivity = {
  id: string;
  product_name: string;
  sku: string;
  type: string;
  quantity_change: number;
  condition: string;
  created_at: string;
};

type LowStockItem = {
  id: string;
  name: string;
  sku: string;
  current_stock: number;
  reorder_threshold: number;
};

type ReportData = {
  total_valuation_fifo_cents: number;
  total_valuation_lifo_cents: number;
  total_valuation_wac_cents: number;
  total_items_count: number;
  low_stock_alerts_count: number;
  low_stock_items: LowStockItem[];
  valuation_by_location: LocationValuation[];
  recent_activity: RecentActivity[];
};

export default function ValuationDashboard() {
  const [data, setData] = useState<ReportData | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedModel, setSelectedModel] = useState<'fifo' | 'lifo' | 'wac'>('wac');
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

  useEffect(() => {
    fetchReport();
  }, []);

  const fetchReport = async () => {
    try {
      setRefreshing(true);
      setError(null);
      const res = await api.get('/reports/valuation');
      setData(res);
      setLastUpdated(new Date());
    } catch (err: any) {
      setError(err.message || 'Failed to fetch valuation analytics.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const formatMoney = (cents: number) => {
    return '$' + (cents / 100).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  };

  if (loading) {
    return (
      <div className="empty-state-text" style={{ padding: '5rem 0' }}>
        <div style={{ fontSize: '2rem', marginBottom: '1rem' }}>🔄</div>
        <p style={{ color: '#9ca3af' }}>Calculating inventory valuation layers...</p>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="empty-state-text" style={{ padding: '5rem 0' }}>
        <div style={{ fontSize: '2.5rem', color: '#f87171', marginBottom: '1.5rem' }}>⚠️</div>
        <h3 style={{ color: '#fff', marginBottom: '0.75rem' }}>Valuation Calculation Error</h3>
        <p style={{ color: '#f87171', marginBottom: '1.5rem' }}>{error || 'No valuation data available.'}</p>
        <button onClick={fetchReport} className="btn-primary">
          Try Again
        </button>
      </div>
    );
  }

  // Get valuation for the currently selected model
  const getSelectedValuationCents = () => {
    if (selectedModel === 'fifo') return data.total_valuation_fifo_cents;
    if (selectedModel === 'lifo') return data.total_valuation_lifo_cents;
    return data.total_valuation_wac_cents;
  };

  const totalValuationCents = getSelectedValuationCents();
  const maxLocationVal = data.valuation_by_location.reduce(
    (max, loc) => (loc.valuation > max ? loc.valuation : max),
    0
  );

  return (
    <div>
      {/* Dashboard Title & Actions Header */}
      <div className="analytics-header">
        <div>
          <h2 style={{ fontSize: '1.5rem', fontWeight: 600, color: '#fff' }}>
            Financial Valuation & Analytics
          </h2>
          <p className="analytics-title-desc">
            Realtime valuation modeling under FIFO, LIFO, and WAC cost layers.
          </p>
        </div>
        <div className="refresh-container">
          {lastUpdated && (
            <span className="text-muted" style={{ fontSize: '0.8rem' }}>
              Last Calculated: {lastUpdated.toLocaleTimeString()}
            </span>
          )}
          <button
            onClick={fetchReport}
            className={`btn-refresh ${refreshing ? 'disabled' : ''}`}
            disabled={refreshing}
          >
            {refreshing ? <Spinner /> : <span>🔄</span>}
            {refreshing ? 'Re-valuing...' : 'Recalculate'}
          </button>
        </div>
      </div>

      {/* Main KPI Row */}
      <div className="analytics-dashboard-grid">
        {/* Glow Stat Card 1: Dynamic Valuation Model Choice */}
        <div className="stat-card-premium glow-indigo">
          <div className="card-bg-gradient-indigo"></div>
          <div className="stat-label-row">
            <span>Asset Valuation ({selectedModel.toUpperCase()})</span>
            <span className="stat-icon">⚖️</span>
          </div>
          <div className="stat-val-container">
            <span className="stat-val-large stat-val-gradient-indigo">
              {formatMoney(totalValuationCents)}
            </span>
          </div>
          <div className="model-selection-bar">
            <button
              onClick={() => setSelectedModel('wac')}
              className={`model-tab-btn ${selectedModel === 'wac' ? 'active' : ''}`}
            >
              WAC
            </button>
            <button
              onClick={() => setSelectedModel('fifo')}
              className={`model-tab-btn ${selectedModel === 'fifo' ? 'active' : ''}`}
            >
              FIFO
            </button>
            <button
              onClick={() => setSelectedModel('lifo')}
              className={`model-tab-btn ${selectedModel === 'lifo' ? 'active' : ''}`}
            >
              LIFO
            </button>
          </div>
        </div>

        {/* Glow Stat Card 2: Total Items */}
        <div className="stat-card-premium glow-emerald">
          <div className="card-bg-gradient-emerald"></div>
          <div className="stat-label-row">
            <span>Total Units Handled</span>
            <span className="stat-icon">📦</span>
          </div>
          <div className="stat-val-container">
            <span className="stat-val-large stat-val-gradient-emerald">
              {data.total_items_count.toLocaleString()}
            </span>
          </div>
          <div className="stat-subtext" style={{ color: '#a7f3d0' }}>
            Across all physical locations
          </div>
        </div>

        {/* Glow Stat Card 3: Low Stock Alerts */}
        <div
          className={`stat-card-premium ${
            data.low_stock_alerts_count > 0 ? 'glow-rose' : ''
          }`}
          style={{
            borderColor: data.low_stock_alerts_count > 0 ? 'rgba(244, 63, 94, 0.2)' : '',
          }}
        >
          <div className="card-bg-gradient-rose"></div>
          <div className="stat-label-row">
            <span>Reorder Triggers</span>
            <span className="stat-icon">⚠️</span>
          </div>
          <div className="stat-val-container">
            <span
              className={`stat-val-large ${
                data.low_stock_alerts_count > 0
                  ? 'stat-val-gradient-rose'
                  : 'stat-val-large'
              }`}
            >
              {data.low_stock_alerts_count}
            </span>
          </div>
          <div
            className="stat-subtext"
            style={{ color: data.low_stock_alerts_count > 0 ? '#fecdd3' : '#6b7280' }}
          >
            {data.low_stock_alerts_count > 0
              ? 'Units are below reorder threshold'
              : 'All products adequately stocked'}
          </div>
        </div>
      </div>

      {/* Detail Breakdown Panels Grid */}
      <div className="dashboard-panel-grid">
        {/* Left Side Panel: Valuation comparison table and location breakdown */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Subcard 1: Model Comparison */}
          <div className="dashboard-section-card">
            <div className="dashboard-section-title-row">
              <span className="dashboard-section-title">Valuation Method Comparison</span>
              <span className="text-muted" style={{ fontSize: '0.8rem' }}>
                Inflation Impact Analysis
              </span>
            </div>
            <div className="comparison-table-wrapper">
              <table className="comparison-table">
                <thead>
                  <tr>
                    <th>Method</th>
                    <th>Asset Valuation</th>
                    <th>Description</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr className={selectedModel === 'wac' ? 'active-row' : ''}>
                    <td>
                      <span className="valuation-pill valuation-pill-wac">WAC</span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{formatMoney(data.total_valuation_wac_cents)}</td>
                    <td className="text-muted">Weighted Average of remaining cost layers</td>
                    <td>{selectedModel === 'wac' ? '🟢 Active' : ''}</td>
                  </tr>
                  <tr className={selectedModel === 'fifo' ? 'active-row' : ''}>
                    <td>
                      <span className="valuation-pill valuation-pill-fifo">FIFO</span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{formatMoney(data.total_valuation_fifo_cents)}</td>
                    <td className="text-muted">First-In, First-Out (Newest layers remaining)</td>
                    <td>{selectedModel === 'fifo' ? '🟢 Active' : ''}</td>
                  </tr>
                  <tr className={selectedModel === 'lifo' ? 'active-row' : ''}>
                    <td>
                      <span className="valuation-pill valuation-pill-lifo">LIFO</span>
                    </td>
                    <td style={{ fontWeight: 600 }}>{formatMoney(data.total_valuation_lifo_cents)}</td>
                    <td className="text-muted">Last-In, First-Out (Oldest layers remaining)</td>
                    <td>{selectedModel === 'lifo' ? '🟢 Active' : ''}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            {/* Difference note */}
            <div style={{ marginTop: '1.25rem', padding: '0.75rem 1rem', background: 'rgba(255,255,255,0.01)', borderRadius: '8px', border: '1px solid rgba(255,255,255,0.03)' }}>
              <span style={{ fontSize: '0.8rem', color: '#9ca3af' }}>
                💡 <strong>Valuation Analysis:</strong> FIFO yields a valuation of{' '}
                {formatMoney(data.total_valuation_fifo_cents)} compared to LIFO's{' '}
                {formatMoney(data.total_valuation_lifo_cents)}. A higher FIFO value indicates rising costs for inventory replacement (inflation layers are active).
              </span>
            </div>
          </div>

          {/* Subcard 2: Breakdown by Location */}
          <div className="dashboard-section-card">
            <div className="dashboard-section-title-row">
              <span className="dashboard-section-title">Valuation by Storage Location</span>
              <span className="text-muted" style={{ fontSize: '0.8rem' }}>
                WAC Method Distribution
              </span>
            </div>

            {data.valuation_by_location.length === 0 ? (
              <div className="empty-state-text">No location inventory balances recorded.</div>
            ) : (
              <div className="bar-chart-container">
                {data.valuation_by_location.map((loc) => {
                  const pct = maxLocationVal > 0 ? (loc.valuation / maxLocationVal) * 100 : 0;
                  return (
                    <div className="bar-row" key={loc.location_id}>
                      <div className="bar-info-row">
                        <span className="bar-label">📍 {loc.name}</span>
                        <span className="bar-percentage">
                          {formatMoney(loc.valuation)}{' '}
                          <span style={{ color: '#6b7280', fontSize: '0.75rem', fontWeight: 400 }}>
                            ({pct.toFixed(0)}%)
                          </span>
                        </span>
                      </div>
                      <div className="bar-track">
                        <div
                          className="bar-fill-gradient"
                          style={{ width: `${pct}%` }}
                        ></div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Right Side Panel: Recent activity timeline and Actionable Warnings */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Subcard 3: Actionable Low Stock alerts */}
          <div className="dashboard-section-card">
            <div className="dashboard-section-title-row">
              <span className="dashboard-section-title">Critical Reorder Alerts</span>
              <span
                className="badge"
                style={{
                  background: data.low_stock_alerts_count > 0 ? 'rgba(244, 63, 94, 0.15)' : 'rgba(16, 185, 129, 0.15)',
                  color: data.low_stock_alerts_count > 0 ? '#f43f5e' : '#10b981',
                }}
              >
                {data.low_stock_alerts_count} ALERT{data.low_stock_alerts_count !== 1 ? 'S' : ''}
              </span>
            </div>

            {data.low_stock_items.length === 0 ? (
              <div className="empty-state-text" style={{ padding: '1rem 0' }}>
                🎉 All stock counts are above warning thresholds.
              </div>
            ) : (
              <div style={{ maxHeight: '250px', overflowY: 'auto' }}>
                {data.low_stock_items.map((item) => (
                  <div className="alert-item-container" key={item.id}>
                    <div className="alert-item-details">
                      <span className="alert-item-title">{item.name}</span>
                      <span className="alert-item-meta">
                        SKU: {item.sku} | Threshold: {item.reorder_threshold}
                      </span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <span
                        style={{
                          fontSize: '0.85rem',
                          fontWeight: 700,
                          color: '#f87171',
                        }}
                      >
                        {item.current_stock} left
                      </span>
                      <span className="alert-action-badge">REORDER</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Subcard 4: Recent activity */}
          <div className="dashboard-section-card">
            <div className="dashboard-section-title-row">
              <span className="dashboard-section-title">Inventory Velocity Feed</span>
              <span className="text-muted" style={{ fontSize: '0.8rem' }}>
                Last 5 Movements
              </span>
            </div>

            {data.recent_activity.length === 0 ? (
              <div className="empty-state-text">No inventory transactions logged.</div>
            ) : (
              <div className="timeline-list">
                {data.recent_activity.map((activity) => {
                  const isIn = activity.quantity_change > 0;
                  const absQty = Math.abs(activity.quantity_change);
                  return (
                    <div className="timeline-item-modern" key={activity.id}>
                      <div
                        className={`timeline-marker ${
                          isIn ? 'timeline-marker-in' : 'timeline-marker-out'
                        }`}
                      ></div>
                      <div className="timeline-card">
                        <div className="timeline-header-row">
                          <span className="timeline-title-text">{activity.product_name}</span>
                          <span
                            className={`timeline-qty-change ${
                              isIn ? 'qty-positive' : 'qty-negative'
                            }`}
                          >
                            {isIn ? '+' : '-'}{absQty}
                          </span>
                        </div>
                        <div className="timeline-meta-row">
                          <span>
                            {activity.type} ({activity.condition})
                          </span>
                          <span>
                            {new Date(activity.created_at).toLocaleTimeString([], {
                              hour: '2-digit',
                              minute: '2-digit',
                            })}
                          </span>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
