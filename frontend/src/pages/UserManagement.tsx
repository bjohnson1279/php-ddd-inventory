import React, { useEffect, useState } from 'react';
import api from '../api/client';
import Spinner from '../components/Spinner';

type User = { id: string; email: string; role: string };

export default function UserManagement() {
  const [users, setUsers] = useState<User[]>([]);
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [isInviting, setIsInviting] = useState(false);

  useEffect(() => {
    fetchUsers();
  }, []);

  const fetchUsers = async () => {
    try {
      const res = await api.get('/users');
      setUsers(res.users || []);
    } catch (e) {
      console.error(e);
    }
  };

  const invite = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage('');
    setIsInviting(true);
    try {
      await api.post('/users', { email });
      setMessage('Invited successfully!');
      setEmail('');
      fetchUsers();
    } catch (err: any) {
      setMessage(err?.message || 'Error');
    } finally {
      setIsInviting(false);
    }
  };

  return (
    <div className="card">
      <h2>Users</h2>
      <ul>
        {users.map((u) => (
          <li key={u.id}>
            {u.email} ({u.role})
          </li>
        ))}
      </ul>
      <form onSubmit={invite} className="form-group">
        <label htmlFor="invite-email">Email Address</label>
        <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem' }}>
          <input
            id="invite-email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="colleague@example.com"
            required
            disabled={isInviting}
            style={{ margin: 0 }}
          />
          <button type="submit" className="btn-primary" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem' }} disabled={isInviting || !email} aria-busy={isInviting}>
            {isInviting && <Spinner />} {isInviting ? 'Inviting...' : 'Invite'}
          </button>
        </div>
      </form>
      {message && (
        <p role="alert" className={message === 'Invited successfully!' ? 'text-success' : 'text-danger'}>
          {message}
        </p>
      )}
    </div>
  );
}
