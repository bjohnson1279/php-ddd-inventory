import React, { useEffect, useState } from 'react';
import api from '../api/client';
import Spinner from '../components/Spinner';

export default function Inventory() {
  // Stock Ops
  const [opType, setOpType] = useState<'receive' | 'dispatch' | 'transfer'>('receive');
  const [sku, setSku] = useState('');
  const [quantity, setQuantity] = useState('');
  const [locationId, setLocationId] = useState('LOC-STOREFRONT');
  const [toLocationId, setToLocationId] = useState('LOC-BACKROOM');
  const [opMsg, setOpMsg] = useState('');
  const [isProcessingOp, setIsProcessingOp] = useState(false);

  // Stock Query
  const [querySku, setQuerySku] = useState('');
  const [queryLoc, setQueryLoc] = useState('LOC-STOREFRONT');
  const [stockLevel, setStockLevel] = useState<number | null>(null);
  const [queryMsg, setQueryMsg] = useState('');

  // Count Sessions
  const [activeCountId, setActiveCountId] = useState<string | null>(() => localStorage.getItem('active_count_id'));
  const [countSku, setCountSku] = useState('');
  const [countQty, setCountQty] = useState('');
  const [countItems, setCountItems] = useState<{ sku: string; quantity: number }[]>([]);
  const [countMsg, setCountMsg] = useState('');

  const [isCheckingStock, setIsCheckingStock] = useState(false);
  const [isStartingCount, setIsStartingCount] = useState(false);
  const [isRecordingItem, setIsRecordingItem] = useState(false);
  const [isCompletingCount, setIsCompletingCount] = useState(false);

  const handleStockOp = async (e: React.FormEvent) => {
    e.preventDefault();
    setOpMsg('Processing transaction...');
    setIsProcessingOp(true);
    try {
      const qty = parseInt(quantity) || 0;
      if (opType === 'receive') {
        await api.post('/inventory/receive', { sku, quantity: qty, location_id: locationId });
        setOpMsg('Stock received successfully!');
      } else if (opType === 'dispatch') {
        await api.post('/inventory/dispatch', { sku, quantity: qty, location_id: locationId });
        setOpMsg('Stock dispatched successfully!');
      } else {
        await api.post('/inventory/transfer', { sku, quantity: qty, from_location_id: locationId, to_location_id: toLocationId });
        setOpMsg('Stock transferred successfully!');
      }
      setSku('');
      setQuantity('');
    } catch (err: any) {
      setOpMsg(err.message || 'Transaction failed');
    } finally {
      setIsProcessingOp(false);
    }
  };

  const checkStock = async (e: React.FormEvent) => {
    e.preventDefault();
    setStockLevel(null);
    setQueryMsg('Querying ledger...');
    setIsCheckingStock(true);
    try {
      const res = await api.get(`/inventory/${querySku}/stock?location_id=${queryLoc}`);
      if (res.stock !== undefined) {
        setStockLevel(res.stock);
        setQueryMsg('');
      } else {
        setQueryMsg('Could not read stock response');
      }
    } catch (err: any) {
      setQueryMsg(err.message || 'Error checking stock');
    } finally {
      setIsCheckingStock(false);
    }
  };

  const startCount = async () => {
    setCountMsg('Starting session...');
    setIsStartingCount(true);
    try {
      const res = await api.post('/inventory/counts');
      if (res.count_id) {
        setActiveCountId(res.count_id);
        localStorage.setItem('active_count_id', res.count_id);
        setCountItems([]);
        setCountMsg('Session started');
      }
    } catch (err: any) {
      setCountMsg(err.message || 'Error starting count');
    } finally {
      setIsStartingCount(false);
    }
  };

  const recordCountItem = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeCountId) return;
    setCountMsg('Recording item...');
    setIsRecordingItem(true);
    try {
      const qty = parseInt(countQty) || 0;
      await api.post(`/inventory/counts/${activeCountId}/items`, { sku: countSku, quantity: qty });
      setCountItems([...countItems, { sku: countSku, quantity: qty }]);
      setCountSku('');
      setCountQty('');
      setCountMsg('Item recorded!');
    } catch (err: any) {
      setCountMsg(err.message || 'Error recording item');
    } finally {
      setIsRecordingItem(false);
    }
  };

  const completeCount = async () => {
    if (!activeCountId) return;
    setCountMsg('Completing count & reconciling...');
    setIsCompletingCount(true);
    try {
      await api.post(`/inventory/counts/${activeCountId}/complete`);
      setCountMsg('Count completed and stock reconciled successfully!');
      setActiveCountId(null);
      localStorage.removeItem('active_count_id');
      setCountItems([]);
    } catch (err: any) {
      setCountMsg(err.message || 'Reconciliation failed');
    } finally {
      setIsCompletingCount(false);
    }
  };

  return (
    <div>
      <h2 style={{ marginBottom: '1.5rem', fontWeight: 600 }}>Ledger Stock Operations</h2>

      <div className="grid-2">
        {/* Left Column: Transaction Forms */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Operations block */}
          <div className="card-lite">
            <div className="section-title">Stock Transaction</div>
            <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1rem' }}>
              <button className={`btn-sm ${opType === 'receive' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setOpType('receive')}>Receive</button>
              <button className={`btn-sm ${opType === 'dispatch' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setOpType('dispatch')}>Dispatch</button>
              <button className={`btn-sm ${opType === 'transfer' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setOpType('transfer')}>Transfer</button>
            </div>

            <form onSubmit={handleStockOp}>
              <div className="form-group">
                <label htmlFor="opSku">SKU</label>
                <input id="opSku" value={sku} onChange={e => setSku(e.target.value)} placeholder="e.g. DNM-JKT-BLU-M" required />
              </div>
              <div className="form-group">
                <label htmlFor="opQuantity">Quantity</label>
                <input id="opQuantity" type="number" min="1" value={quantity} onChange={e => setQuantity(e.target.value)} placeholder="e.g. 10" required />
              </div>
              <div className="form-group">
                <label htmlFor="opLocationId">{opType === 'transfer' ? 'From Location' : 'Location'}</label>
                <select id="opLocationId" value={locationId} onChange={e => setLocationId(e.target.value)}>
                  <option value="LOC-STOREFRONT">Sales Floor (LOC-STOREFRONT)</option>
                  <option value="LOC-BACKROOM">Backroom Storage (LOC-BACKROOM)</option>
                </select>
              </div>
              {opType === 'transfer' && (
                <div className="form-group">
                  <label htmlFor="opToLocationId">To Location</label>
                  <select id="opToLocationId" value={toLocationId} onChange={e => setToLocationId(e.target.value)}>
                    <option value="LOC-BACKROOM">Backroom Storage (LOC-BACKROOM)</option>
                    <option value="LOC-STOREFRONT">Sales Floor (LOC-STOREFRONT)</option>
                  </select>
                </div>
              )}
              <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isProcessingOp} aria-busy={isProcessingOp}>
                {isProcessingOp && <Spinner />} {isProcessingOp ? 'Processing...' : opType === 'receive' ? 'Receive Stock' : opType === 'dispatch' ? 'Dispatch Stock' : 'Transfer Stock'}
              </button>
            </form>
            <p style={{ color: opMsg.includes('failed') || opMsg.includes('Error') ? '#f87171' : '#34d399' }}>{opMsg}</p>
          </div>

          {/* Query block */}
          <div className="card-lite">
            <div className="section-title">Query Stock Level</div>
            <form onSubmit={checkStock}>
              <div className="form-group">
                <label htmlFor="querySku">SKU</label>
                <input id="querySku" value={querySku} onChange={e => setQuerySku(e.target.value)} placeholder="e.g. DNM-JKT-BLU-M" required />
              </div>
              <div className="form-group">
                <label htmlFor="queryLoc">Location Context</label>
                <select id="queryLoc" value={queryLoc} onChange={e => setQueryLoc(e.target.value)}>
                  <option value="LOC-STOREFRONT">Sales Floor</option>
                  <option value="LOC-BACKROOM">Backroom Storage</option>
                  <option value="ALL">All Combined (ALL)</option>
                </select>
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isCheckingStock} aria-busy={isCheckingStock}>
                {isCheckingStock && <Spinner />} {isCheckingStock ? 'Checking...' : 'Check Level'}
              </button>
            </form>
            {stockLevel !== null && (
              <div style={{ marginTop: '1.25rem', padding: '1rem', background: 'rgba(255,255,255,0.02)', borderRadius: '8px', border: '1px solid rgba(255,255,255,0.05)', textAlign: 'center' }}>
                <span className="text-muted">Stock Level:</span>
                <div style={{ fontSize: '2rem', fontWeight: 700, color: '#818cf8', marginTop: '0.25rem' }}>{stockLevel} units</div>
              </div>
            )}
            <p style={{ color: '#f87171' }}>{queryMsg}</p>
          </div>
        </div>

        {/* Right Column: Physical Counts Session */}
        <div>
          <div className="card-lite">
            <div className="section-title">Stock Count / Reconciliation</div>
            {!activeCountId ? (
              <div style={{ textAlign: 'center', padding: '2rem 0' }}>
                <p className="text-muted" style={{ marginBottom: '1.5rem' }}>No active inventory count session running.</p>
                <button onClick={startCount} className="btn-primary" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem', margin: '0 auto' }} disabled={isStartingCount} aria-busy={isStartingCount}>
                  {isStartingCount && <Spinner />} {isStartingCount ? 'Starting...' : 'Start New Count Session'}
                </button>
              </div>
            ) : (
              <div>
                <div style={{ padding: '0.75rem', background: 'rgba(99, 102, 241, 0.1)', border: '1px dashed rgba(99, 102, 241, 0.3)', borderRadius: '8px', marginBottom: '1.5rem' }}>
                  <div className="text-muted" style={{ fontSize: '0.75rem' }}>Active Session ID:</div>
                  <div style={{ fontSize: '0.85rem', fontWeight: 600, color: '#fff' }}>{activeCountId}</div>
                </div>

                <form onSubmit={recordCountItem} style={{ marginBottom: '1.5rem' }}>
                  <div className="section-title" style={{ fontSize: '1rem', border: 'none' }}>Record Item Count</div>
                  <div className="form-group">
                    <label htmlFor="countSku">SKU</label>
                    <input id="countSku" value={countSku} onChange={e => setCountSku(e.target.value)} placeholder="SKU to count" required />
                  </div>
                  <div className="form-group">
                    <label htmlFor="countQty">Counted Quantity</label>
                    <input id="countQty" type="number" min="0" value={countQty} onChange={e => setCountQty(e.target.value)} placeholder="Quantity found" required />
                  </div>
                  <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isRecordingItem} aria-busy={isRecordingItem}>
                    {isRecordingItem && <Spinner />} {isRecordingItem ? 'Submitting...' : 'Submit Item Count'}
                  </button>
                </form>

                {countItems.length > 0 && (
                  <div style={{ marginBottom: '1.5rem' }}>
                    <div className="text-muted" style={{ fontWeight: 600, marginBottom: '0.5rem' }}>Recorded in this Session:</div>
                    <ul style={{ border: '1px solid rgba(255,255,255,0.05)' }}>
                      {countItems.map((item, idx) => (
                        <li key={idx} style={{ padding: '0.5rem 0.75rem', fontSize: '0.85rem' }}>
                          <span style={{ fontWeight: 600 }}>{item.sku}</span>
                          <span>{item.quantity} units</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                <button onClick={completeCount} className="btn-primary" style={{ width: '100%', background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)', boxShadow: '0 4px 12px rgba(16, 185, 129, 0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isCompletingCount} aria-busy={isCompletingCount}>
                  {isCompletingCount && <Spinner />} {isCompletingCount ? 'Completing...' : 'Complete Session & Reconcile'}
                </button>
              </div>
            )}
            <p style={{ color: countMsg.includes('failed') || countMsg.includes('Error') ? '#f87171' : '#34d399' }}>{countMsg}</p>
          </div>
        </div>
      </div>
    </div>
  );
}
