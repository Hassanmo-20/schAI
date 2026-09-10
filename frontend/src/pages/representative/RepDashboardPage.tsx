import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTasks } from '../../context/TaskContext';
import { statisticsService, BatchOverviewStats } from '../../services/statisticsService';
import { PageHeader } from '../../components/common/Button';
import { ErrorState, LoadingState } from '../../components/common/Feedback';
import { StatCard } from '../../components/common/Progress';
import TaskList from '../../components/tasks/TaskList';
import '../student/student.css';

const RepDashboardPage: React.FC = () => {
  const { tasks, isLoading, error, fetchTasks } = useTasks();
  const [overview, setOverview] = useState<BatchOverviewStats | null>(null);

  useEffect(() => {
    if (tasks.length > 0) {
      statisticsService.getBatchOverview(tasks).then(setOverview);
    }
  }, [tasks]);

  const recent = useMemo(() => [...tasks].slice(0, 4), [tasks]);

  if (isLoading) return <LoadingState message="Loading batch overview…" />;
  if (error) {
    return (
      <div className="page-col">
        <PageHeader title="Representative Dashboard" />
        <ErrorState message={error} onRetry={fetchTasks} />
      </div>
    );
  }

  return (
    <div className="page-col">
      <PageHeader
        title="Representative Dashboard"
        subtitle="Manage batch tasks, track completion and fix overdue items."
        actions={<Link className="btn btn-primary" to="/representative/tasks/create">+ New task</Link>}
      />
      <div className="rep-stats">
        <StatCard label="Total tasks" value={overview?.totalTasks ?? tasks.length} />
        <StatCard label="Active tasks" value={overview?.activeTasks ?? 0} sub="Incomplete" />
        <StatCard label="Overdue tasks" value={overview?.overdueTasks ?? 0} sub="Need attention" />
        <StatCard label="Avg. completion" value={`${overview?.averageCompletionRate ?? 0}%`} sub="Across all tasks" />
      </div>
      <div className="dash-section-head">
        <h2>Recent task activity</h2>
        <Link to="/representative/tasks">Manage all →</Link>
      </div>
      <TaskList tasks={recent} detailPath={(id) => `/tasks/${id}`} emptyTitle="No tasks yet" emptyMessage="Create your first batch task to get started." />
    </div>
  );
};

export default RepDashboardPage;
