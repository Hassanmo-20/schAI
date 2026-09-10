import React from 'react';
import { TaskType, UrgencyLevel } from '../../types';
import { formatUrgencyLabel } from '../../utils/dateUtils';
import './common.css';

export const TASK_TYPE_STYLES: Record<TaskType, string> = {
  Assignment: 'badge-assignment',
  Quiz: 'badge-quiz',
  Midterm: 'badge-midterm',
  Exam: 'badge-exam',
  Project: 'badge-project',
  Other: 'badge-other',
};

export const URGENCY_STYLES: Record<UrgencyLevel, string> = {
  overdue: 'urg-overdue',
  very_urgent: 'urg-very',
  high: 'urg-high',
  medium: 'urg-medium',
  normal: 'urg-normal',
};

export const TypeBadge: React.FC<{ type: TaskType }> = ({ type }) => (
  <span className={`badge ${TASK_TYPE_STYLES[type]}`}>{type}</span>
);

export const UrgencyBadge: React.FC<{ urgency: UrgencyLevel }> = ({ urgency }) => (
  <span className={`badge urgency ${URGENCY_STYLES[urgency]}`} aria-label={`Urgency: ${formatUrgencyLabel(urgency)}`}>
    {formatUrgencyLabel(urgency)}
  </span>
);

export const StatusBadge: React.FC<{ completed: boolean; overdue: boolean }> = ({ completed, overdue }) => {
  if (completed) return <span className="badge status-done">✓ Completed</span>;
  if (overdue) return <span className="badge status-overdue">Overdue</span>;
  return <span className="badge status-pending">Pending</span>;
};
