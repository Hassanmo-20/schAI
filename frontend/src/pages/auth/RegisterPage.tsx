import React, { useEffect, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { Field, Input, Select } from '../../components/common/FormControls';
import { Button } from '../../components/common/Button';
import { batchService } from '../../services/batchService';
import { Batch } from '../../types';
import './auth.css';

function isValidEmail(v: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

const RegisterPage: React.FC = () => {
  const { register, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  // Batch is submitted by id: the API validates `batch_id` against the
  // batches table, so a hardcoded list of names could never register.
  const [batches, setBatches] = useState<Batch[]>([]);
  const [batchId, setBatchId] = useState('');
  const [batchError, setBatchError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [serverError, setServerError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let active = true;
    batchService
      .getBatches()
      .then((list) => {
        if (!active) return;
        setBatches(list);
        setBatchId((current) => current || (list[0]?.id ?? ''));
        setBatchError(null);
      })
      .catch(() => active && setBatchError('Could not load batches. Please refresh and try again.'));
    return () => {
      active = false;
    };
  }, []);

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  const validate = (): boolean => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Full name is required.';
    if (!email.trim()) e.email = 'Email is required.';
    else if (!isValidEmail(email.trim())) e.email = 'Enter a valid email address.';
    if (!password) e.password = 'Password is required.';
    else if (password.length < 8) e.password = 'Password must be at least 8 characters.';
    if (confirm !== password) e.confirm = 'Passwords do not match.';
    if (!batchId) e.batch = 'Batch is required.';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setServerError(null);
    if (!validate()) return;
    setSubmitting(true);
    try {
      // Public registration always creates a Student; reps are provisioned separately.
      await register({ name: name.trim(), email: email.trim(), password, batch: batchId });
      navigate('/dashboard', { replace: true });
    } catch (err: any) {
      setServerError(err?.message ?? 'Registration failed. Please try again.');
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
        <h1>Create your student account</h1>
        <p>Track assignments, quizzes, midterms and projects for your batch — all in one hub.</p>
      </section>

      <section className="auth-panel">
        <div className="auth-card">
          <div className="auth-card-header">Create Account</div>
          <form onSubmit={handleSubmit} noValidate>
            {serverError && (
              <p className="form-error" role="alert" style={{ marginBottom: 12 }}>{serverError}</p>
            )}
            <Field label="Full name" htmlFor="reg-name" error={errors.name}>
              <Input id="reg-name" type="text" placeholder="e.g. Alex Mercer" autoComplete="name"
                value={name} onChange={(e) => setName(e.target.value)} error={errors.name} />
            </Field>
            <Field label="Email Address" htmlFor="reg-email" error={errors.email}>
              <Input id="reg-email" type="email" placeholder="your.email@example.com" autoComplete="email"
                value={email} onChange={(e) => setEmail(e.target.value)} error={errors.email} />
            </Field>
            <Field label="Password" htmlFor="reg-password" error={errors.password} hint="Minimum 8 characters.">
              <Input id="reg-password" type="password" placeholder="********" autoComplete="new-password"
                value={password} onChange={(e) => setPassword(e.target.value)} error={errors.password} />
            </Field>
            <Field label="Confirm password" htmlFor="reg-confirm" error={errors.confirm}>
              <Input id="reg-confirm" type="password" placeholder="********" autoComplete="new-password"
                value={confirm} onChange={(e) => setConfirm(e.target.value)} error={errors.confirm} />
            </Field>
            <Field label="Batch" htmlFor="reg-batch" error={errors.batch ?? batchError ?? undefined}>
              <Select
                id="reg-batch"
                value={batchId}
                onChange={(e) => setBatchId(e.target.value)}
                error={errors.batch}
                disabled={batches.length === 0}
              >
                {batches.length === 0 && <option value="">Loading batches…</option>}
                {batches.map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </Select>
            </Field>
            <Button type="submit" loading={submitting} className="auth-submit">
              Create Account
            </Button>
            <p className="auth-switch">
              Already have an account? <Link to="/login">Sign In</Link>
            </p>
          </form>
        </div>
      </section>
    </main>
  );
};

export default RegisterPage;
