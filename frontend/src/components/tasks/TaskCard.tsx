import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { AcademicTask } from '../../types';
import { calculateUrgency, formatDateTime, formatRemainingTime } from '../../utils/dateUtils';
import { StatusBadge, TypeBadge, UrgencyBadge } from '../common/Badge';
import './tasks.css';

interface TaskCardProps {
  task: AcademicTask;
  onToggleComplete?: (id: string) => void;
  toggling?: boolean;
  detailPath?: (id: string) => string;
}

const TaskCard: React.FC<TaskCardProps> = ({ task, onToggleComplete, toggling, detailPath }) => {
  const [imgBroken, setImgBroken] = useState(false);
  const urgency = calculateUrgency(task.deadline, task.isCompleted);
  const remaining = formatRemainingTime(task.deadline, task.isCompleted);
  const isOverdue = urgency === 'overdue';
  const to = detailPath ? detailPath(task.id) : `/tasks/${task.id}`;
  const image = task.attachments?.find((a) => a.type === 'image');
  const fileCount = task.attachments?.length ?? 0;

  return (
    <article className={`card task-card${task.isCompleted ? ' task-done' : ''}${isOverdue ? ' task-overdue' : ''}`}>
      {image && !imgBroken && (
        <div className="task-thumb">
          <img
            src={image.url}
            alt=""
            loading="lazy"
            onError={() => setImgBroken(true)}
          />
        </div>
      )}
      <div className="task-body">
        <div className="task-badges">
          <TypeBadge type={task.type} />
          <UrgencyBadge urgency={urgency} />
        </div>
        <h3 className="task-title">
          <Link to={to}>{task.title}</Link>
        </h3>
        <p className="task-deadline" aria-label={`Deadline: ${formatDateTime(task.deadline)}`}>
          🕒 {remaining} · {formatDateTime(task.deadline)}
        </p>
        <p className="task-desc">{task.description}</p>
        <div className="task-meta">
          <StatusBadge completed={!!task.isCompleted} overdue={isOverdue} />
          {fileCount > 0 && (
            <span className="task-files" title={`${fileCount} attachment${fileCount > 1 ? 's' : ''}`}>
              📎 {fileCount}
            </span>
          )}
        </div>
        <div className="task-actions">
          <Link className="btn btn-secondary btn-sm" to={to}>
            Open details
          </Link>
          {onToggleComplete && (
            <button
              type="button"
              className={`btn btn-sm ${task.isCompleted ? 'btn-secondary' : 'btn-primary'}`}
              disabled={toggling}
              onClick={() => onToggleComplete(task.id)}
              aria-pressed={!!task.isCompleted}
              aria-label={task.isCompleted ? `Mark ${task.title} as incomplete` : `Mark ${task.title} as complete`}
            >
              {toggling ? '…' : task.isCompleted ? '✓ Done — undo' : 'Complete'}
            </button>
          )}
        </div>
      </div>
    </article>
  );
};

export default TaskCard;
