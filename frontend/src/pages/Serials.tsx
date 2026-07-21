import React, { useEffect, useState } from 'react';
import api from '../api/client';
import Spinner from '../components/Spinner';

type Transition = {
  from: string;
  to: string;
  reason: string;
  actorId: string;
  referenceId: string | null;
  occurredAt: string;
};

type SerialItem = {
  id: string;
  variant_id: string;
  serial_number: string;
  location_id: string | null;
  status: string;
  history: Transition[];
};

export default function Serials() {
  const [serials, setSerials] = useState<SerialItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedSerial, setSelectedSerial] = useState<SerialItem | null>(null);

  // Register Serial Form
  const [regVariantId, setRegVariantId] = useState('');
  const [regSerial, setRegSerial] = useState('');
  const [regLocation, setRegLocation] = useState('LOC-STOREFRONT');
  const [regMsg, setRegMsg] = useState('');

  // Action Panel Form
  const [actionType, setActionType] = useState<'receive' | 'sell' | 'return' | 'restock' | 'write-off'>('receive');
  // Common inputs
  const [actLocation, setActLocation] = useState('LOC-STOREFRONT');
  const [actPoId, setActPoId] = useState('');
  const [actCostCents, setActCostCents] = useState('2000'); // $20.00
  const [actSaleId, setActSaleId] = useState('');
  const [actReturnId, setActReturnId] = useState('');
  const [actReason, setActReason] = useState('');
  const [actMsg, setActMsg] = useState('');

  const [isRegistering, setIsRegistering] = useState(false);
  const [isProcessingAction, setIsProcessingAction] = useState(false);

  useEffect(() => {
    fetchSerials();
  }, []);

  const fetchSerials = async () => {
    try {
      setLoading(true);
      const res = await api.get('/serials');
      setSerials(res.items || []);
    } catch (e: any) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setRegMsg('Registering...');
    setIsRegistering(true);
    try {
      await api.post('/serials', {
        variant_id: regVariantId,
        serial_number: regSerial,
        location_id: regLocation,
      });
      setRegMsg('Serial registered successfully!');
      setRegSerial('');
      fetchSerials();
    } catch (err: any) {
      setRegMsg(err.message || 'Registration failed');
    } finally {
      setIsRegistering(false);
    }
  };

  const handleAction = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSerial) {
      setActMsg('No serial selected.');
      return;
    }
    setActMsg('Processing action...');
    setIsProcessingAction(true);
    try {
      let endpoint = `/serials/${selectedSerial.id}`;
      let body: any = {};

      if (actionType === 'receive') {
        endpoint += '/receive';
        body = {
          location_id: actLocation,
          purchase_order_id: actPoId,
          unit_cost_cents: parseInt(actCostCents) || 0
        };
      } else if (actionType === 'sell') {
        endpoint += '/sell';
        body = { sale_id: actSaleId };
      } else if (actionType === 'return') {
        endpoint += '/return';
        body = { return_id: actReturnId };
      } else if (actionType === 'restock') {
        endpoint += '/restock';
        body = {
          return_id: actReturnId,
          restocked_unit_cost_cents: parseInt(actCostCents) || 0
        };
      } else if (actionType === 'write-off') {
        endpoint += '/write-off';
        body = { reason: actReason };
      }

      await api.post(endpoint, body);
      setActMsg('Status transitioned successfully!');
      // Reset forms
      setActPoId('');
      setActSaleId('');
      setActReturnId('');
      setActReason('');
      
      // Refresh items and update selected
      const updatedRes = await api.get('/serials');
      const updatedItems = updatedRes.items || [];
      setSerials(updatedItems);
      const matched = updatedItems.find((s: SerialItem) => s.id === selectedSerial.id);
      if (matched) setSelectedSerial(matched);

    } catch (err: any) {
      setActMsg(err.message || 'Action execution failed');
    } finally {
      setIsProcessingAction(false);
    }
  };

  return (
    <div>
      <h2 style={{ marginBottom: '1.5rem', fontWeight: 600 }}>Serial Number Lifecycle tracking</h2>

      <div className="grid-2">
        {/* Left Column: Registered Serials List & Registry form */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          <div className="card-lite">
            <div className="section-title">Register Serial Number</div>
            <form onSubmit={handleRegister}>
              <div className="form-group">
                <label htmlFor="regVariantId">Variant ID (UUID)</label>
                <input id="regVariantId" value={regVariantId} onChange={e => setRegVariantId(e.target.value)} placeholder="e.g. 550e8400-e29b-41d4-a716-446655440000" required />
              </div>
              <div className="form-group">
                <label htmlFor="regSerial">Serial Number (SN)</label>
                <input id="regSerial" value={regSerial} onChange={e => setRegSerial(e.target.value)} placeholder="e.g. SN-IPHONE15-8823" required />
              </div>
              <div className="form-group">
                <label htmlFor="regLocation">Initial Location</label>
                <select id="regLocation" value={regLocation} onChange={e => setRegLocation(e.target.value)}>
                  <option value="LOC-STOREFRONT">Sales Floor</option>
                  <option value="LOC-BACKROOM">Backroom Storage</option>
                </select>
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isRegistering} aria-busy={isRegistering}>
                {isRegistering && <Spinner />} {isRegistering ? 'Registering...' : 'Register Serial'}
              </button>
            </form>
            <p style={{ color: regMsg.includes('failed') || regMsg.includes('Error') ? '#f87171' : '#34d399' }}>{regMsg}</p>
          </div>

          <div className="card-lite">
            <div className="section-title">Registered Serial Units</div>
            {loading ? (
              <div className="text-muted">Loading serial numbers...</div>
            ) : serials.length === 0 ? (
              <div className="text-muted">No serial units registered yet.</div>
            ) : (
              <table style={{ fontSize: '0.85rem' }}>
                <thead>
                  <tr>
                    <th>Serial Number</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {serials.map((s) => (
                    <tr key={s.id} style={{ cursor: 'pointer' }} onClick={() => setSelectedSerial(s)}>
                      <td style={{ fontWeight: 600, color: selectedSerial?.id === s.id ? '#818cf8' : '#fff' }}>{s.serial_number}</td>
                      <td>
                        <span className={`badge badge-${s.status.replace('-', '')}`}>{s.status}</span>
                      </td>
                      <td>{s.location_id || 'None'}</td>
                      <td>
                        <button className="btn-sm btn-secondary" onClick={(e) => { e.stopPropagation(); setSelectedSerial(s); }}>Manage</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        {/* Right Column: Serial Management & Action Panel */}
        <div>
          {selectedSerial ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
              {/* Unit Card Detail */}
              <div className="card-lite" style={{ borderLeft: '4px solid #6366f1' }}>
                <div className="section-title" style={{ border: 'none', marginBottom: '0.5rem' }}>Serial Details</div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Serial Number:</span>
                  <strong style={{ color: '#fff' }}>{selectedSerial.serial_number}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Status:</span>
                  <span className={`badge badge-${selectedSerial.status.replace('-', '')}`}>{selectedSerial.status}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Location:</span>
                  <span style={{ color: '#fff' }}>{selectedSerial.location_id || 'Not Set'}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span className="text-muted">Variant ID:</span>
                  <span style={{ fontSize: '0.75rem', fontFamily: 'monospace' }}>{selectedSerial.variant_id}</span>
                </div>
              </div>

              {/* Status Transition Action Panel */}
              <div className="card-lite">
                <div className="section-title">Perform Lifecycle Action</div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '1.25rem' }}>
                  {['receive', 'sell', 'return', 'restock', 'write-off'].map((t) => (
                    <button key={t} className={`btn-sm ${actionType === t ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setActionType(t as any)}>
                      {t}
                    </button>
                  ))}
                </div>

                <form onSubmit={handleAction}>
                  {actionType === 'receive' && (
                    <>
                      <div className="form-group">
                        <label htmlFor="actPoId">PO Number</label>
                        <input id="actPoId" value={actPoId} onChange={e => setActPoId(e.target.value)} placeholder="PO-1020" required />
                      </div>
                      <div className="form-group">
                        <label htmlFor="actLocation">Location</label>
                        <select id="actLocation" value={actLocation} onChange={e => setActLocation(e.target.value)}>
                          <option value="LOC-STOREFRONT">Sales Floor</option>
                          <option value="LOC-BACKROOM">Backroom Storage</option>
                        </select>
                      </div>
                      <div className="form-group">
                        <label htmlFor="actCostCents">Unit Cost (in Cents)</label>
                        <input id="actCostCents" type="number" value={actCostCents} onChange={e => setActCostCents(e.target.value)} placeholder="e.g. 2500 for $25.00" required />
                      </div>
                    </>
                  )}

                  {actionType === 'sell' && (
                    <div className="form-group">
                      <label htmlFor="actSaleId">Sale ID / Invoice</label>
                      <input id="actSaleId" value={actSaleId} onChange={e => setActSaleId(e.target.value)} placeholder="SALE-9912" required />
                    </div>
                  )}

                  {actionType === 'return' && (
                    <div className="form-group">
                      <label htmlFor="actReturnId">Return Claim ID</label>
                      <input id="actReturnId" value={actReturnId} onChange={e => setActReturnId(e.target.value)} placeholder="RET-8812" required />
                    </div>
                  )}

                  {actionType === 'restock' && (
                    <>
                      <div className="form-group">
                        <label htmlFor="actRestockReturnId">Associated Return Claim ID</label>
                        <input id="actRestockReturnId" value={actReturnId} onChange={e => setActReturnId(e.target.value)} placeholder="RET-8812" required />
                      </div>
                      <div className="form-group">
                        <label htmlFor="actRestockCostCents">Re-boarding Value (in Cents)</label>
                        <input id="actRestockCostCents" type="number" value={actCostCents} onChange={e => setActCostCents(e.target.value)} placeholder="e.g. 2400 for $24.00" required />
                      </div>
                    </>
                  )}

                  {actionType === 'write-off' && (
                    <div className="form-group">
                      <label htmlFor="actReason">Write-Off Reason Details</label>
                      <input id="actReason" value={actReason} onChange={e => setActReason(e.target.value)} placeholder="e.g. Damaged in-store inspection" required />
                    </div>
                  )}

                  <button type="submit" className="btn-primary" style={{ width: '100%', textTransform: 'capitalize', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isProcessingAction} aria-busy={isProcessingAction}>
                    {isProcessingAction && <Spinner />} {isProcessingAction ? 'Processing...' : `Process ${actionType.replace('-', ' ')}`}
                  </button>
                </form>
                <p style={{ color: actMsg.includes('failed') || actMsg.includes('Error') ? '#f87171' : '#34d399' }}>{actMsg}</p>
              </div>

              {/* History Timeline */}
              {selectedSerial.history && selectedSerial.history.length > 0 && (
                <div className="card-lite">
                  <div className="section-title" style={{ fontSize: '1rem' }}>Lifecycle Timeline History</div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem', marginTop: '0.5rem' }}>
                    {selectedSerial.history.map((h, i) => (
                      <div key={i} style={{ borderLeft: '2px solid rgba(255,255,255,0.08)', paddingLeft: '1rem', position: 'relative' }}>
                        <div style={{ position: 'absolute', left: '-5px', top: '5px', width: '8px', height: '8px', borderRadius: '50%', background: '#818cf8' }}></div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem' }}>
                          <span style={{ fontWeight: 600 }}>{h.from} ➜ {h.to}</span>
                          <span className="text-muted">{new Date(h.occurredAt).toLocaleDateString()}</span>
                        </div>
                        <div style={{ fontSize: '0.8rem', color: '#9ca3af', marginTop: '0.15rem' }}>
                          Reason: <em>{h.reason}</em>
                        </div>
                        {h.referenceId && (
                          <div style={{ fontSize: '0.75rem', color: '#818cf8' }}>Ref: {h.referenceId}</div>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="card-lite" style={{ textAlign: 'center', padding: '4rem 0' }}>
              <p className="text-muted">Select a serial number on the left to review its lifecycle and trigger transitions.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
