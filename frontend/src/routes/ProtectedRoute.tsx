import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { UserRole } from '../types';

export const ProtectedRoute: React.FC<{ children: React.ReactElement }> = ({ children }) => {
  const { isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div className="route-loading" role="status" aria-label="Loading">
        <div className="route-spinner" aria-hidden="true" />
        <p>Loading SchAI…</p>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return children;
};

export const RoleProtectedRoute: React.FC<{
  children: React.ReactElement;
  allowedRoles: UserRole[];
}> = ({ children, allowedRoles }) => {
  const { isAuthenticated, isLoading, role } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div className="route-loading" role="status" aria-label="Loading">
        <div className="route-spinner" aria-hidden="true" />
        <p>Loading SchAI…</p>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  if (!role || !allowedRoles.includes(role)) {
    return <Navigate to="/access-denied" replace />;
  }

  return children;
};
