const API_BASE = import.meta.env.VITE_API_URL || '/api';
let token: string | null = localStorage.getItem('token');

async function request(path: string, opts: RequestInit = {}) {
  const headers: Record<string, string> = {
    ...(opts.headers as Record<string,string> || {}),
    'Content-Type': 'application/json'
  };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(`${API_BASE}${path}`, { ...opts, headers });
  if (!res.ok) throw new Error(await res.text());
  try {
    return await res.json();
  } catch {
    return {};
  }
}

export default {
  get: (path: string) => request(path, { method: 'GET' }),
  post: (path: string, body?: any) => request(path, { method: 'POST', body: JSON.stringify(body) }),
  put: (path: string, body?: any) => request(path, { method: 'PUT', body: JSON.stringify(body) }),
  delete: (path: string) => request(path, { method: 'DELETE' }),
  setToken: (t: string) => { token = t; localStorage.setItem('token', t); },
  clearToken: () => { token = null; localStorage.removeItem('token'); }
};
