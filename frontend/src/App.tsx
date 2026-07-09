import React from 'react';
import { BrowserRouter, Routes, Route, Link, Navigate, useLocation } from 'react-router-dom';
import InitialSetup from './pages/InitialSetup';
import UserManagement from './pages/UserManagement';
import Login from './pages/Login';
import Catalog from './pages/Catalog';
import Inventory from './pages/Inventory';
import Serials from './pages/Serials';
import Onboarding from './pages/Onboarding';
import Uom from './pages/Uom';
import Kits from './pages/Kits';
import Journal from './pages/Journal';
import ValuationDashboard from './pages/ValuationDashboard';
import { useAuth, AuthProvider } from './auth/AuthProvider';
import PrivateRoute from './components/PrivateRoute';
import NotificationBell from './components/NotificationBell';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <AppLayout />
      </BrowserRouter>
    </AuthProvider>
  );
}

function AppLayout() {
  const { user } = useAuth();
  const loc = useLocation();

  const isLinkActive = (path: string) => loc.pathname === path;

  if (!user) {
    return (
      <>
        <header>
          <h1>Inventory System</h1>
          <nav>
            <Link to="/setup"><button className="btn-secondary">Organization Setup</button></Link>
            <Link to="/login"><button className="btn-primary">Sign In</button></Link>
          </nav>
        </header>
        <main>
          <Routes>
            <Route path="/" element={<Navigate to="/setup" replace />} />
            <Route path="/setup" element={<InitialSetup />} />
            <Route path="/login" element={<Login />} />
            <Route path="*" element={<Navigate to="/login" replace />} />
          </Routes>
        </main>
      </>
    );
  }

  return (
    <>
      <header>
        <h1>Inventory Control Panel</h1>
        <div style={{ display: 'flex', alignItems: 'center', gap: '1.5rem' }}>
          <NotificationBell />
          <AuthStatus />
        </div>
      </header>
      <main className="dashboard-container">
        {/* Navigation Sidebar */}
        <aside className="sidebar">
          <Link to="/catalog" className={`sidebar-link ${isLinkActive('/catalog') ? 'active' : ''}`}>
            📦 Catalog & Products
          </Link>
          <Link to="/inventory" className={`sidebar-link ${isLinkActive('/inventory') ? 'active' : ''}`}>
            ⚡ Stock & Counts
          </Link>
          <Link to="/serials" className={`sidebar-link ${isLinkActive('/serials') ? 'active' : ''}`}>
            🔢 Serial Tracking
          </Link>
          <Link to="/onboarding" className={`sidebar-link ${isLinkActive('/onboarding') ? 'active' : ''}`}>
            🏁 Opening Balances
          </Link>
          <Link to="/uom" className={`sidebar-link ${isLinkActive('/uom') ? 'active' : ''}`}>
            📏 UoM Conversions
          </Link>
          <Link to="/kits" className={`sidebar-link ${isLinkActive('/kits') ? 'active' : ''}`}>
            🍱 Kits & Bundles
          </Link>
          <Link to="/journal" className={`sidebar-link ${isLinkActive('/journal') ? 'active' : ''}`}>
            📖 Accounting Journal
          </Link>
          <Link to="/analytics" className={`sidebar-link ${isLinkActive('/analytics') ? 'active' : ''}`}>
            📊 Financial Analytics
          </Link>
          <Link to="/users" className={`sidebar-link ${isLinkActive('/users') ? 'active' : ''}`}>
            👥 User Access Management
          </Link>
        </aside>

        {/* Dynamic Content Panel */}
        <section className="content-area">
          <Routes>
            <Route path="/" element={<Navigate to="/catalog" replace />} />
            <Route path="/catalog" element={<PrivateRoute><Catalog /></PrivateRoute>} />
            <Route path="/inventory" element={<PrivateRoute><Inventory /></PrivateRoute>} />
            <Route path="/serials" element={<PrivateRoute><Serials /></PrivateRoute>} />
            <Route path="/onboarding" element={<PrivateRoute><Onboarding /></PrivateRoute>} />
            <Route path="/uom" element={<PrivateRoute><Uom /></PrivateRoute>} />
            <Route path="/kits" element={<PrivateRoute><Kits /></PrivateRoute>} />
            <Route path="/journal" element={<PrivateRoute><Journal /></PrivateRoute>} />
            <Route path="/analytics" element={<PrivateRoute><ValuationDashboard /></PrivateRoute>} />
            <Route path="/users" element={<PrivateRoute><UserManagement /></PrivateRoute>} />
            <Route path="*" element={<Navigate to="/catalog" replace />} />
          </Routes>
        </section>
      </main>
    </>
  );
}

function AuthStatus() {
  const { user, logout } = useAuth();
  if (!user) return null;
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
      <span className="text-muted" style={{ fontSize: '0.9rem' }}>{user.email}</span>
      <button onClick={logout} className="btn-secondary btn-sm" style={{ padding: '0.4rem 0.8rem' }}>Logout</button>
    </div>
  );
}

