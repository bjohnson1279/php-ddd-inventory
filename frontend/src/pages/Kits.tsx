import React, { useEffect, useState } from 'react';
import api from '../api/client';

type KitComponent = {
  id: string;
  kit_id: string;
  variant_id: string;
  quantity: number;
};

type Kit = {
  id: string;
  sku: string;
  name: string;
  components: KitComponent[];
};

export default function Kits() {
  const [kits, setKits] = useState<Kit[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedKit, setSelectedKit] = useState<Kit | null>(null);

  // New Kit Form
  const [kitSku, setKitSku] = useState('');
  const [kitName, setKitName] = useState('');
  const [createMsg, setCreateMsg] = useState('');
  const [isCreatingKit, setIsCreatingKit] = useState(false);

  // Add Component Form
  const [compVariantId, setCompVariantId] = useState('');
  const [compQty, setCompQty] = useState('1');
  const [compMsg, setCompMsg] = useState('');
  const [isAddingComponent, setIsAddingComponent] = useState(false);

  // Sell Kit Bundle Form
  const [sellQty, setSellQty] = useState('1');
  const [sellSaleId, setSellSaleId] = useState('');
  const [sellMsg, setSellMsg] = useState('');
  const [isSellingKit, setIsSellingKit] = useState(false);

  useEffect(() => {
    fetchKits();
  }, []);

  const fetchKits = async () => {
    try {
      setLoading(true);
      const res = await api.get('/kits');
      setKits(res.kits || []);
    } catch (e: any) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateKit = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateMsg('');
    setIsCreatingKit(true);
    try {
      const res = await api.post('/kits', { sku: kitSku, name: kitName });
      setCreateMsg('Kit aggregate created successfully!');
      setKitSku('');
      setKitName('');
      fetchKits();
      if (res.id) {
        handleSelectKit(res.id);
      }
    } catch (err: any) {
      setCreateMsg(err.message || 'Error creating Kit');
    } finally {
      setIsCreatingKit(false);
    }
  };

  const handleSelectKit = async (id: string) => {
    try {
      const res = await api.get(`/kits/${id}`);
      setSelectedKit(res);
    } catch (err: any) {
      console.error(err);
    }
  };

  const handleAddComponent = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedKit) return;
    setCompMsg('');
    setIsAddingComponent(true);
    try {
      await api.post(`/kits/${selectedKit.id}/components`, {
        variant_id: compVariantId,
        quantity: parseInt(compQty) || 1
      });
      setCompMsg('Component registered!');
      setCompVariantId('');
      setCompQty('1');
      handleSelectKit(selectedKit.id);
      fetchKits();
    } catch (err: any) {
      setCompMsg(err.message || 'Failed to add component');
    } finally {
      setIsAddingComponent(false);
    }
  };

  const handleSellKit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedKit) return;
    setSellMsg('');
    setIsSellingKit(true);
    try {
      await api.post(`/kits/${selectedKit.id}/sell`, {
        quantity: parseInt(sellQty) || 1,
        sale_id: sellSaleId
      });
      setSellMsg('Kit sold successfully! Subcomponent stock levels updated.');
      setSellSaleId('');
      setSellQty('1');
    } catch (err: any) {
      setSellMsg(err.message || 'Kit selling failed. Ensure subcomponents have enough stock!');
    } finally {
      setIsSellingKit(false);
    }
  };

  return (
    <div>
      <h2 style={{ marginBottom: '1.5rem', fontWeight: 600 }}>Kitting & Bundles</h2>

      <div className="grid-2">
        {/* Left Column: Kits list & Create Kit Form */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Create Kit Card */}
          <div className="card-lite">
            <div className="section-title">Assemble New Kit Bundle</div>
            <form onSubmit={handleCreateKit}>
              <div className="form-group">
                <label htmlFor="kitSku">Bundle SKU</label>
                <input id="kitSku" value={kitSku} onChange={e => setKitSku(e.target.value)} placeholder="e.g. KIT-SUMMER-BUNDLE" required />
              </div>
              <div className="form-group">
                <label htmlFor="kitName">Bundle Name</label>
                <input id="kitName" value={kitName} onChange={e => setKitName(e.target.value)} placeholder="e.g. Summer Essentials Pack" required />
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%' }} disabled={isCreatingKit} aria-busy={isCreatingKit}>
                {isCreatingKit ? 'Creating...' : 'Create Kit Bundle'}
              </button>
            </form>
            <p style={{ color: createMsg.includes('Error') ? '#f87171' : '#34d399' }}>{createMsg}</p>
          </div>

          {/* List Kits Card */}
          <div className="card-lite">
            <div className="section-title">Assembled Kit Bundles</div>
            {loading ? (
              <div className="text-muted">Loading kits...</div>
            ) : kits.length === 0 ? (
              <div className="text-muted">No kits assembled yet.</div>
            ) : (
              <table style={{ fontSize: '0.85rem' }}>
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Components</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {kits.map((k) => (
                    <tr key={k.id} style={{ cursor: 'pointer' }} onClick={() => handleSelectKit(k.id)}>
                      <td style={{ fontWeight: 600, color: selectedKit?.id === k.id ? '#818cf8' : '#fff' }}>{k.sku}</td>
                      <td>{k.name}</td>
                      <td>{k.components ? k.components.length : 0} items</td>
                      <td>
                        <button className="btn-sm btn-secondary" onClick={(e) => { e.stopPropagation(); handleSelectKit(k.id); }}>Manage</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        {/* Right Column: Kit Details, Components addition, and Sell simulation */}
        <div>
          {selectedKit ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
              {/* Kit Details Header */}
              <div className="card-lite" style={{ borderLeft: '4px solid #10b981' }}>
                <div className="section-title" style={{ border: 'none', marginBottom: '0.5rem' }}>Kit Config Summary</div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Kit ID:</span>
                  <span style={{ fontSize: '0.8rem', fontFamily: 'monospace' }}>{selectedKit.id}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">SKU:</span>
                  <strong style={{ color: '#fff' }}>{selectedKit.sku}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span className="text-muted">Name:</span>
                  <strong style={{ color: '#fff' }}>{selectedKit.name}</strong>
                </div>
              </div>

              {/* Add Component Form */}
              <div className="card-lite">
                <div className="section-title">Add Subcomponent to Kit</div>
                <form onSubmit={handleAddComponent}>
                  <div className="form-group">
                    <label htmlFor="compVariantId">Variant ID (UUID)</label>
                    <input id="compVariantId" value={compVariantId} onChange={e => setCompVariantId(e.target.value)} placeholder="Variant UUID..." required />
                  </div>
                  <div className="form-group">
                    <label htmlFor="compQty">Quantity per Bundle</label>
                    <input id="compQty" type="number" min="1" value={compQty} onChange={e => setCompQty(e.target.value)} placeholder="e.g. 1" required />
                  </div>
                  <button type="submit" className="btn-primary" style={{ width: '100%' }} disabled={isAddingComponent} aria-busy={isAddingComponent}>
                    {isAddingComponent ? 'Adding...' : 'Add Component'}
                  </button>
                </form>
                <p style={{ color: compMsg.includes('Failed') || compMsg.includes('Error') ? '#f87171' : '#34d399' }}>{compMsg}</p>
              </div>

              {/* Components List */}
              <div className="card-lite">
                <div className="section-title">Kit Components</div>
                {!selectedKit.components || selectedKit.components.length === 0 ? (
                  <p className="text-muted" style={{ textAlign: 'center', padding: '1rem 0' }}>No component variants mapped to this kit yet.</p>
                ) : (
                  <table style={{ fontSize: '0.85rem', marginBottom: 0 }}>
                    <thead>
                      <tr>
                        <th>Variant ID</th>
                        <th>Qty Required</th>
                      </tr>
                    </thead>
                    <tbody>
                      {selectedKit.components.map((comp) => (
                        <tr key={comp.id}>
                          <td style={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{comp.variant_id}</td>
                          <td style={{ fontWeight: 600 }}>{comp.quantity}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>

              {/* Sell Kit form (Decrement logic trigger) */}
              <div className="card-lite">
                <div className="section-title">Sell Kit Bundle</div>
                <form onSubmit={handleSellKit}>
                  <div className="form-group">
                    <label htmlFor="sellQty">Sale Quantity</label>
                    <input id="sellQty" type="number" min="1" value={sellQty} onChange={e => setSellQty(e.target.value)} placeholder="e.g. 1" required />
                  </div>
                  <div className="form-group">
                    <label htmlFor="sellSaleId">Sale ID / Invoice</label>
                    <input id="sellSaleId" value={sellSaleId} onChange={e => setSellSaleId(e.target.value)} placeholder="e.g. SALE-KIT-BND-10" required />
                  </div>
                  <button type="submit" className="btn-primary" style={{ width: '100%', background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)' }} disabled={isSellingKit} aria-busy={isSellingKit}>
                    {isSellingKit ? 'Processing...' : 'Process Bundle Sale'}
                  </button>
                </form>
                <p style={{ color: sellMsg.includes('failed') || sellMsg.includes('Ensure') ? '#f87171' : '#34d399' }}>{sellMsg}</p>
              </div>
            </div>
          ) : (
            <div className="card-lite" style={{ textAlign: 'center', padding: '4rem 0' }}>
              <p className="text-muted">Select a kit bundle on the left to add component rules or execute stock sales.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
