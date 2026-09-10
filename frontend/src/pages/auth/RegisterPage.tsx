import React, { useEffect, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { Field, Input, Select } from '../../components/common/FormControls';
import { Button } from '../../components/common/Button';
import { batchService } from '../../services/batchService';
import { RegistrationOptions, UserRole } from '../../types';
import './auth.css';

function isValidEmail(v: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

const EMPTY_OPTIONS: RegistrationOptions = { batchYears: [], departments: [], roles: [] };

const RegisterPage: React.FC = () => {
  const { register, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  // A group is the (batch year, department) PAIR, submitted as its two halves.
  // The API resolves them to the real group row, so the browser never sends —
  // and can never invent — a batch id.
  const [options, setOptions] = useState<RegistrationOptions>(EMPTY_OPTIONS);
  const [batchYear, setBatchYear] = useState('');
  const [department, setDepartment] = useState('');
  const [role, setRole] = useState<UserRole>('student');
  const [optionsError, setOptionsError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [serverError, setServerError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let active = true;
    batchService
      .getRegistrationOptions()
      .then((opts) => {
        if (!active) return;
        setOptions(opts);
        setBatchYear((current) => current || (opts.batchYears[0]?.value ?? ''));
        setDepartment((current) => current || (opts.departments[0]?.value ?? ''));
        setOptionsError(null);
      })
      .catch(() =>
        active && setOptionsError('Could not load the sign-up options. Please refresh and try again.')
      );
    return () => {
      active = false;
    };
  }, []);

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  const loading = options.batchYears.length === 0 && !optionsError;

  const validate = (): boolean => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Full name is required.';
    if (!email.trim()) e.email = 'Email is required.';
    else if (!isValidEmail(email.trim())) e.email = 'Enter a valid email address.';
    if (!password) e.password = 'Password is required.';
    else if (password.length < 8) e.password = 'Password must be at least 8 characters.';
    if (confirm !== password) e.confirm = 'Passwords do not match.';
    if (!batchYear) e.batchYear = 'Please choose your batch.';
    if (!department) e.department = 'Please choose your department.';
    if (!role) e.role = 'Please choose your role.';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setServerError(null);
    if (!validate()) return;
    setSubmitting(true);
    try {
      await register({
        name: name.trim(),
        email: email.trim(),
        password,
        batchYear,
        department,
        role,
      });
      navigate(role === 'representative' ? '/representative' : '/dashboard', { replace: true });
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
        <h1>Create your account</h1>
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

            <div className="auth-field-row">
              <Field label="Batch" htmlFor="reg-batch-year" error={errors.batchYear}>
                <Select
                  id="reg-batch-year"
                  value={batchYear}
                  onChange={(e) => setBatchYear(e.target.value)}
                  error={errors.batchYear}
                  disabled={loading}
                >
                  {loading && <option value="">Loading…</option>}
                  {options.batchYears.map((b) => (
                    <option key={b.value} value={b.value}>{b.label}</option>
                  ))}
                </Select>
              </Field>
              <Field label="Department" htmlFor="reg-department" error={errors.department}>
                <Select
                  id="reg-department"
                  value={department}
                  onChange={(e) => setDepartment(e.target.value)}
                  error={errors.department}
                  disabled={loading}
                >
                  {loading && <option value="">Loading…</option>}
                  {options.departments.map((d) => (
                    <option key={d.value} value={d.value} title={d.label}>{d.value}</option>
                  ))}
                </Select>
              </Field>
            </div>

            <Field
              label="I am a"
              htmlFor="reg-role"
              error={errors.role ?? optionsError ?? undefined}
              hint="Representatives can post and manage tasks for their own group."
            >
              <Select
                id="reg-role"
                value={role}
                onChange={(e) => setRole(e.target.value as UserRole)}
                error={errors.role}
              >
                {(options.roles.length > 0
                  ? options.roles
                  : [
                      { value: 'student', label: 'Student' },
                      { value: 'representative', label: 'Batch Representative' },
                    ]
                ).map((r) => (
                  <option key={r.value} value={r.value}>{r.label}</option>
                ))}
              </Select>
            </Field>

            <p className="auth-group-preview">
              You are joining <strong>{batchYear || '—'} {department}</strong>. Only this group&apos;s
              tasks will be visible to you.
            </p>

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
