import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTasks } from '../../context/TaskContext';
import { taskService } from '../../services/taskService';
import { AcademicTask } from '../../types';
import { PageHeader } from '../../components/common/Button';
import { EmptyState, LoadingState } from '../../components/common/Feedback';
import TaskForm, { TaskFormValues } from '../../components/tasks/TaskForm';
import '../../pages/student/student.css';

const TaskEditPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { getTaskById, updateTask, fetchTasks } = useTasks();
  const navigate = useNavigate();
  const cached = id ? getTaskById(id) : undefined;

  // On a direct visit/refresh the context is still empty, so fall back to the
  // service instead of flashing "Task not found".
  const [task, setTask] = useState<AcademicTask | null>(cached ?? null);
  const [loading, setLoading] = useState(!cached);
  const [submitting, setSubmitting] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    if (cached) {
      setTask(cached);
      setLoading(false);
      return;
    }
    let active = true;
    setLoading(true);
    taskService
      .getTaskById(id)
      .then((t) => active && setTask(t))
      .catch(() => active && setTask(null))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [id, cached]);

  if (loading) {
    return <LoadingState message="Loading task…" />;
  }

  if (!id || !task) {
    return (
      <div className="page-col">
        <EmptyState
          title="Task not found"
          message="It may have been deleted."
          action={<Link className="btn btn-secondary" to="/representative/tasks">Back to tasks</Link>}
        />
      </div>
    );
  }

  const handleSubmit = async (values: TaskFormValues, files: File[]) => {
    setSubmitting(true);
    setServerError(null);
    try {
      await updateTask(task.id, {
        title: values.title.trim(),
        description: values.description.trim() || task.description,
        type: values.type,
        deadline: new Date(values.deadline).toISOString(),
      });

      if (files.length > 0) {
        await taskService.uploadAttachments(task.id, files);
        await fetchTasks();
      }

      setSuccess('Task updated successfully.');
      setTimeout(() => navigate('/representative/tasks'), 900);
    } catch (err: any) {
      setServerError(err?.message ?? 'Update failed. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="page-col narrow">
      <Link className="back-link" to="/representative/tasks">← Back to tasks</Link>
      <PageHeader title="Edit Task" subtitle={task.title} />
      {success && <p className="settings-notice" role="status">{success}</p>}
      <div className="card settings-card">
        <TaskForm
          initial={task}
          submitLabel="Save changes"
          submitting={submitting}
          serverError={serverError}
          onSubmit={handleSubmit}
        />
      </div>
    </div>
  );
};

export default TaskEditPage;
