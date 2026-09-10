import React, { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { Field, Input } from '../../components/common/FormControls';
import { Button } from '../../components/common/Button';
import { safeRedirectPath } from '../../utils/safeRedirect';
import './auth.css';

function isValidEmail(v: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

const LoginPage: React.FC = () => {
  const { login, isAuthenticated, role } = useAuth();
  const navigate = useNavigate();
  const location = useLocation() as { state?: { from?: string } };
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(true);
  const [errors, setErrors] = useState<{ email?: string; password?: string }>({});
  const [serverError, setServerError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  if (isAuthenticated) {
    const fallback = role === 'representative' ? '/representative' : '/dashboard';
    return <Navigate to={safeRedirectPath(location.state?.from, fallback)} replace />;
  }

  const validate = (): boolean => {
    const e: typeof errors = {};
    if (!email.trim()) e.email = 'Email is required.';
    else if (!isValidEmail(email.trim())) e.email = 'Enter a valid email address.';
    if (!password) e.password = 'Password is required.';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setServerError(null);
    if (!validate()) return;
    setSubmitting(true);
    try {
      await login({ email: email.trim(), password, rememberMe });
      // Navigation happens via isAuthenticated redirect; push immediately as well.
      const fallback = email.toLowerCase().includes('rep') ? '/representative' : '/dashboard';
      navigate(safeRedirectPath(location.state?.from, fallback), { replace: true });
    } catch (err: any) {
      setServerError(err?.message ?? 'Login failed. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <main className="auth-shell">
      <section className="auth-brand" aria-label="About SchAI">
        <div className="auth-logo">
          <span className="auth-logo-text">Sch<span className="ai">AI</span></span>
        </div>
        <h1>SchAI: Your Intelligent Task Assistant</h1>
        <p>Streamline your schedule, track your progress, and get things done.</p>
      </section>

      <section className="auth-panel">
        <div className="auth-card">
          <div className="auth-card-header">Sign In</div>
          <form onSubmit={handleSubmit} noValidate>
            {serverError && (
              <p className="form-error" role="alert" style={{ marginBottom: 12 }}>{serverError}</p>
            )}
            <Field label="Email Address" htmlFor="login-email" error={errors.email}>
              <Input
                id="login-email" type="email" placeholder="your.email@example.com"
                autoComplete="email" value={email}
                onChange={(e) => setEmail(e.target.value)} error={errors.email}
              />
            </Field>
            <Field label="Password" htmlFor="login-password" error={errors.password}>
              <Input
                id="login-password" type="password" placeholder="********"
                autoComplete="current-password" value={password}
                onChange={(e) => setPassword(e.target.value)} error={errors.password}
              />
            </Field>
            <div className="auth-row">
              <label className="remember">
                <input
                  type="checkbox" checked={rememberMe}
                  onChange={(e) => setRememberMe(e.target.checked)}
                />
                Remember me
              </label>
              <span className="auth-hint" title="Password reset is not available yet">
                Forgot password? Contact your batch representative.
              </span>
            </div>
            <Button type="submit" loading={submitting} className="auth-submit">
              Sign In
            </Button>
            <p className="auth-switch">
              Don&apos;t have an account? <Link to="/register">Create Account</Link>
            </p>
          </form>
        </div>
      </section>
    </main>
  );
};

export default LoginPage;
