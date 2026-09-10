import React from 'react';
import { AcademicTask } from '../../types';
import TaskCard from './TaskCard';
import { EmptyState } from '../common/Feedback';
import './tasks.css';

interface TaskListProps {
  tasks: AcademicTask[];
  onToggleComplete?: (id: string) => void;
  togglingId?: string | null;
  detailPath?: (id: string) => string;
  emptyTitle?: string;
  emptyMessage?: string;
}

const TaskList: React.FC<TaskListProps> = ({
  tasks,
  onToggleComplete,
  togglingId,
  detailPath,
  emptyTitle = 'No tasks here',
  emptyMessage = 'Tasks assigned to your batch will appear here.',
}) => {
  if (tasks.length === 0) {
    return <EmptyState title={emptyTitle} message={emptyMessage} />;
  }
  return (
    <div className="task-grid">
      {tasks.map((t) => (
        <TaskCard
          key={t.id}
          task={t}
          onToggleComplete={onToggleComplete}
          toggling={togglingId === t.id}
          detailPath={detailPath}
        />
      ))}
    </div>
  );
};

export default TaskList;
