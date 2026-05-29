import React, { useState } from 'react';
import api from '../api/client';

export default function InitialSetup() {
  const [orgName, setOrgName] = useState('');
  const [tenantId, setTenantId] = useState('');
  const [adminName, setAdminName] = useState('');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminPassword, setAdminPassword] = useState('');
  const [message, setMessage] = useState('');

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage('Setting up...');
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
    }
  };

  return (
    <form onSubmit={submit} className="card">
      <h2>Initial Setup</h2>
      <label>
        Organization Name
        <input value={orgName} onChange={(e) => setOrgName(e.target.value)} placeholder="e.g. Riverside Apparel Co." />
      </label>
      <label>
        Tenant ID
        <input value={tenantId} onChange={(e) => setTenantId(e.target.value)} placeholder="e.g. riverside-apparel" />
      </label>
      <label>
        Admin Name
        <input value={adminName} onChange={(e) => setAdminName(e.target.value)} placeholder="Jane Smith" />
      </label>
      <label>
        Admin Email
        <input type="email" value={adminEmail} onChange={(e) => setAdminEmail(e.target.value)} placeholder="jane@example.com" />
      </label>
      <label>
        Admin Password
        <input type="password" value={adminPassword} onChange={(e) => setAdminPassword(e.target.value)} placeholder="•••••••• (Min 8 characters)" />
      </label>
      <button type="submit">Run Initial Setup</button>
      <p>{message}</p>
    </form>
  );
}
