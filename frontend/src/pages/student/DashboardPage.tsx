import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useTasks } from '../../context/TaskContext';
import { PageHeader } from '../../components/common/Button';
import { EmptyState, ErrorState, SkeletonCards } from '../../components/common/Feedback';
import { ProgressRing } from '../../components/common/Progress';
import TaskList from '../../components/tasks/TaskList';
import DeadlineCalendar from '../../components/tasks/DeadlineCalendar';
import AcademicAssistant from '../../components/tasks/AcademicAssistant';
import { formatRemainingTime } from '../../utils/dateUtils';
import { countUrgentTasks, getProgress, getUrgentTasks } from '../../utils/taskHelpers';
import './student.css';

function greeting(): string {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 18) return 'Good afternoon';
  return 'Good evening';
}

const DashboardPage: React.FC = () => {
  const { user } = useAuth();
  const { tasks, isLoading, error, fetchTasks, toggleComplete } = useTasks();
  const [togglingId, setTogglingId] = useState<string | null>(null);

  const progress = useMemo(() => getProgress(tasks), [tasks]);
  const urgent = useMemo(() => getUrgentTasks(tasks, 4), [tasks]);
  const urgentTotal = useMemo(() => countUrgentTasks(tasks), [tasks]);
  const upcoming = useMemo(
    () =>
      tasks
        .filter((t) => !t.isCompleted)
        .sort((a, b) => new Date(a.deadline).getTime() - new Date(b.deadline).getTime())
        .slice(0, 6),
    [tasks]
  );

  const handleToggle = async (id: string) => {
    setTogglingId(id);
    try {
      await toggleComplete(id);
    } finally {
      setTogglingId(null);
    }
  };

  if (isLoading) {
    return (
      <div>
        <PageHeader title="Dashboard" subtitle="Loading your academic overview…" />
        <SkeletonCards count={4} />
      </div>
    );
  }

  if (error) {
    return (
      <div>
        <PageHeader title="Dashboard" />
        <ErrorState message={error} onRetry={fetchTasks} />
      </div>
    );
  }

  return (
    <div className="dash">
      <PageHeader
        title={`${greeting()}, ${user?.name?.split(' ')[0] ?? 'Student'} 👋`}
        subtitle={`${user?.batch ?? ''} · ${progress.completed} of ${progress.total} tasks completed`}
        actions={<Link className="btn btn-primary" to="/tasks">View all tasks</Link>}
      />

      <div className="dash-grid">
        <section className="card progress-card" aria-label="Task progress">
          <div className="progress-head">
            <span className="section-title">Task Progress</span>
            <span className="section-title">Completion</span>
          </div>
          <ProgressRing completed={progress.completed} total={progress.total} />
          <div className="legend">
            <div><span className="swatch done" /> Completed Tasks ({progress.completed})</div>
            <div>
              <span className="swatch left" /> Remaining Tasks ({progress.total - progress.completed}
              {progress.total > 0 ? `, ${100 - progress.pct}% left` : ''})
            </div>
          </div>
        </section>

        <section className={`alerts${urgent.length === 0 ? ' alerts-calmer' : ''}`} aria-label="Urgent tasks">
          <div className="alerts-head">⚠ URGENT TASKS</div>
          {urgent.length === 0 ? (
            <p className="urgent-empty">No urgent tasks 🎉 — everything is under control.</p>
          ) : (
            <>
              <ul className="alerts-list">
                {urgent.map((t) => (
                  <li key={t.id}>
                    <Link to={`/tasks/${t.id}`}>
                      <strong>{t.title.length > 42 ? `${t.title.slice(0, 42)}…` : t.title}</strong>
                      <span>{formatRemainingTime(t.deadline, t.isCompleted)}</span>
                    </Link>
                  </li>
                ))}
              </ul>
              {urgentTotal > urgent.length && (
                <Link className="see-more" to="/tasks">See More ({urgentTotal - urgent.length} more) →</Link>
              )}
            </>
          )}
        </section>

        <div className="dash-wide">
          <DeadlineCalendar tasks={tasks} />
        </div>

        <div className="dash-wide">
          <div className="dash-section-head">
            <h2>Upcoming deadlines</h2>
            <Link to="/tasks">See all →</Link>
          </div>
          {upcoming.length === 0 ? (
            <EmptyState title="No upcoming tasks 🎉" message="Enjoy the quiet week — new batch tasks will show up here." />
          ) : (
            <TaskList tasks={upcoming} onToggleComplete={handleToggle} togglingId={togglingId} />
          )}
        </div>

        <AcademicAssistant />
      </div>
    </div>
  );
};

export default DashboardPage;
