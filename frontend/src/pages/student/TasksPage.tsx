import React, { useState } from 'react';
import { useTasks } from '../../context/TaskContext';
import { useTaskFilters } from '../../hooks/useTaskFilters';
import { PageHeader } from '../../components/common/Button';
import { EmptyState, ErrorState, SkeletonCards } from '../../components/common/Feedback';
import TaskFilters from '../../components/tasks/TaskFilters';
import TaskList from '../../components/tasks/TaskList';
import './student.css';

const TasksPage: React.FC = () => {
  const { tasks, isLoading, error, fetchTasks, toggleComplete } = useTasks();
  const { filters, setFilters, filtered } = useTaskFilters(tasks);
  const [togglingId, setTogglingId] = useState<string | null>(null);

  const handleToggle = async (id: string) => {
    setTogglingId(id);
    try {
      await toggleComplete(id);
    } finally {
      setTogglingId(null);
    }
  };

  return (
    <div className="page-col">
      <PageHeader title="My Tasks" subtitle="Every task for your batch — filter, sort and complete." />

      {isLoading ? (
        <SkeletonCards count={6} />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchTasks} />
      ) : tasks.length === 0 ? (
        <EmptyState title="No tasks yet" message="Your batch representative hasn't posted anything. Check back soon." />
      ) : (
        <>
          <TaskFilters value={filters} onChange={setFilters} totalCount={tasks.length} shownCount={filtered.length} />
          {filtered.length === 0 ? (
            <EmptyState
              title="No tasks match these filters"
              message="Try clearing the search or choosing a different status."
            />
          ) : (
            <TaskList tasks={filtered} onToggleComplete={handleToggle} togglingId={togglingId} />
          )}
        </>
      )}
    </div>
  );
};

export default TasksPage;
