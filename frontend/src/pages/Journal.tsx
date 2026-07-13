import React, { useEffect, useState } from 'react';
import api from '../api/client';
import Spinner from '../components/Spinner';

type JournalLine = {
  id?: string;
  account: string;
  amount: number; // in cents
  type: 'debit' | 'credit';
  memo: string;
};

type JournalEntry = {
  id: string;
  entry_date: string;
  description: string;
  method: 'cash' | 'accrual';
  reference_id: string | null;
  lines: JournalLine[];
};

export default function Journal() {
  const [entries, setEntries] = useState<JournalEntry[]>([]);
  const [loading, setLoading] = useState(true);

  // New Journal Entry Form
  const [entryDate, setEntryDate] = useState('2026-05-30');
  const [description, setDescription] = useState('');
  const [method, setMethod] = useState<'cash' | 'accrual'>('accrual');
  const [refId, setRefId] = useState('');
  const [lines, setLines] = useState<JournalLine[]>([
    { account: '1200', amount: 0, type: 'debit', memo: '' },
    { account: '1000', amount: 0, type: 'credit', memo: '' }
  ]);
  const [submitMsg, setSubmitMsg] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    fetchEntries();
  }, []);

  const fetchEntries = async () => {
    try {
      setLoading(true);
      const res = await api.get('/journal/entries');
      const items = res.entries || [];
      // Parse lines if string
      const parsed = items.map((e: any) => {
        let linesData = e.lines;
        if (typeof linesData === 'string') {
          try {
            linesData = JSON.parse(linesData);
          } catch {
            linesData = [];
          }
        }
        return { ...e, lines: linesData };
      });
      setEntries(parsed);
    } catch (e: any) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleAddLine = () => {
    setLines([...lines, { account: '', amount: 0, type: 'debit', memo: '' }]);
  };

  const handleRemoveLine = (idx: number) => {
    if (lines.length <= 2) return;
    setLines(lines.filter((_, i) => i !== idx));
  };

  const handleLineChange = (idx: number, field: keyof JournalLine, value: any) => {
    const updated = [...lines];
    if (field === 'amount') {
      updated[idx].amount = parseInt(value) || 0;
    } else {
      (updated[idx] as any)[field] = value;
    }
    setLines(updated);
  };

  // Calculate sums
  const totalDebits = lines.reduce((acc, curr) => curr.type === 'debit' ? acc + curr.amount : acc, 0);
  const totalCredits = lines.reduce((acc, curr) => curr.type === 'credit' ? acc + curr.amount : acc, 0);
  const isBalanced = totalDebits === totalCredits && totalDebits > 0;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isBalanced) {
      setSubmitMsg('Error: Debits and Credits must balance and be greater than 0.');
      return;
    }
    setSubmitMsg('');
    setIsSubmitting(true);
    try {
      await api.post('/journal/entries', {
        date: entryDate,
        description,
        method,
        reference_id: refId || null,
        lines: lines.map(l => ({
          account: l.account,
          amount: l.amount, // already in cents
          type: l.type,
          memo: l.memo
        }))
      });
      setSubmitMsg('Journal entry balanced and recorded successfully!');
      setDescription('');
      setRefId('');
      setLines([
        { account: '1200', amount: 0, type: 'debit', memo: '' },
        { account: '1000', amount: 0, type: 'credit', memo: '' }
      ]);
      fetchEntries();
    } catch (err: any) {
      setSubmitMsg(err.message || 'Submission failed');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div>
      <h2 style={{ marginBottom: '1.5rem', fontWeight: 600 }}>Accounting Journal entries</h2>

      <div className="grid-2">
        {/* Left Column: Form to create Entry */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          <div className="card-lite" style={{ maxWidth: 'none' }}>
            <div className="section-title">Record Manual Journal Entry</div>
            <form onSubmit={handleSubmit}>
              <div className="grid-2">
                <div className="form-group">
                  <label htmlFor="entryDate">Date</label>
                  <input id="entryDate" type="date" value={entryDate} onChange={e => setEntryDate(e.target.value)} required />
                </div>
                <div className="form-group">
                  <label htmlFor="method">Accounting Standard</label>
                  <select id="method" value={method} onChange={e => setMethod(e.target.value as any)}>
                    <option value="accrual">Accrual Method</option>
                    <option value="cash">Cash Method</option>
                  </select>
                </div>
              </div>
              <div className="form-group">
                <label htmlFor="description">Description / Narrative</label>
                <input id="description" value={description} onChange={e => setDescription(e.target.value)} placeholder="e.g. Month-end inventory adjustment" required />
              </div>
              <div className="form-group">
                <label htmlFor="refId">Reference ID / Source doc (Optional)</label>
                <input id="refId" value={refId} onChange={e => setRefId(e.target.value)} placeholder="e.g. PO-9801 / SALE-1002" />
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                <span className="text-muted" style={{ fontWeight: 600 }}>Journal Lines</span>
                <button type="button" className="btn-sm btn-secondary" onClick={handleAddLine}>+ Add Line</button>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', marginBottom: '1.5rem' }}>
                {lines.map((line, idx) => (
                  <div key={idx} style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', background: 'rgba(0,0,0,0.1)', padding: '0.5rem', borderRadius: '8px', border: '1px solid rgba(255,255,255,0.03)' }}>
                    <div style={{ flex: 1.5 }}>
                      <input aria-label={`Account for line ${idx + 1}`} value={line.account} onChange={e => handleLineChange(idx, 'account', e.target.value)} placeholder="Acct e.g. 1200" required style={{ margin: 0 }} />
                    </div>
                    <div style={{ flex: 1 }}>
                      <select aria-label={`Type for line ${idx + 1}`} value={line.type} onChange={e => handleLineChange(idx, 'type', e.target.value)} style={{ padding: '0.625rem 0.5rem' }}>
                        <option value="debit">DR (Debit)</option>
                        <option value="credit">CR (Credit)</option>
                      </select>
                    </div>
                    <div style={{ flex: 1.5 }}>
                      <input aria-label={`Amount for line ${idx + 1}`} type="number" min="1" value={line.amount || ''} onChange={e => handleLineChange(idx, 'amount', e.target.value)} placeholder="Amount (cents)" required style={{ margin: 0 }} />
                    </div>
                    <div style={{ flex: 2 }}>
                      <input aria-label={`Memo for line ${idx + 1}`} value={line.memo} onChange={e => handleLineChange(idx, 'memo', e.target.value)} placeholder="Memo note" style={{ margin: 0 }} />
                    </div>
                    {lines.length > 2 && (
                      <button type="button" aria-label={`Remove line ${idx + 1}`} className="btn-sm btn-secondary text-danger" style={{ padding: '0.5rem 0.6rem' }} onClick={() => handleRemoveLine(idx)}>✕</button>
                    )}
                  </div>
                ))}
              </div>

              {/* Status Summary & Submit */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '1rem', background: 'rgba(255,255,255,0.01)', borderRadius: '8px', border: '1px solid rgba(255,255,255,0.05)', marginBottom: '1rem' }}>
                <div>
                  <div style={{ fontSize: '0.85rem' }}>Debits: <strong style={{ color: '#fff' }}>${(totalDebits / 100).toFixed(2)}</strong></div>
                  <div style={{ fontSize: '0.85rem' }}>Credits: <strong style={{ color: '#fff' }}>${(totalCredits / 100).toFixed(2)}</strong></div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  {isBalanced ? (
                    <span className="text-success" style={{ fontWeight: 600 }}>✓ Balanced</span>
                  ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end' }}>
                      <span className="text-danger" style={{ fontWeight: 600 }}>✗ Unbalanced</span>
                      <span className="text-danger" style={{ fontSize: '0.75rem', marginTop: '0.25rem' }}>Diff: ${(Math.abs(totalDebits - totalCredits) / 100).toFixed(2)}</span>
                    </div>
                  )}
                </div>
              </div>

              <button type="submit" className="btn-primary" style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={!isBalanced || isSubmitting} aria-busy={isSubmitting} title={!isBalanced ? 'Debits and credits must balance before posting' : ''}>
                {isSubmitting && <Spinner />} {isSubmitting ? 'Posting...' : 'Post Journal Entry'}
              </button>
            </form>
            {submitMsg && (
              <p style={{ color: submitMsg === 'Journal entry balanced and recorded successfully!' ? '#34d399' : '#f87171' }}>
                {submitMsg}
              </p>
            )}
          </div>
        </div>

        {/* Right Column: List Entries */}
        <div>
          <div className="card-lite" style={{ maxWidth: 'none' }}>
            <div className="section-title">General Ledger Entries</div>
            {loading ? (
              <div className="text-muted">Loading transactions...</div>
            ) : entries.length === 0 ? (
              <div className="text-muted">No journal postings recorded.</div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
                {entries.map((entry) => (
                  <div key={entry.id} style={{ background: 'rgba(255,255,255,0.015)', border: '1px solid rgba(255,255,255,0.04)', borderRadius: '12px', padding: '1rem' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '1px solid rgba(255,255,255,0.05)', paddingBottom: '0.5rem', marginBottom: '0.75rem' }}>
                      <div>
                        <h4 style={{ color: '#fff', fontSize: '0.95rem' }}>{entry.description}</h4>
                        <span className="text-muted" style={{ fontSize: '0.75rem' }}>Ref: {entry.reference_id || 'None'} | Date: {entry.entry_date}</span>
                      </div>
                      <span className="badge badge-submitted">{entry.method}</span>
                    </div>

                    <table style={{ fontSize: '0.8rem', width: '100%', margin: 0 }}>
                      <thead>
                        <tr>
                          <th>Account</th>
                          <th>Debit</th>
                          <th>Credit</th>
                          <th>Memo</th>
                        </tr>
                      </thead>
                      <tbody>
                        {entry.lines.map((line, idx) => (
                          <tr key={idx}>
                            <td style={{ fontFamily: 'monospace' }}>{line.account}</td>
                            <td style={{ color: line.type === 'debit' ? '#34d399' : 'inherit' }}>{line.type === 'debit' ? `$${(line.amount / 100).toFixed(2)}` : ''}</td>
                            <td style={{ color: line.type === 'credit' ? '#f87171' : 'inherit' }}>{line.type === 'credit' ? `$${(line.amount / 100).toFixed(2)}` : ''}</td>
                            <td className="text-muted">{line.memo}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
