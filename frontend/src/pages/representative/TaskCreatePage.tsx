import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useTasks } from '../../context/TaskContext';
import { PageHeader } from '../../components/common/Button';
import TaskForm, { TaskFormValues } from '../../components/tasks/TaskForm';
import '../../pages/student/student.css';

const TaskCreatePage: React.FC = () => {
  const { user } = useAuth();
  const { createTask } = useTasks();
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const handleSubmit = async (values: TaskFormValues, files: File[]) => {
    setSubmitting(true);
    setServerError(null);
    try {
      const created = await createTask(
        {
          title: values.title.trim(),
          description: values.description.trim() || 'No description provided.',
          type: values.type,
          deadline: new Date(values.deadline).toISOString(),
          // Batch and ownership are assigned by the API from the auth token;
          // these values are only used by the local mock store.
          batch: user?.batch ?? '',
          createdBy: user?.id ?? 'usr_rep_01',
          createdByName: user?.name ?? 'Batch Representative',
          isCompleted: false,
          totalStudents: 50,
          completedStudents: 0,
          attachments: files.map((file, i) => ({
            id: `att_${Date.now()}_${i}`,
            name: file.name,
            url: URL.createObjectURL(file),
            type: (file.type.startsWith('image/')
              ? 'image'
              : file.type === 'application/pdf'
                ? 'pdf'
                : 'file') as 'image' | 'pdf' | 'file',
            size: `${Math.max(1, Math.round(file.size / 1024))} KB`,
          })),
        },
        files
      );
      setSuccess(`“${created.title}” created successfully.`);
      setTimeout(() => navigate('/representative/tasks'), 900);
    } catch (err: any) {
      setServerError(err?.message ?? 'Task creation failed. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="page-col narrow">
      <Link className="back-link" to="/representative/tasks">← Back to tasks</Link>
      <PageHeader title="Create Task" subtitle="Post a new task for your batch." />
      {success && <p className="settings-notice" role="status">{success}</p>}
      <div className="card settings-card">
        <TaskForm submitLabel="Create task" submitting={submitting} serverError={serverError} onSubmit={handleSubmit} />
      </div>
    </div>
  );
};

export default TaskCreatePage;
