import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthProvider';

export default function Login() {
  const [email,setEmail]=useState('');
  const [password,setPassword]=useState('');
  const [msg,setMsg]=useState('');
  const { login } = useAuth();
  const nav = useNavigate();

  const submit = async (e:React.FormEvent) => {
    e.preventDefault();
    setMsg('Signing in...');
    try {
      await login(email,password);
      setMsg('Signed in');
      nav('/users');
    } catch (err:any) {
      setMsg(err.message || 'Login failed');
    }
  };

  return (
    <form onSubmit={submit} className="card">
      <h2>Sign in</h2>
      <label>Email<input value={email} onChange={e=>setEmail(e.target.value)} /></label>
      <label>Password<input type="password" value={password} onChange={e=>setPassword(e.target.value)} /></label>
      <button type="submit">Sign in</button>
      <p>{msg}</p>
    </form>
  );
}
