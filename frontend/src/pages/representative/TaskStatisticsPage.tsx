import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { statisticsService } from '../../services/statisticsService';
import { TaskStatisticsData } from '../../types';
import { PageHeader } from '../../components/common/Button';
import { ErrorState, LoadingState } from '../../components/common/Feedback';
import TaskStatistics from '../../components/statistics/TaskStatistics';
import '../../pages/student/student.css';

const TaskStatisticsPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const [stats, setStats] = useState<TaskStatisticsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = () => {
    if (!id) {
      setError('Missing task id.');
      setLoading(false);
      return;
    }
    setLoading(true);
    statisticsService
      .getTaskStatistics(id)
      .then((s) => {
        setStats(s);
        setError(null);
      })
      .catch((e: any) => setError(e?.message ?? 'Failed to load statistics.'))
      .finally(() => setLoading(false));
  };

  useEffect(load, [id]);

  return (
    <div className="page-col narrow">
      <Link className="back-link" to="/representative/tasks">← Back to tasks</Link>
      <PageHeader title="Task Statistics" subtitle={stats?.taskTitle ?? 'Completion overview'} />
      {loading ? (
        <LoadingState message="Loading statistics…" />
      ) : error || !stats ? (
        <ErrorState message={error ?? 'Statistics unavailable.'} onRetry={load} />
      ) : (
        <>
          <TaskStatistics stats={stats} />
          {stats.submissionsByDate && stats.submissionsByDate.length > 0 && (
            <div className="card settings-card">
              <span className="section-title">Submissions by day</span>
              <ul className="submissions-list">
                {stats.submissionsByDate.map((row) => (
                  <li key={row.date}>
                    <span>{row.date}</span>
                    <strong>{row.count}</strong>
                  </li>
                ))}
              </ul>
            </div>
          )}
          <div className="detail-actions">
            <Link className="btn btn-secondary" to={`/tasks/${id}`}>View as student</Link>
            <Link className="btn btn-primary" to={`/representative/tasks/${id}/edit`}>Edit task</Link>
          </div>
        </>
      )}
    </div>
  );
};

export default TaskStatisticsPage;
