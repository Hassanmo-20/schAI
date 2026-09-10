import React, { createContext, useContext, useEffect, useState } from 'react';
import { AcademicTask } from '../types';
import { taskService } from '../services/taskService';
import { useAuth } from './AuthContext';

interface TaskContextType {
  tasks: AcademicTask[];
  isLoading: boolean;
  error: string | null;
  fetchTasks: () => Promise<void>;
  createTask: (taskData: Omit<AcademicTask, 'id'>, files?: File[]) => Promise<AcademicTask>;
  updateTask: (id: string, updates: Partial<AcademicTask>) => Promise<AcademicTask>;
  deleteTask: (id: string) => Promise<void>;
  toggleComplete: (id: string) => Promise<void>;
  getTaskById: (id: string) => AcademicTask | undefined;
  resetTasks: () => void;
}

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated } = useAuth();
  const [tasks, setTasks] = useState<AcademicTask[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const fetchTasks = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await taskService.getTasks();
      setTasks(data);
    } catch (err: any) {
      setError(err.message || 'Failed to load academic tasks');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    // Only fetch once authenticated: firing on mount hit /api/tasks from the
    // login screen, producing a guaranteed 401 and clearing auth state.
    if (!isAuthenticated) {
      setTasks([]);
      setError(null);
      setIsLoading(false);
      return;
    }
    fetchTasks();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAuthenticated]);

  const createTask = async (taskData: Omit<AcademicTask, 'id'>, files: File[] = []) => {
    const created = await taskService.createTask(taskData, files);
    setTasks((prev) => [created, ...prev]);
    return created;
  };

  const updateTask = async (id: string, updates: Partial<AcademicTask>) => {
    const updated = await taskService.updateTask(id, updates);
    setTasks((prev) => prev.map((t) => (t.id === id ? updated : t)));
    return updated;
  };

  const deleteTask = async (id: string) => {
    await taskService.deleteTask(id);
    setTasks((prev) => prev.filter((t) => t.id !== id));
  };

  const toggleComplete = async (id: string) => {
    // Optimistic UI update
    setTasks((prev) =>
      prev.map((t) => {
        if (t.id === id) {
          const nextState = !t.isCompleted;
          const delta = nextState ? 1 : -1;
          const completed = Math.max(0, (t.completedStudents || 0) + delta);
          return {
            ...t,
            isCompleted: nextState,
            completedStudents: completed,
            completedAt: nextState ? new Date().toISOString() : undefined,
          };
        }
        return t;
      })
    );

    try {
      const updated = await taskService.toggleComplete(id);
      setTasks((prev) => prev.map((t) => (t.id === id ? updated : t)));
    } catch (err) {
      // Revert if failed
      fetchTasks();
      throw err;
    }
  };

  const getTaskById = (id: string): AcademicTask | undefined => {
    return tasks.find((t) => t.id === id);
  };

  const resetTasks = () => {
    taskService.resetToInitialMock();
    fetchTasks();
  };

  return (
    <TaskContext.Provider
      value={{
        tasks,
        isLoading,
        error,
        fetchTasks,
        createTask,
        updateTask,
        deleteTask,
        toggleComplete,
        getTaskById,
        resetTasks,
      }}
    >
      {children}
    </TaskContext.Provider>
  );
};

export const useTasks = (): TaskContextType => {
  const context = useContext(TaskContext);
  if (!context) {
    throw new Error('useTasks must be used within a TaskProvider');
  }
  return context;
};
