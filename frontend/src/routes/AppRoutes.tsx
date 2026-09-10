import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { ProtectedRoute, RoleProtectedRoute } from './ProtectedRoute';
import AppLayout from '../components/layout/AppLayout';
import LoginPage from '../pages/auth/LoginPage';
import RegisterPage from '../pages/auth/RegisterPage';
import DashboardPage from '../pages/student/DashboardPage';
import TasksPage from '../pages/student/TasksPage';
import TaskDetailsPage from '../pages/student/TaskDetailsPage';
import ProfilePage from '../pages/student/ProfilePage';
import SettingsPage from '../pages/student/SettingsPage';
import RepDashboardPage from '../pages/representative/RepDashboardPage';
import RepTasksPage from '../pages/representative/RepTasksPage';
import TaskCreatePage from '../pages/representative/TaskCreatePage';
import TaskEditPage from '../pages/representative/TaskEditPage';
import TaskStatisticsPage from '../pages/representative/TaskStatisticsPage';
import NotFoundPage from '../pages/NotFoundPage';
import AccessDeniedPage from '../pages/AccessDeniedPage';

const AppRoutes: React.FC = () => {
  return (
    <Routes>
      {/* Public */}
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/access-denied" element={<AccessDeniedPage />} />

      {/* Student (any authenticated user) */}
      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/tasks" element={<TasksPage />} />
        <Route path="/tasks/:id" element={<TaskDetailsPage />} />
        <Route path="/profile" element={<ProfilePage />} />
        <Route path="/settings" element={<SettingsPage />} />

        {/* Representative-only (nested under same layout) */}
        <Route
          path="/representative"
          element={
            <RoleProtectedRoute allowedRoles={['representative']}>
              <RepDashboardPage />
            </RoleProtectedRoute>
          }
        />
        <Route
          path="/representative/tasks"
          element={
            <RoleProtectedRoute allowedRoles={['representative']}>
              <RepTasksPage />
            </RoleProtectedRoute>
          }
        />
        <Route
          path="/representative/tasks/create"
          element={
            <RoleProtectedRoute allowedRoles={['representative']}>
              <TaskCreatePage />
            </RoleProtectedRoute>
          }
        />
        <Route
          path="/representative/tasks/:id/edit"
          element={
            <RoleProtectedRoute allowedRoles={['representative']}>
              <TaskEditPage />
            </RoleProtectedRoute>
          }
        />
        <Route
          path="/representative/tasks/:id/statistics"
          element={
            <RoleProtectedRoute allowedRoles={['representative']}>
              <TaskStatisticsPage />
            </RoleProtectedRoute>
          }
        />
      </Route>

      {/* Defaults */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
};

export default AppRoutes;
