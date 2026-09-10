import { apiClient, USE_MOCK_DATA } from './apiClient';
import { AcademicTask } from '../types';
import { INITIAL_MOCK_TASKS } from '../data/mockData';
import { mapTask, Paginated, toTaskFormData, unwrap } from './apiMappers';

const TASKS_STORAGE_KEY = 'schai_academic_tasks';

function getStoredTasks(): AcademicTask[] {
  const raw = localStorage.getItem(TASKS_STORAGE_KEY);
  if (!raw) {
    localStorage.setItem(TASKS_STORAGE_KEY, JSON.stringify(INITIAL_MOCK_TASKS));
    return INITIAL_MOCK_TASKS;
  }
  try {
    return JSON.parse(raw);
  } catch {
    return INITIAL_MOCK_TASKS;
  }
}

function saveStoredTasks(tasks: AcademicTask[]): void {
  localStorage.setItem(TASKS_STORAGE_KEY, JSON.stringify(tasks));
}

export const taskService = {
  /** Fetches the first page at the API maximum; pagination metadata is ignored
   *  because the UI filters/sorts client-side over the batch's task set. */
  async getTasks(): Promise<AcademicTask[]> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 200));
      return getStoredTasks();
    }
    const response = await apiClient.get<Paginated<any>>('/tasks?per_page=100');
    return (response?.data ?? []).map(mapTask);
  },

  async getTaskById(id: string): Promise<AcademicTask> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 150));
      const tasks = getStoredTasks();
      const task = tasks.find((t) => t.id === id);
      if (!task) {
        throw new Error('Task not found');
      }
      return task;
    }
    const response = await apiClient.get<any>(`/tasks/${id}`);
    return mapTask(unwrap(response));
  },

  async createTask(taskData: Omit<AcademicTask, 'id'>, files: File[] = []): Promise<AcademicTask> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 300));
      const tasks = getStoredTasks();
      const newTask: AcademicTask = {
        ...taskData,
        id: `task_${Date.now()}`,
        totalStudents: taskData.totalStudents ?? 50,
        completedStudents: taskData.completedStudents ?? 0,
        attachments: taskData.attachments || [],
      };
      const updated = [newTask, ...tasks];
      saveStoredTasks(updated);
      return newTask;
    }
    const created = await apiClient.post<any>('/tasks', toTaskFormData(taskData, files));
    return mapTask(unwrap(created));
  },

  async updateTask(id: string, updates: Partial<AcademicTask>): Promise<AcademicTask> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 250));
      const tasks = getStoredTasks();
      const index = tasks.findIndex((t) => t.id === id);
      if (index === -1) {
        throw new Error('Task not found');
      }
      const updatedTask = { ...tasks[index], ...updates };
      tasks[index] = updatedTask;
      saveStoredTasks(tasks);
      return updatedTask;
    }
    // PUT with multipart is unreliable across servers; scalars go as JSON and
    // any new files use the dedicated multipart POST override below.
    const payload: Record<string, unknown> = {};
    if (updates.title !== undefined) payload.title = updates.title;
    if (updates.description !== undefined) payload.description = updates.description;
    if (updates.type !== undefined) payload.type = updates.type.toLowerCase();
    if (updates.deadline !== undefined) payload.deadline = updates.deadline;

    const updated = await apiClient.put<any>(`/tasks/${id}`, payload);
    return mapTask(unwrap(updated));
  },

  /** Appends files to an existing task (Laravel method-spoofed multipart PUT). */
  async uploadAttachments(id: string, files: File[]): Promise<AcademicTask> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 200));
      const tasks = getStoredTasks();
      const index = tasks.findIndex((t) => t.id === id);
      if (index === -1) throw new Error('Task not found');
      tasks[index] = {
        ...tasks[index],
        attachments: [
          ...tasks[index].attachments,
          ...files.map((file, i) => ({
            id: `att_${Date.now()}_${i}`,
            name: file.name,
            url: URL.createObjectURL(file),
            type: (file.type.startsWith('image/')
              ? 'image'
              : file.type === 'application/pdf'
                ? 'pdf'
                : 'file') as 'image' | 'pdf' | 'file',
            size: `${Math.max(1, Math.round(file.size / 1024))} KB`,
          })),
        ],
      };
      saveStoredTasks(tasks);
      return tasks[index];
    }

    const form = new FormData();
    form.append('_method', 'PUT');
    files.forEach((file) => form.append('attachments[]', file));
    const updated = await apiClient.post<any>(`/tasks/${id}`, form);
    return mapTask(unwrap(updated));
  },

  async deleteAttachment(taskId: string, attachmentId: string): Promise<void> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 150));
      const tasks = getStoredTasks();
      const index = tasks.findIndex((t) => t.id === taskId);
      if (index === -1) return;
      tasks[index] = {
        ...tasks[index],
        attachments: tasks[index].attachments.filter((a) => a.id !== attachmentId),
      };
      saveStoredTasks(tasks);
      return;
    }
    await apiClient.delete(`/tasks/${taskId}/attachments/${attachmentId}`);
  },

  async deleteTask(id: string): Promise<void> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 200));
      const tasks = getStoredTasks().filter((t) => t.id !== id);
      saveStoredTasks(tasks);
      return;
    }
    await apiClient.delete(`/tasks/${id}`);
  },

  async toggleComplete(id: string): Promise<AcademicTask> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 150));
      const tasks = getStoredTasks();
      const index = tasks.findIndex((t) => t.id === id);
      if (index === -1) {
        throw new Error('Task not found');
      }
      const current = tasks[index];
      const newStatus = !current.isCompleted;
      const studentDelta = newStatus ? 1 : -1;
      const currentCompleted = current.completedStudents || 0;
      const newCompleted = Math.max(0, Math.min(current.totalStudents || 50, currentCompleted + studentDelta));

      const updatedTask: AcademicTask = {
        ...current,
        isCompleted: newStatus,
        completedAt: newStatus ? new Date().toISOString() : undefined,
        completedStudents: newCompleted,
      };

      tasks[index] = updatedTask;
      saveStoredTasks(tasks);
      return updatedTask;
    }
    // The API models completion as create/delete, not a single toggle:
    // POST completes, DELETE uncompletes. Always POSTing would return 409.
    const current = await this.getTaskById(id);
    if (current.isCompleted) {
      await apiClient.delete(`/tasks/${id}/complete`);
    } else {
      await apiClient.post(`/tasks/${id}/complete`);
    }
    return this.getTaskById(id);
  },

  resetToInitialMock(): void {
    localStorage.setItem(TASKS_STORAGE_KEY, JSON.stringify(INITIAL_MOCK_TASKS));
  }
};
