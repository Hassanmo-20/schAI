import { AcademicTask, UrgencyLevel } from '../types';
import { calculateUrgency } from './dateUtils';

/** Sort key weight: lower = more urgent. Completed tasks sink to the bottom. */
const URGENCY_WEIGHT: Record<UrgencyLevel, number> = {
  overdue: 0,
  very_urgent: 1,
  high: 2,
  medium: 3,
  normal: 4,
};

export function getUrgency(task: AcademicTask): UrgencyLevel {
  return calculateUrgency(task.deadline, task.isCompleted);
}

export function compareByUrgency(a: AcademicTask, b: AcademicTask): number {
  const ua = getUrgency(a);
  const ub = getUrgency(b);
  if (ua !== ub) return URGENCY_WEIGHT[ua] - URGENCY_WEIGHT[ub];
  return new Date(a.deadline).getTime() - new Date(b.deadline).getTime();
}

/**
 * Urgent tasks for the dashboard: incomplete tasks with urgency above
 * "normal", sorted nearest-deadline first, capped at `limit`.
 */
export function getUrgentTasks(tasks: AcademicTask[], limit = 4): AcademicTask[] {
  return tasks
    .filter((t) => !t.isCompleted && getUrgency(t) !== 'normal')
    .sort((a, b) => new Date(a.deadline).getTime() - new Date(b.deadline).getTime())
    .slice(0, limit);
}

export function countUrgentTasks(tasks: AcademicTask[]): number {
  return tasks.filter((t) => !t.isCompleted && getUrgency(t) !== 'normal').length;
}

export function getProgress(tasks: AcademicTask[]): { completed: number; total: number; pct: number } {
  const total = tasks.length;
  const completed = tasks.filter((t) => t.isCompleted).length;
  const pct = total === 0 ? 0 : Math.round((completed / total) * 100);
  return { completed, total, pct };
}
