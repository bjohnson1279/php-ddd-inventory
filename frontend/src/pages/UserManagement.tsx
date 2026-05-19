import React, { useEffect, useState } from 'react';
import api from '../api/client';

type User = { id: string; email: string; role: string };

export default function UserManagement() {
  const [users, setUsers] = useState<User[]>([]);
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');

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
    setMessage('Inviting...');
    try {
      await api.post('/users', { email });
      setMessage('Invited');
      setEmail('');
      fetchUsers();
    } catch (err: any) {
      setMessage(err.message || 'Error');
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
      <form onSubmit={invite}>
        <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="email" />
        <button type="submit">Invite</button>
      </form>
      <p>{message}</p>
    </div>
  );
}
