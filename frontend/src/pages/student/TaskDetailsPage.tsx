import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTasks } from '../../context/TaskContext';
import { useAuth } from '../../context/AuthContext';
import { taskService } from '../../services/taskService';
import { AcademicTask } from '../../types';
import { calculateUrgency, formatDateTime, formatRemainingTime } from '../../utils/dateUtils';
import { StatusBadge, TypeBadge, UrgencyBadge } from '../../components/common/Badge';
import { Button } from '../../components/common/Button';
import { AttachmentList } from '../../components/tasks/AttachmentPreview';
import { EmptyState, ErrorState, LoadingState } from '../../components/common/Feedback';
import './student.css';

const TaskDetailsPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { getTaskById, toggleComplete } = useTasks();
  const { hasRole } = useAuth();
  const navigate = useNavigate();
  const [task, setTask] = useState<AcademicTask | null>(() => (id ? getTaskById(id) ?? null : null));
  const [loading, setLoading] = useState(!task);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [toggling, setToggling] = useState(false);

  useEffect(() => {
    if (!id) return;
    const cached = getTaskById(id);
    if (cached) {
      setTask(cached);
      setLoading(false);
      return;
    }
    setLoading(true);
    taskService
      .getTaskById(id)
      .then((t) => {
        setTask(t);
        setLoadError(null);
      })
      .catch(() => setLoadError('Task not found. It may have been removed by your representative.'))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (loading) return <LoadingState message="Loading task…" />;
  if (loadError || !task) {
    return (
      <div className="page-col">
        {loadError ? (
          <ErrorState message={loadError} onRetry={() => navigate('/tasks')} />
        ) : (
          <EmptyState title="Task not found" message="It may have been removed." action={<Link className="btn btn-secondary" to="/tasks">Back to tasks</Link>} />
        )}
      </div>
    );
  }

  const urgency = calculateUrgency(task.deadline, task.isCompleted);
  const isRep = hasRole('representative');

  const handleToggle = async () => {
    setToggling(true);
    try {
      await toggleComplete(task.id);
      const fresh = await taskService.getTaskById(task.id).catch(() => null);
      if (fresh) setTask(fresh);
      else setTask((prev) => (prev ? { ...prev, isCompleted: !prev.isCompleted } : prev));
    } finally {
      setToggling(false);
    }
  };

  return (
    <div className="page-col detail">
      <Link className="back-link" to="/tasks">← Back to tasks</Link>
      <div className="card detail-card">
        <div className="task-badges">
          <TypeBadge type={task.type} />
          <UrgencyBadge urgency={urgency} />
          <StatusBadge completed={!!task.isCompleted} overdue={urgency === 'overdue'} />
        </div>
        <h1 className="detail-title">{task.title}</h1>
        <p className="task-deadline">
          🕒 {formatRemainingTime(task.deadline, task.isCompleted)} · {formatDateTime(task.deadline)}
        </p>
        <p className="detail-meta">
          Posted by {task.createdByName ?? 'Batch Representative'} · {task.batch}
        </p>
        <div className="detail-desc">
          {task.description.split('\n').map((p, i) => (
            <p key={i}>{p}</p>
          ))}
        </div>

        <h2 className="detail-h2">Attachments</h2>
        <AttachmentList attachments={task.attachments} />

        <div className="detail-actions">
          {!isRep && (
            <Button variant={task.isCompleted ? 'secondary' : 'primary'} onClick={handleToggle} loading={toggling}>
              {task.isCompleted ? 'Mark as incomplete' : 'Mark as complete'}
            </Button>
          )}
          {isRep && (
            <>
              <Link className="btn btn-primary" to={`/representative/tasks/${task.id}/edit`}>Edit task</Link>
              <Link className="btn btn-secondary" to={`/representative/tasks/${task.id}/statistics`}>View statistics</Link>
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default TaskDetailsPage;
