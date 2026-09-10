export type UserRole = 'student' | 'representative';

/**
 * A group is the (batch year, department) PAIR — "2027 CCE" and "2027 CSE"
 * are different groups. The valid values live in the backend enums and reach
 * the UI through `GET /registration-options`, so they are typed as strings
 * here rather than hardcoded: adding a batch year is a backend-only change.
 */
export interface Batch {
  id: string;
  /** Display label for the pair, e.g. "2027 CCE". */
  name: string;
  batchYear?: string;
  department?: string;
}

/** One selectable value plus the text to show for it. */
export interface Choice {
  value: string;
  label: string;
}

/** The choices the registration form must offer, served by the backend. */
export interface RegistrationOptions {
  batchYears: Choice[];
  departments: Choice[];
  roles: Choice[];
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  /** Ready-made group label, e.g. "2027 CCE". */
  batch: string;
  batchYear?: string;
  department?: string;
  avatarUrl?: string;
}

export type NotificationType =
  | 'task_published'
  | 'task_updated'
  | 'deadline_approaching'
  | 'notification';

export interface AppNotification {
  id: string;
  type: NotificationType;
  title: string;
  message: string;
  taskId?: string;
  taskTitle?: string;
  deadline?: string;
  isRead: boolean;
  readAt?: string;
  createdAt?: string;
}

export type TaskType = 'Assignment' | 'Quiz' | 'Midterm' | 'Exam' | 'Project' | 'Other';

export type UrgencyLevel = 'overdue' | 'very_urgent' | 'high' | 'medium' | 'normal';

export interface Attachment {
  id: string;
  name: string;
  url: string;
  type: 'image' | 'pdf' | 'file';
  size?: string;
}

export interface AcademicTask {
  id: string;
  title: string;
  description: string;
  type: TaskType;
  deadline: string; // ISO 8601 string
  batch: string;
  createdBy: string;
  createdByName?: string;
  attachments: Attachment[];
  isCompleted?: boolean;
  completedAt?: string;
  totalStudents?: number;
  completedStudents?: number;
}

export interface TaskStatisticsData {
  taskId: string;
  taskTitle: string;
  totalStudents: number;
  completedStudents: number;
  remainingStudents: number;
  completionPercentage: number;
  submissionsByDate?: { date: string; count: number }[];
}

export interface AuthResponse {
  user: User;
  token: string;
}

export type TaskStatusFilter = 'all' | 'pending' | 'completed' | 'overdue';
export type TaskTypeFilter = 'all' | TaskType;
export type TaskSortBy = 'deadline' | 'urgency' | 'newest' | 'title';

export interface TaskFilterState {
  search: string;
  status: TaskStatusFilter;
  type: TaskTypeFilter;
  sortBy: TaskSortBy;
}
