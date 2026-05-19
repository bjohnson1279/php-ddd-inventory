import React from 'react';
import { BrowserRouter, Routes, Route, Link, Navigate } from 'react-router-dom';
import InitialSetup from './pages/InitialSetup';
import UserManagement from './pages/UserManagement';
import Login from './pages/Login';
import { useAuth, AuthProvider } from './auth/AuthProvider';
import PrivateRoute from './components/PrivateRoute';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <header>
          <h1>Inventory Admin</h1>
          <nav>
            <Link to="/setup"><button>Initial Setup</button></Link>
            <Link to="/users"><button>User Management</button></Link>
            <AuthStatus />
          </nav>
        </header>
        <main>
          <Routes>
            <Route path="/" element={<Navigate to="/setup" replace />} />
            <Route path="/setup" element={<InitialSetup />} />
            <Route path="/login" element={<Login />} />
            <Route path="/users" element={
              <PrivateRoute>
                <UserManagement />
              </PrivateRoute>
            } />
          </Routes>
        </main>
      </BrowserRouter>
    </AuthProvider>
  );
}

function AuthStatus() {
  const { user, logout } = useAuth();
  if (!user) return null;
  return (
    <div style={{display:'inline-block', marginLeft: '1rem'}}>
      <span>{user.email}</span>
      <button onClick={logout} style={{marginLeft: '0.5rem'}}>Logout</button>
    </div>
  );
}
