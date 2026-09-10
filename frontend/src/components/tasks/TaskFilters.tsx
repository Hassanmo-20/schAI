import React from 'react';
import { TaskFilterState, TaskStatusFilter, TaskType, TaskSortBy } from '../../types';
import { Input, Select } from '../common/FormControls';
import './tasks.css';

const STATUS_OPTIONS: { value: TaskStatusFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'completed', label: 'Completed' },
  { value: 'overdue', label: 'Overdue' },
];

const TYPE_OPTIONS: ('all' | TaskType)[] = ['all', 'Assignment', 'Quiz', 'Midterm', 'Exam', 'Project', 'Other'];

const SORT_OPTIONS: { value: TaskSortBy; label: string }[] = [
  { value: 'deadline', label: 'Deadline (nearest)' },
  { value: 'urgency', label: 'Urgency' },
  { value: 'newest', label: 'Recently added' },
  { value: 'title', label: 'Title A–Z' },
];

interface TaskFiltersProps {
  value: TaskFilterState;
  onChange: (next: TaskFilterState) => void;
  totalCount: number;
  shownCount: number;
}

const TaskFilters: React.FC<TaskFiltersProps> = ({ value, onChange, totalCount, shownCount }) => {
  const set = (patch: Partial<TaskFilterState>) => onChange({ ...value, ...patch });

  return (
    <div className="card filters-card" role="search" aria-label="Filter tasks">
      <div className="filters-row">
        <div className="filters-search">
          <Input
            type="search"
            placeholder="Search tasks…"
            aria-label="Search tasks"
            value={value.search}
            onChange={(e) => set({ search: e.target.value })}
          />
        </div>
        <div className="filters-selects">
          <label className="filter-inline">
            <span>Status</span>
            <Select
              aria-label="Filter by status"
              value={value.status}
              onChange={(e) => set({ status: e.target.value as TaskStatusFilter })}
            >
              {STATUS_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </label>
          <label className="filter-inline">
            <span>Type</span>
            <Select
              aria-label="Filter by type"
              value={value.type}
              onChange={(e) => set({ type: e.target.value as TaskFilterState['type'] })}
            >
              {TYPE_OPTIONS.map((t) => (
                <option key={t} value={t}>{t === 'all' ? 'All types' : t}</option>
              ))}
            </Select>
          </label>
          <label className="filter-inline">
            <span>Sort</span>
            <Select
              aria-label="Sort tasks"
              value={value.sortBy}
              onChange={(e) => set({ sortBy: e.target.value as TaskSortBy })}
            >
              {SORT_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </label>
        </div>
      </div>
      <p className="filters-count" aria-live="polite">
        Showing {shownCount} of {totalCount} tasks
      </p>
    </div>
  );
};

export default TaskFilters;
