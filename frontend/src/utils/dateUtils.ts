import { UrgencyLevel } from '../types';

export function calculateUrgency(deadlineIso: string, isCompleted: boolean = false): UrgencyLevel {
  if (isCompleted) {
    return 'normal';
  }

  const now = new Date();
  const deadline = new Date(deadlineIso);
  const diffMs = deadline.getTime() - now.getTime();
  const diffHours = diffMs / (1000 * 60 * 60);

  if (diffMs < 0) {
    return 'overdue';
  }
  if (diffHours <= 24) {
    return 'very_urgent';
  }
  if (diffHours <= 72) { // 2-3 days
    return 'high';
  }
  if (diffHours <= 168) { // 7 days
    return 'medium';
  }
  return 'normal';
}

export function formatUrgencyLabel(urgency: UrgencyLevel): string {
  switch (urgency) {
    case 'overdue':
      return 'Overdue';
    case 'very_urgent':
      return 'Very Urgent';
    case 'high':
      return 'High Priority';
    case 'medium':
      return 'Medium';
    case 'normal':
      return 'Normal';
  }
}

export function formatRemainingTime(deadlineIso: string, isCompleted: boolean = false): string {
  if (isCompleted) {
    return 'Completed';
  }

  const now = new Date();
  const deadline = new Date(deadlineIso);
  const diffMs = deadline.getTime() - now.getTime();
  const absDiff = Math.abs(diffMs);

  const diffMinutes = Math.floor(absDiff / (1000 * 60));
  const diffHours = Math.floor(absDiff / (1000 * 60 * 60));
  const diffDays = Math.floor(absDiff / (1000 * 60 * 60 * 24));

  if (diffMs < 0) {
    if (diffDays > 0) return `Overdue by ${diffDays} day${diffDays > 1 ? 's' : ''}`;
    if (diffHours > 0) return `Overdue by ${diffHours} hr${diffHours > 1 ? 's' : ''}`;
    return `Overdue by ${diffMinutes} min${diffMinutes > 1 ? 's' : ''}`;
  }

  // Same calendar day check
  const isSameDay = now.toDateString() === deadline.toDateString();
  if (isSameDay) {
    if (diffHours < 1) return `Due in ${diffMinutes} min${diffMinutes > 1 ? 's' : ''}`;
    return `Due tonight (${diffHours} hr${diffHours > 1 ? 's' : ''} left)`;
  }

  // Tomorrow check
  const tomorrow = new Date(now);
  tomorrow.setDate(now.getDate() + 1);
  if (tomorrow.toDateString() === deadline.toDateString()) {
    return 'Due tomorrow';
  }

  if (diffDays <= 7) {
    return `Due in ${diffDays} days`;
  }

  return `Due in ${diffDays} days`;
}

export function formatDateTime(isoString: string): string {
  try {
    const date = new Date(isoString);
    return date.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch {
    return isoString;
  }
}

export function formatDateOnly(isoString: string): string {
  try {
    const date = new Date(isoString);
    return date.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  } catch {
    return isoString;
  }
}
