import React, { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import NotificationBell from './NotificationBell';
import './AppLayout.css';

const StudentLinks = [
  { to: '/dashboard', label: 'Dashboard', icon: '⌂' },
  { to: '/tasks', label: 'My Tasks', icon: '☰' },
  { to: '/profile', label: 'Account Profile', icon: '○' },
  { to: '/settings', label: 'Settings', icon: '⚙' },
];

const RepLinks = [
  { to: '/representative', label: 'Rep Dashboard', icon: '◈' },
  { to: '/representative/tasks', label: 'Manage Tasks', icon: '✎' },
  { to: '/representative/tasks/create', label: 'Create Task', icon: '+' },
];

const AppLayout: React.FC = () => {
  const { user, logout, hasRole } = useAuth();
  const navigate = useNavigate();
  const [mobileOpen, setMobileOpen] = useState(false);
  const isRep = hasRole('representative');

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const closeMobile = () => setMobileOpen(false);

  return (
    <div className="app-container">
      {/* Mobile top bar */}
      <header className="mobile-topbar">
        <button
          className="hamburger"
          aria-label={mobileOpen ? 'Close navigation' : 'Open navigation'}
          aria-expanded={mobileOpen}
          onClick={() => setMobileOpen((v) => !v)}
        >
          ☰
        </button>
        <span className="mobile-brand">
          Sch<span className="ai">AI</span>
        </span>
        <div className="mobile-actions">
          <NotificationBell />
          <span className="mobile-user" aria-hidden="true">
            {user?.name?.charAt(0) ?? 'S'}
          </span>
        </div>
      </header>

      <aside className={`sidebar${mobileOpen ? ' sidebar-open' : ''}`} aria-label="Primary navigation">
        <div className="brand">
          <span className="brand-text">
            Sch<span className="ai">AI</span>
          </span>
          <span className="brand-sub">Academic Task Hub</span>
        </div>

        <nav>
          {StudentLinks.map((l) => (
            <NavLink
              key={l.to}
              to={l.to}
              onClick={closeMobile}
              className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
            >
              <span aria-hidden="true" className="nav-icon">{l.icon}</span>
              {l.label}
            </NavLink>
          ))}

          {isRep && (
            <>
              <div className="nav-section">Representative</div>
              {RepLinks.map((l) => (
                <NavLink
                  key={l.to}
                  to={l.to}
                  end={l.to === '/representative'}
                  onClick={closeMobile}
                  className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
                >
                  <span aria-hidden="true" className="nav-icon">{l.icon}</span>
                  {l.label}
                </NavLink>
              ))}
            </>
          )}

          <div className="nav-section">System</div>
          <div className="nav-user">
            <div className="nav-user-name">{user?.name}</div>
            <div className="nav-user-meta">
              {user?.role === 'representative' ? 'Batch Representative' : 'Student'} · {user?.batch}
            </div>
          </div>
        </nav>

        <div className="spacer" />
        <button className="nav-item nav-logout" onClick={handleLogout} type="button">
          <span aria-hidden="true" className="nav-icon">⏻</span>
          Logout
        </button>
      </aside>

      {mobileOpen && (
        <button className="sidebar-scrim" aria-label="Close navigation" onClick={closeMobile} />
      )}

      <div className="main-content">
        <Outlet />
      </div>
    </div>
  );
};

export default AppLayout;
