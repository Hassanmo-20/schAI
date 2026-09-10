export type UserRole = 'student' | 'representative';

export interface Batch {
  id: string;
  name: string;
  department?: string;
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  batch: string;
  avatarUrl?: string;
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
