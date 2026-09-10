import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export const AccessDeniedPage: React.FC = () => {
  const { role } = useAuth();
  return (
    <main className="error-page">
      <h1>Access denied</h1>
      <p>
        {role === 'student'
          ? 'This area is for batch representatives only.'
          : 'You do not have permission to view this page.'}
      </p>
      <Link className="btn btn-primary" to="/dashboard">Back to dashboard</Link>
    </main>
  );
};

export default AccessDeniedPage;
