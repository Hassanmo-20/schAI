import React, { useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useTasks } from '../../context/TaskContext';
import { PageHeader, Button } from '../../components/common/Button';
import { Field, Select } from '../../components/common/FormControls';
import { ConfirmDialog } from '../../components/common/Feedback';
import { USE_MOCK_DATA } from '../../services/apiClient';
import './student.css';

const SettingsPage: React.FC = () => {
  const { user, switchDemoRole } = useAuth();
  const { resetTasks } = useTasks();
  const [confirmReset, setConfirmReset] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  return (
    <div className="page-col narrow">
      <PageHeader title="Settings" subtitle="Your account and local development options." />
      {notice && <p className="settings-notice" role="status">{notice}</p>}

      <div className="card settings-card">
        <h2 className="settings-heading">Account</h2>
        <dl className="settings-list">
          <div><dt>Name</dt><dd>{user?.name}</dd></div>
          <div><dt>Email</dt><dd>{user?.email}</dd></div>
          <div><dt>Role</dt><dd>{user?.role === 'representative' ? 'Batch Representative' : 'Student'}</dd></div>
          <div><dt>Batch</dt><dd>{user?.batch || '—'}</dd></div>
        </dl>
        <p className="settings-hint">
          Your role and batch are managed by the university and can only be changed by an administrator.
        </p>
      </div>

      {/* Demo tools only exist against the local mock store. Against the real
          API the role comes from the token, so a client-side switch would just
          produce confusing 403s. */}
      {USE_MOCK_DATA && (
        <div className="card settings-card">
          <h2 className="settings-heading">Development tools</h2>
          <p className="settings-hint">
            Visible because <code>VITE_USE_MOCK_DATA=true</code>. These affect this browser only.
          </p>
          <Field label="Preview role" htmlFor="set-role" hint="Switch the mock session without logging out.">
            <Select
              id="set-role"
              value={user?.role ?? 'student'}
              onChange={(e) => {
                switchDemoRole(e.target.value as 'student' | 'representative');
                setNotice(`Switched to ${e.target.value} demo session.`);
              }}
            >
              <option value="student">Student</option>
              <option value="representative">Batch Representative</option>
            </Select>
          </Field>
          <div className="settings-actions">
            <Button variant="secondary" onClick={() => setConfirmReset(true)}>
              Reset demo tasks
            </Button>
          </div>
        </div>
      )}

      {confirmReset && (
        <ConfirmDialog
          title="Reset demo tasks?"
          message="This restores the original mock task list in this browser. Your completion changes will be lost."
          confirmLabel="Reset tasks"
          danger={false}
          onCancel={() => setConfirmReset(false)}
          onConfirm={() => {
            resetTasks();
            setConfirmReset(false);
            setNotice('Demo tasks restored.');
          }}
        />
      )}
    </div>
  );
};

export default SettingsPage;
