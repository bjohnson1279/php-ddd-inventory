import React, { useEffect, useState } from 'react';
import api from '../api/client';

type Unit = {
  name: string;
  abbreviation: string;
  category: 'discrete' | 'weight' | 'volume';
};

type ConversionRule = {
  id: string;
  unit: Unit;
  factor_to_base: number;
  label: string | null;
};

type UomConfig = {
  id: string;
  variant_id: string;
  base_unit: Unit;
  purchase_unit: Unit;
  sale_unit: Unit;
  rules: ConversionRule[];
};

export default function Uom() {
  const [variantId, setVariantId] = useState('');
  const [config, setConfig] = useState<UomConfig | null>(null);
  const [searchMsg, setSearchMsg] = useState('');

  // Create Config Form
  const [createVariantId, setCreateVariantId] = useState('');
  const [baseName, setBaseName] = useState('Each');
  const [baseAbbrev, setBaseAbbrev] = useState('ea');
  const [baseCategory, setBaseCategory] = useState<'discrete' | 'weight' | 'volume'>('discrete');
  const [createMsg, setCreateMsg] = useState('');

  // Add Conversion Rule Form
  const [ruleName, setRuleName] = useState('Case');
  const [ruleAbbrev, setRuleAbbrev] = useState('cs');
  const [ruleCategory, setRuleCategory] = useState<'discrete' | 'weight' | 'volume'>('discrete');
  const [ruleFactor, setRuleFactor] = useState('24.0');
  const [ruleLabel, setRuleLabel] = useState('Case of 24');
  const [ruleMsg, setRuleMsg] = useState('');

  // Set Purchase/Sale Units Form
  const [purchaseUnitIndex, setPurchaseUnitIndex] = useState('-1'); // -1 means base_unit
  const [saleUnitIndex, setSaleUnitIndex] = useState('-1'); // -1 means base_unit
  const [unitMsg, setUnitMsg] = useState('');

  const fetchConfig = async (vId: string) => {
    setSearchMsg('Searching...');
    setConfig(null);
    try {
      const res = await api.get(`/uom/configurations/variants/${vId}`);
      if (res.id) {
        setConfig(res);
        setSearchMsg('');
      } else {
        setSearchMsg('No configuration found for this variant.');
      }
    } catch (err: any) {
      setSearchMsg(err.message || 'No configuration found.');
    }
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (!variantId) return;
    fetchConfig(variantId);
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateMsg('Creating configuration...');
    try {
      const res = await api.post('/uom/configurations', {
        variant_id: createVariantId,
        base_unit: {
          name: baseName,
          abbreviation: baseAbbrev,
          category: baseCategory
        }
      });
      setCreateMsg('UoM configuration established!');
      setVariantId(createVariantId);
      fetchConfig(createVariantId);
      setCreateVariantId('');
    } catch (err: any) {
      setCreateMsg(err.message || 'Failed to create configuration');
    }
  };

  const handleAddRule = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!config) return;
    setRuleMsg('Adding rule...');
    try {
      await api.post(`/uom/configurations/${config.id}/rules`, {
        unit: {
          name: ruleName,
          abbreviation: ruleAbbrev,
          category: ruleCategory
        },
        factor_to_base: parseFloat(ruleFactor) || 1.0,
        label: ruleLabel
      });
      setRuleMsg('Conversion rule added!');
      setRuleLabel('');
      fetchConfig(config.variant_id);
    } catch (err: any) {
      setRuleMsg(err.message || 'Failed to add rule');
    }
  };

  const handleRemoveRule = async (ruleUnit: Unit) => {
    if (!config) return;
    try {
      await api.post(`/uom/configurations/${config.id}/rules`, {
        // DELETE endpoint uses DELETE method in ApiEndpointsTest but post/delete adapter routes
        // Wait! In public/index.php, line 690:
        // if ($method === 'DELETE' && preg_match('#^/api/uom/configurations/([^/]+)/rules$#', $uri, $m))
        // So we should use api.delete inside api client? Yes, api.delete is available!
        // But wait! How does DELETE verify body? Let's check how RequestAdapter parses body in index.php.
        // It says `file_get_contents('php://input')` which works for DELETE as well!
        // Let's use fetch directly via standard request structure if needed or api client with custom request.
        // Wait, our api client in client.ts does:
        // delete: (path) => request(path, { method: 'DELETE' }) -- does not accept body!
        // So let's write a direct fetch for delete conversion rule!
      });
    } catch (e) {
      console.error(e);
    }
  };

  const handleRemoveRuleFetch = async (ruleUnit: Unit) => {
    if (!config) return;
    try {
      const token = localStorage.getItem('token');
      const res = await fetch(`/api/uom/configurations/${config.id}/rules`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ unit: ruleUnit })
      });
      if (res.ok) {
        fetchConfig(config.variant_id);
      } else {
        const text = await res.text();
        alert('Failed to remove rule: ' + text);
      }
    } catch (err: any) {
      alert(err.message);
    }
  };

  const handleSetUnits = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!config) return;
    setUnitMsg('Setting workflow units...');
    try {
      // Find units based on indexes (-1 means base unit, otherwise index of rules)
      const pIdx = parseInt(purchaseUnitIndex);
      const sIdx = parseInt(saleUnitIndex);

      const purchase_unit = pIdx === -1 ? config.base_unit : config.rules[pIdx].unit;
      const sale_unit = sIdx === -1 ? config.base_unit : config.rules[sIdx].unit;

      await api.post(`/uom/configurations/${config.id}/units`, {
        purchase_unit,
        sale_unit
      });
      setUnitMsg('Units configured successfully!');
      fetchConfig(config.variant_id);
    } catch (err: any) {
      setUnitMsg(err.message || 'Failed to update workflow units');
    }
  };

  return (
    <div>
      <h2 style={{ marginBottom: '1.5rem', fontWeight: 600 }}>Unit of Measure (UoM) Manager</h2>

      <div className="grid-2">
        {/* Left Column: Search & Create Forms */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Lookup Config */}
          <div className="card-lite">
            <div className="section-title">Lookup Variant UoM Configuration</div>
            <form onSubmit={handleSearch} className="form-row">
              <div className="form-group" style={{ flex: 1, marginBottom: 0 }}>
                <label>Variant ID (UUID)</label>
                <input value={variantId} onChange={e => setVariantId(e.target.value)} placeholder="Variant UUID..." required />
              </div>
              <button type="submit" className="btn-primary">Load</button>
            </form>
            <p style={{ color: '#f87171' }}>{searchMsg}</p>
          </div>

          {/* Create Config */}
          <div className="card-lite">
            <div className="section-title">Establish UoM Configuration</div>
            <form onSubmit={handleCreate}>
              <div className="form-group">
                <label>Variant ID (UUID)</label>
                <input value={createVariantId} onChange={e => setCreateVariantId(e.target.value)} placeholder="Variant UUID..." required />
              </div>
              <div className="form-group">
                <label>Base Unit Name</label>
                <input value={baseName} onChange={e => setBaseName(e.target.value)} placeholder="e.g. Gram / Each" required />
              </div>
              <div className="grid-2">
                <div className="form-group">
                  <label>Abbrev</label>
                  <input value={baseAbbrev} onChange={e => setBaseAbbrev(e.target.value)} placeholder="e.g. g / ea" required />
                </div>
                <div className="form-group">
                  <label>Category</label>
                  <select value={baseCategory} onChange={e => setBaseCategory(e.target.value as any)}>
                    <option value="discrete">Discrete / Count</option>
                    <option value="weight">Weight / Mass</option>
                    <option value="volume">Volume / Liquid</option>
                  </select>
                </div>
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%' }}>Create Configuration</button>
            </form>
            <p style={{ color: createMsg.includes('established') ? '#34d399' : '#f87171' }}>{createMsg}</p>
          </div>
        </div>

        {/* Right Column: Configuration Details Panel */}
        <div>
          {config ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
              {/* Core Details */}
              <div className="card-lite" style={{ borderLeft: '4px solid #818cf8' }}>
                <div className="section-title" style={{ border: 'none', marginBottom: '0.5rem' }}>Active Configuration</div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Configuration ID:</span>
                  <span style={{ fontSize: '0.8rem', fontFamily: 'monospace' }}>{config.id}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Base Unit:</span>
                  <strong style={{ color: '#fff' }}>{config.base_unit.name} ({config.base_unit.abbreviation})</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span className="text-muted">Purchase Unit:</span>
                  <strong style={{ color: '#fff' }}>{config.purchase_unit ? `${config.purchase_unit.name} (${config.purchase_unit.abbreviation})` : 'Base Unit'}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span className="text-muted">Sale Unit:</span>
                  <strong style={{ color: '#fff' }}>{config.sale_unit ? `${config.sale_unit.name} (${config.sale_unit.abbreviation})` : 'Base Unit'}</strong>
                </div>
              </div>

              {/* Set Workflow Units Form */}
              <div className="card-lite">
                <div className="section-title">Configure Workflow Operations</div>
                <form onSubmit={handleSetUnits}>
                  <div className="form-group">
                    <label>Purchase Unit</label>
                    <select value={purchaseUnitIndex} onChange={e => setPurchaseUnitIndex(e.target.value)}>
                      <option value="-1">Base Unit: {config.base_unit.name}</option>
                      {config.rules.map((r, i) => (
                        <option key={r.id} value={i.toString()}>{r.unit.name} ({r.unit.abbreviation})</option>
                      ))}
                    </select>
                  </div>
                  <div className="form-group">
                    <label>Sale Unit</label>
                    <select value={saleUnitIndex} onChange={e => setSaleUnitIndex(e.target.value)}>
                      <option value="-1">Base Unit: {config.base_unit.name}</option>
                      {config.rules.map((r, i) => (
                        <option key={r.id} value={i.toString()}>{r.unit.name} ({r.unit.abbreviation})</option>
                      ))}
                    </select>
                  </div>
                  <button type="submit" className="btn-primary" style={{ width: '100%' }}>Update Workflow Units</button>
                </form>
                <p style={{ color: unitMsg.includes('failed') || unitMsg.includes('Error') ? '#f87171' : '#34d399' }}>{unitMsg}</p>
              </div>

              {/* Add Conversion Rule Form */}
              <div className="card-lite">
                <div className="section-title">Add Pack / Conversion Rule</div>
                <form onSubmit={handleAddRule}>
                  <div className="form-group">
                    <label>Pack Unit Name</label>
                    <input value={ruleName} onChange={e => setRuleName(e.target.value)} placeholder="e.g. Case / Pallet" required />
                  </div>
                  <div className="grid-2">
                    <div className="form-group">
                      <label>Abbrev</label>
                      <input value={ruleAbbrev} onChange={e => setRuleAbbrev(e.target.value)} placeholder="e.g. cs / plt" required />
                    </div>
                    <div className="form-group">
                      <label>Category (Must Match Base Category)</label>
                      <select value={ruleCategory} onChange={e => setRuleCategory(e.target.value as any)}>
                        <option value="discrete">Discrete / Count</option>
                        <option value="weight">Weight / Mass</option>
                        <option value="volume">Volume / Liquid</option>
                      </select>
                    </div>
                  </div>
                  <div className="grid-2">
                    <div className="form-group">
                      <label>Factor to Base</label>
                      <input type="number" step="any" value={ruleFactor} onChange={e => setRuleFactor(e.target.value)} placeholder="e.g. 24" required />
                    </div>
                    <div className="form-group">
                      <label>Label</label>
                      <input value={ruleLabel} onChange={e => setRuleLabel(e.target.value)} placeholder="e.g. Case of 24" />
                    </div>
                  </div>
                  <button type="submit" className="btn-primary" style={{ width: '100%' }}>Add Conversion Rule</button>
                </form>
                <p style={{ color: ruleMsg.includes('added') ? '#34d399' : '#f87171' }}>{ruleMsg}</p>
              </div>

              {/* Rules List */}
              <div className="card-lite">
                <div className="section-title">Established Conversion Rules</div>
                {config.rules.length === 0 ? (
                  <p className="text-muted" style={{ textAlign: 'center', padding: '1rem 0' }}>No conversion rules defined. Currently only supports base units.</p>
                ) : (
                  <table style={{ fontSize: '0.85rem' }}>
                    <thead>
                      <tr>
                        <th>Unit</th>
                        <th>Factor</th>
                        <th>Label</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {config.rules.map((rule) => (
                        <tr key={rule.id}>
                          <td style={{ fontWeight: 600 }}>{rule.unit.name} ({rule.unit.abbreviation})</td>
                          <td>{rule.factor_to_base}x base</td>
                          <td>{rule.label || 'None'}</td>
                          <td>
                            <button onClick={() => handleRemoveRuleFetch(rule.unit)} className="btn-sm btn-secondary text-danger" style={{ padding: '0.2rem 0.5rem' }}>
                              Remove
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          ) : (
            <div className="card-lite" style={{ textAlign: 'center', padding: '4rem 0' }}>
              <p className="text-muted">Enter a variant UUID on the left to review and manage unit conversions.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
