import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthProvider';

export default function Login() {
  const [tenantId,setTenantId]=useState('');
  const [email,setEmail]=useState('');
  const [password,setPassword]=useState('');
  const [msg,setMsg]=useState('');
  const [isLoading,setIsLoading]=useState(false);
  const { login } = useAuth();
  const nav = useNavigate();

  const submit = async (e:React.FormEvent) => {
    e.preventDefault();
    setMsg('');
    setIsLoading(true);
    try {
      await login(email,password,tenantId);
      setMsg('Signed in');
      nav('/users');
    } catch (err:any) {
      setMsg(err.message || 'Login failed');
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={submit} className="card">
      <h2>Sign in</h2>

      <div className="form-group">
        <label htmlFor="tenantId">Tenant ID</label>
        <input id="tenantId" value={tenantId} onChange={e=>setTenantId(e.target.value)} placeholder="e.g. riverside-apparel" required />
      </div>

      <div className="form-group">
        <label htmlFor="email">Email</label>
        <input id="email" type="email" value={email} onChange={e=>setEmail(e.target.value)} placeholder="jane@example.com" required />
      </div>

      <div className="form-group">
        <label htmlFor="password">Password</label>
        <input id="password" type="password" value={password} onChange={e=>setPassword(e.target.value)} placeholder="••••••••" required />
      </div>

      <button type="submit" className="btn-primary" disabled={isLoading} aria-busy={isLoading}>
        {isLoading ? 'Signing in...' : 'Sign in'}
      </button>

      {msg && <p role="alert">{msg}</p>}
    </form>
  );
}
