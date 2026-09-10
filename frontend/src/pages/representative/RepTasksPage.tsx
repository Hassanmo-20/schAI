import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTasks } from '../../context/TaskContext';
import { useTaskFilters } from '../../hooks/useTaskFilters';
import { PageHeader, Button } from '../../components/common/Button';
import { ConfirmDialog, EmptyState, ErrorState, SkeletonCards } from '../../components/common/Feedback';
import TaskFilters from '../../components/tasks/TaskFilters';
import TaskCard from '../../components/tasks/TaskCard';
import '../../components/tasks/tasks.css';
import '../student/student.css';

const RepTasksPage: React.FC = () => {
  const { tasks, isLoading, error, fetchTasks, deleteTask } = useTasks();
  const { filters, setFilters, filtered } = useTaskFilters(tasks);
  const navigate = useNavigate();
  const [pendingDelete, setPendingDelete] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);

  const confirmDelete = async () => {
    if (!pendingDelete) return;
    setDeleting(true);
    try {
      await deleteTask(pendingDelete);
      setPendingDelete(null);
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="page-col">
      <PageHeader
        title="Manage Tasks"
        subtitle="Edit, view statistics or remove batch tasks."
        actions={<Link className="btn btn-primary" to="/representative/tasks/create">+ New task</Link>}
      />
      {isLoading ? (
        <SkeletonCards count={6} />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchTasks} />
      ) : tasks.length === 0 ? (
        <EmptyState title="No tasks yet" message="Create your first batch task to get started."
          action={<Link className="btn btn-primary" to="/representative/tasks/create">Create task</Link>} />
      ) : (
        <>
          <TaskFilters value={filters} onChange={setFilters} totalCount={tasks.length} shownCount={filtered.length} />
          {filtered.length === 0 ? (
            <EmptyState title="No tasks match these filters" message="Try a different status or search term." />
          ) : (
            <div className="task-grid">
              {filtered.map((t) => (
                <div key={t.id} className="rep-card-wrap">
                  <TaskCard task={t} detailPath={() => `/tasks/${t.id}`} />
                  <div className="rep-card-actions">
                    <Button size="sm" variant="secondary" onClick={() => navigate(`/representative/tasks/${t.id}/edit`)}>Edit</Button>
                    <Button size="sm" variant="secondary" onClick={() => navigate(`/representative/tasks/${t.id}/statistics`)}>Statistics</Button>
                    <Button size="sm" variant="danger" onClick={() => setPendingDelete(t.id)}>Delete</Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      )}
      {pendingDelete && (
        <ConfirmDialog
          title="Delete this task?"
          message="Are you sure you want to delete this task? Students will no longer see it. This cannot be undone."
          confirmLabel={deleting ? 'Deleting…' : 'Delete task'}
          onCancel={() => !deleting && setPendingDelete(null)}
          onConfirm={confirmDelete}
        />
      )}
    </div>
  );
};

export default RepTasksPage;
