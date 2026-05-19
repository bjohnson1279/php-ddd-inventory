import React, { useState } from 'react';
import api from '../api/client';

export default function InitialSetup() {
  const [orgName, setOrgName] = useState('');
  const [message, setMessage] = useState('');

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage('Setting up...');
    try {
      const res = await api.post('/setup', { orgName });
      setMessage(res.message || 'Setup complete');
    } catch (err: any) {
      setMessage(err.message || 'Error');
    }
  };

  return (
    <form onSubmit={submit} className="card">
      <label>
        Organization name
        <input value={orgName} onChange={(e) => setOrgName(e.target.value)} />
      </label>
      <button type="submit">Run initial setup</button>
      <p>{message}</p>
    </form>
  );
}
