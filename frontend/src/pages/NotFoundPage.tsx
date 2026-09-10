import React from 'react';
import { Link } from 'react-router-dom';

export const NotFoundPage: React.FC = () => (
  <main className="error-page">
    <h1>404 — Page not found</h1>
    <p>The page you are looking for doesn&apos;t exist or was moved.</p>
    <Link className="btn btn-primary" to="/dashboard">Go to dashboard</Link>
  </main>
);

export default NotFoundPage;
