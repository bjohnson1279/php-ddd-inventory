import React, { createContext, useContext, useState, ReactNode, useEffect } from 'react';
import api from '../api/client';

type User = { email: string } | null;
type AuthContextType = { user: User; login: (email:string,password:string)=>Promise<void>; logout: ()=>void };

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User>(null);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (token) {
      api.setToken(token);
      setUser({ email: localStorage.getItem('user_email') || '' });
    }
  }, []);

  const login = async (email:string, password:string) => {
    const res = await api.post('/auth/login', { email, password });
    const token = res.token;
    if (!token) throw new Error('No token returned');
    localStorage.setItem('token', token);
    localStorage.setItem('user_email', email);
    api.setToken(token);
    setUser({ email });
  };

  const logout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user_email');
    api.clearToken();
    setUser(null);
  };

  return <AuthContext.Provider value={{ user, login, logout }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
