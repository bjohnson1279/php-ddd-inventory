import React, { useEffect, useState } from 'react';
import api from '../api/client';

type OnboardingItem = {
  id: string;
  variant_id: string;
  quantity: number;
  unit_cost_cents: number;
};

type OnboardingSession = {
  id: string;
  location_id: string;
  as_of_date: string;
  status: 'draft' | 'submitted';
  items?: OnboardingItem[];
};

export default function Onboarding() {
  const [sessions, setSessions] = useState<OnboardingSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedSession, setSelectedSession] = useState<OnboardingSession | null>(null);

  // New Onboarding Session Form
  const [newLocation, setNewLocation] = useState('LOC-STOREFRONT');
  const [newAsOfDate, setNewAsOfDate] = useState('2026-05-30');
  const [sessionMsg, setSessionMsg] = useState('');

  // Add Item to Onboarding Form
  const [itemVariantId, setItemVariantId] = useState('');
  const [itemQty, setItemQty] = useState('');
  const [itemCostCents, setItemCostCents] = useState('1500'); // $15.00
  const [itemMsg, setItemMsg] = useState('');

  useEffect(() => {
    fetchSessions();
  }, []);

  const fetchSessions = async () => {
    try {
      setLoading(true);
      const res = await api.get('/onboardings');
      setSessions(res.onboardings || []);
    } catch (e: any) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateSession = async (e: React.FormEvent) => {
    e.preventDefault();
    setSessionMsg('Creating onboarding session...');
    try {
      const res = await api.post('/onboardings', {
        location_id: newLocation,
        as_of_date: newAsOfDate
      });
      setSessionMsg('Onboarding session created successfully!');
      fetchSessions();
      // Select the newly created draft session
      if (res.id) {
        handleSelectSession(res.id);
      }
    } catch (err: any) {
      setSessionMsg(err.message || 'Error creating session');
    }
  };

  const handleSelectSession = async (id: string) => {
    try {
      const res = await api.get(`/onboardings/${id}`);
      setSelectedSession(res);
    } catch (err: any) {
      console.error(err);
    }
  };

  const handleAddItem = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSession) return;
    setItemMsg('Adding item...');
    try {
      await api.post(`/onboardings/${selectedSession.id}/items`, {
        variant_id: itemVariantId,
        quantity: parseInt(itemQty) || 0,
        unit_cost_cents: parseInt(itemCostCents) || 0
      });
      setItemMsg('Item added successfully!');
      setItemVariantId('');
      setItemQty('');
      handleSelectSession(selectedSession.id);
    } catch (err: any) {
      setItemMsg(err.message || 'Error adding item');
    }
  };

  const handleRemoveItem = async (variantId: string) => {
    if (!selectedSession) return;
    try {
      await api.delete(`/onboardings/${selectedSession.id}/items/${variantId}`);
      handleSelectSession(selectedSession.id);
    } catch (err: any) {
      console.error(err);
    }
  };

  const handleSubmitSession = async () => {
    if (!selectedSession) return;
    setItemMsg('Submitting and reconciling balances...');
    try {
      await api.post(`/onboardings/${selectedSession.id}/submit`);
      setItemMsg('Onboarding session submitted & opening balances posted successfully!');
      fetchSessions();
      handleSelectSession(selectedSession.id);
    } catch (err: any) {
      setItemMsg(err.message || 'Submission failed');
    }
  };

  return (
    <div>
      <h2 style={{ marginBottom: '1.5rem', fontWeight: 600 }}>Opening Balances / Stock Onboarding</h2>

      <div className="grid-2">
        {/* Left Column: Onboarding Sessions List & Create Form */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Create Session Card */}
          <div className="card-lite">
            <div className="section-title">New Onboarding Session</div>
            <form onSubmit={handleCreateSession}>
              <div className="form-group">
                <label htmlFor="newLocation">Location</label>
                <select id="newLocation" value={newLocation} onChange={e => setNewLocation(e.target.value)}>
                  <option value="LOC-STOREFRONT">Sales Floor</option>
                  <option value="LOC-BACKROOM">Backroom Storage</option>
                </select>
              </div>
              <div className="form-group">
                <label htmlFor="newAsOfDate">As Of Date</label>
                <input id="newAsOfDate" type="date" value={newAsOfDate} onChange={e => setNewAsOfDate(e.target.value)} required />
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%' }}>Create Onboarding Session</button>
            </form>
            <p style={{ color: sessionMsg.includes('Error') ? '#f87171' : '#34d399' }}>{sessionMsg}</p>
          </div>

          {/* List Card */}
          <div className="card-lite">
            <div className="section-title">Onboarding Sessions</div>
            {loading ? (
              <div className="text-muted">Loading sessions...</div>
            ) : sessions.length === 0 ? (
              <div className="text-muted">No onboarding sessions started yet.</div>
            ) : (
              <table style={{ fontSize: '0.85rem' }}>
                <thead>
                  <tr>
                    <th>As Of Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {sessions.map((s) => (
                    <tr key={s.id} style={{ cursor: 'pointer' }} onClick={() => handleSelectSession(s.id)}>
                      <td style={{ fontWeight: 600, color: selectedSession?.id === s.id ? '#818cf8' : '#fff' }}>{s.as_of_date}</td>
                      <td>{s.location_id}</td>
                      <td>
                        <span className={`badge badge-${s.status}`}>{s.status}</span>
                      </td>
                      <td>
                        <button className="btn-sm btn-secondary" onClick={(e) => { e.stopPropagation(); handleSelectSession(s.id); }}>Review</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        {/* Right Column: Onboarding Details & Item Posting */}
        <div>
          {selectedSession ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
              {/* Session Detail Summary */}
              <div className="card-lite" style={{ borderLeft: '4px solid #f59e0b' }}>
                <div className="section-title" style={{ border: 'none', marginBottom: '0.5rem' }}>Session Details</div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Session ID:</span>
                  <span style={{ fontSize: '0.8rem', fontFamily: 'monospace' }}>{selectedSession.id}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Location:</span>
                  <strong style={{ color: '#fff' }}>{selectedSession.location_id}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">As Of Date:</span>
                  <strong style={{ color: '#fff' }}>{selectedSession.as_of_date}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span className="text-muted">Status:</span>
                  <span className={`badge badge-${selectedSession.status}`}>{selectedSession.status}</span>
                </div>
              </div>

              {/* Add Onboarding Item Form (only if draft) */}
              {selectedSession.status === 'draft' && (
                <div className="card-lite">
                  <div className="section-title">Add Onboarding Item</div>
                  <form onSubmit={handleAddItem}>
                    <div className="form-group">
                      <label htmlFor="itemVariantId">Variant ID (UUID)</label>
                      <input id="itemVariantId" value={itemVariantId} onChange={e => setItemVariantId(e.target.value)} placeholder="e.g. 550e8400-e29b-41d4-a716-446655440000" required />
                    </div>
                    <div className="grid-2">
                      <div className="form-group">
                        <label htmlFor="itemQty">Opening Quantity</label>
                        <input id="itemQty" type="number" min="0" value={itemQty} onChange={e => setItemQty(e.target.value)} placeholder="e.g. 100" required />
                      </div>
                      <div className="form-group">
                        <label htmlFor="itemCostCents">Unit Cost (in Cents)</label>
                        <input id="itemCostCents" type="number" min="0" value={itemCostCents} onChange={e => setItemCostCents(e.target.value)} placeholder="e.g. 1250 for $12.50" required />
                      </div>
                    </div>
                    <button type="submit" className="btn-primary" style={{ width: '100%' }}>Add Item to Onboarding</button>
                  </form>
                  <p style={{ color: itemMsg.includes('Error') || itemMsg.includes('failed') ? '#f87171' : '#34d399' }}>{itemMsg}</p>
                </div>
              )}

              {/* Items Table list */}
              <div className="card-lite">
                <div className="section-title" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <span>Onboarding Items</span>
                  {selectedSession.status === 'draft' && selectedSession.items && selectedSession.items.length > 0 && (
                    <button onClick={handleSubmitSession} className="btn-sm btn-primary" style={{ background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)' }}>
                      Submit & Lock
                    </button>
                  )}
                </div>
                {!selectedSession.items || selectedSession.items.length === 0 ? (
                  <p className="text-muted" style={{ textAlign: 'center', padding: '1rem 0' }}>No items added to this onboarding session yet.</p>
                ) : (
                  <table style={{ fontSize: '0.85rem' }}>
                    <thead>
                      <tr>
                        <th>Variant ID</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        {selectedSession.status === 'draft' && <th>Action</th>}
                      </tr>
                    </thead>
                    <tbody>
                      {selectedSession.items.map((item) => (
                        <tr key={item.id}>
                          <td style={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{item.variant_id}</td>
                          <td style={{ fontWeight: 600 }}>{item.quantity}</td>
                          <td>${(item.unit_cost_cents / 100).toFixed(2)}</td>
                          {selectedSession.status === 'draft' && (
                            <td>
                              <button aria-label={`Remove variant ${item.variant_id} from session`} onClick={() => handleRemoveItem(item.variant_id)} className="btn-sm btn-secondary text-danger" style={{ padding: '0.2rem 0.5rem' }}>
                                Remove
                              </button>
                            </td>
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          ) : (
            <div className="card-lite" style={{ textAlign: 'center', padding: '4rem 0' }}>
              <p className="text-muted">Select an onboarding session on the left to add items or finalize the onboarding batch.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
