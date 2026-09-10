import { AcademicTask, Attachment, TaskStatisticsData, TaskType, User } from '../types';

/**
 * Translation layer between the Laravel API and the frontend domain model.
 *
 * The API is idiomatic Laravel (snake_case, `data` envelopes, lowercase enum
 * values); the UI is idiomatic TypeScript (camelCase, display-cased types).
 * Keeping every difference here means a backend response change touches this
 * file only — components and pages stay untouched.
 */

/** Laravel wraps single resources in `data`; collections add `meta`. */
export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; per_page: number; total: number; last_page: number };
}

export function unwrap<T>(payload: T | { data: T }): T {
  return payload && typeof payload === 'object' && 'data' in (payload as Record<string, unknown>)
    ? (payload as { data: T }).data
    : (payload as T);
}

interface ApiAttachment {
  id: number | string;
  name: string;
  url: string;
  mime_type?: string;
  file_size?: number;
}

interface ApiTask {
  id: number | string;
  title: string;
  description: string | null;
  type: string;
  deadline: string;
  batch_id: number | null;
  is_active: boolean;
  created_by: number | string;
  created_by_name?: string | null;
  is_completed?: boolean;
  completed_at?: string | null;
  attachments?: ApiAttachment[];
  statistics?: {
    total_students: number;
    completed_students: number;
    remaining_students: number;
    completion_percentage: number;
  };
}

interface ApiUser {
  id: number | string;
  name: string;
  email: string;
  role: string;
  batch_id: number | null;
  batch: string | null;
}

const TASK_TYPES: TaskType[] = ['Assignment', 'Quiz', 'Midterm', 'Exam', 'Project', 'Other'];

/** API sends `assignment`; the UI badges key off `Assignment`. */
export function toDisplayType(apiType: string): TaskType {
  const match = TASK_TYPES.find((t) => t.toLowerCase() === String(apiType).toLowerCase());
  return match ?? 'Other';
}

export function toApiType(type: TaskType): string {
  return type.toLowerCase();
}

function humanFileSize(bytes?: number): string | undefined {
  if (!bytes || bytes <= 0) return undefined;
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function attachmentKind(a: ApiAttachment): Attachment['type'] {
  const mime = a.mime_type ?? '';
  if (mime.startsWith('image/')) return 'image';
  if (mime === 'application/pdf') return 'pdf';
  return /\.(png|jpe?g|gif|webp|svg)$/i.test(a.name)
    ? 'image'
    : /\.pdf$/i.test(a.name)
      ? 'pdf'
      : 'file';
}

export function mapAttachment(a: ApiAttachment): Attachment {
  return {
    id: String(a.id),
    name: a.name,
    url: a.url,
    type: attachmentKind(a),
    size: humanFileSize(a.file_size),
  };
}

export function mapTask(t: ApiTask): AcademicTask {
  return {
    id: String(t.id),
    title: t.title,
    description: t.description ?? '',
    type: toDisplayType(t.type),
    deadline: t.deadline,
    batch: t.batch_id != null ? String(t.batch_id) : '',
    createdBy: String(t.created_by),
    createdByName: t.created_by_name ?? undefined,
    attachments: (t.attachments ?? []).map(mapAttachment),
    isCompleted: Boolean(t.is_completed),
    completedAt: t.completed_at ?? undefined,
    totalStudents: t.statistics?.total_students,
    completedStudents: t.statistics?.completed_students,
  };
}

export function mapUser(u: ApiUser): User {
  return {
    id: String(u.id),
    name: u.name,
    email: u.email,
    role: u.role === 'representative' ? 'representative' : 'student',
    batch: u.batch ?? (u.batch_id != null ? String(u.batch_id) : ''),
  };
}

export function mapStatistics(
  raw: {
    task_id: number | string;
    task_title: string;
    total_students: number;
    completed_students: number;
    remaining_students: number;
    completion_percentage: number;
  }
): TaskStatisticsData {
  return {
    taskId: String(raw.task_id),
    taskTitle: raw.task_title,
    totalStudents: raw.total_students,
    completedStudents: raw.completed_students,
    remainingStudents: raw.remaining_students,
    completionPercentage: raw.completion_percentage,
  };
}

/**
 * Task create/update payload. Sent as multipart because the API accepts
 * attachments on the same endpoints; scalar-only calls work identically.
 */
export function toTaskFormData(task: Partial<AcademicTask>, files: File[] = []): FormData {
  const form = new FormData();
  if (task.title !== undefined) form.append('title', task.title);
  if (task.description !== undefined) form.append('description', task.description);
  if (task.type !== undefined) form.append('type', toApiType(task.type));
  if (task.deadline !== undefined) form.append('deadline', task.deadline);
  files.forEach((file) => form.append('attachments[]', file));
  return form;
}
