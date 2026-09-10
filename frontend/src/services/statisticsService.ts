import { AcademicTask, TaskStatisticsData } from '../types';
import { calculateUrgency } from '../utils/dateUtils';
import { taskService } from './taskService';
import { apiClient, USE_MOCK_DATA } from './apiClient';
import { mapStatistics } from './apiMappers';

export interface BatchOverviewStats {
  totalTasks: number;
  activeTasks: number;
  completedTasks: number;
  overdueTasks: number;
  averageCompletionRate: number;
}

export function safePercentage(completed: number, total: number): number {
  if (!total || total <= 0) return 0;
  const pct = Math.round((completed / total) * 100);
  return Math.max(0, Math.min(100, pct));
}

export const statisticsService = {
  async getTaskStatistics(taskId: string): Promise<TaskStatisticsData> {
    // Real deployments read authoritative counts from the API; mock mode
    // derives them from the locally stored task.
    if (!USE_MOCK_DATA) {
      const raw = await apiClient.get<any>(`/tasks/${taskId}/statistics`);
      return mapStatistics(raw);
    }

    const task = await taskService.getTaskById(taskId);
    const total = task.totalStudents ?? 50;
    const completed = task.completedStudents ?? 0;
    const remaining = Math.max(0, total - completed);
    const percentage = safePercentage(completed, total);

    return {
      taskId: task.id,
      taskTitle: task.title,
      totalStudents: total,
      completedStudents: completed,
      remainingStudents: remaining,
      completionPercentage: percentage,
      // No submissions-by-day breakdown: the API does not expose one, and
      // inventing a distribution would present fabricated data as real.
    };
  },

  async getBatchOverview(tasks?: AcademicTask[]): Promise<BatchOverviewStats> {
    const allTasks = tasks || (await taskService.getTasks());
    const totalTasks = allTasks.length;

    let activeCount = 0;
    let overdueCount = 0;
    let totalCompletedRates = 0;

    allTasks.forEach((t) => {
      const urgency = calculateUrgency(t.deadline, t.isCompleted);
      if (urgency === 'overdue') {
        overdueCount++;
      }
      if (!t.isCompleted) {
        activeCount++;
      }
      const total = t.totalStudents ?? 50;
      const completed = t.completedStudents ?? 0;
      totalCompletedRates += safePercentage(completed, total);
    });

    const averageRate = totalTasks > 0 ? Math.round(totalCompletedRates / totalTasks) : 0;

    return {
      totalTasks,
      activeTasks: activeCount,
      completedTasks: totalTasks - activeCount,
      overdueTasks: overdueCount,
      averageCompletionRate: averageRate,
    };
  }
};
