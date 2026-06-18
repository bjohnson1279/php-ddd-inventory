import React, { useState } from 'react';
import api from '../api/client';

export default function InitialSetup() {
  const [orgName, setOrgName] = useState('');
  const [tenantId, setTenantId] = useState('');
  const [adminName, setAdminName] = useState('');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminPassword, setAdminPassword] = useState('');
  const [message, setMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage('');
    setIsLoading(true);
    try {
      const res = await api.post('/setup', { 
        orgName, 
        tenantId, 
        adminName, 
        adminEmail, 
        adminPassword 
      });
      setMessage(res.message || 'Setup complete. You can now log in.');
    } catch (err: any) {
      setMessage(err.message || 'Error');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={submit} className="card">
      <h2>Initial Setup</h2>

      <div className="form-group">
        <label htmlFor="orgName">Organization Name</label>
        <input id="orgName" value={orgName} onChange={(e) => setOrgName(e.target.value)} placeholder="e.g. Riverside Apparel Co." />
      </div>

      <div className="form-group">
        <label htmlFor="tenantId">Tenant ID</label>
        <input id="tenantId" value={tenantId} onChange={(e) => setTenantId(e.target.value)} placeholder="e.g. riverside-apparel" />
      </div>

      <div className="form-group">
        <label htmlFor="adminName">Admin Name</label>
        <input id="adminName" value={adminName} onChange={(e) => setAdminName(e.target.value)} placeholder="Jane Smith" />
      </div>

      <div className="form-group">
        <label htmlFor="adminEmail">Admin Email</label>
        <input id="adminEmail" type="email" value={adminEmail} onChange={(e) => setAdminEmail(e.target.value)} placeholder="jane@example.com" />
      </div>

      <div className="form-group">
        <label htmlFor="adminPassword">Admin Password</label>
        <input id="adminPassword" type="password" value={adminPassword} onChange={(e) => setAdminPassword(e.target.value)} placeholder="•••••••• (Min 8 characters)" />
      </div>

      <button type="submit" className="btn-primary" disabled={isLoading} aria-busy={isLoading}>
        {isLoading ? 'Setting up...' : 'Run Initial Setup'}
      </button>

      {message && (
        <p role="alert" className={message.includes('Setup complete') ? 'text-success' : 'text-danger'}>
          {message}
        </p>
      )}
    </form>
  );
}
