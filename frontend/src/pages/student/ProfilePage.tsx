import React from 'react';
import { useAuth } from '../../context/AuthContext';
import { useTasks } from '../../context/TaskContext';
import { PageHeader } from '../../components/common/Button';
import { ProgressBar } from '../../components/common/Progress';
import { getProgress } from '../../utils/taskHelpers';
import './student.css';

const ProfilePage: React.FC = () => {
  const { user } = useAuth();
  const { tasks } = useTasks();
  const progress = getProgress(tasks);

  return (
    <div className="page-col narrow">
      <PageHeader title="Account Profile" subtitle="Your SchAI identity and batch." />
      <div className="card profile-card">
        <div className="profile-top">
          <div className="profile-avatar" aria-hidden="true">
            {user?.avatarUrl ? (
              <img src={user.avatarUrl} alt="" onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
            ) : (
              user?.name?.charAt(0) ?? 'S'
            )}
          </div>
          <div>
            <h2 className="profile-name">{user?.name}</h2>
            <p className="profile-email">{user?.email}</p>
            <p className="profile-role">
              {user?.role === 'representative' ? 'Batch Representative' : 'Student'} · {user?.batch}
            </p>
          </div>
        </div>
        <div className="profile-progress">
          <span className="section-title">My completion</span>
          <ProgressBar completed={progress.completed} total={progress.total} label="My task completion" />
          <p className="profile-count">{progress.completed} of {progress.total} tasks completed</p>
        </div>
      </div>
    </div>
  );
};

export default ProfilePage;
