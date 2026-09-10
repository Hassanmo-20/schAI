import { useMemo, useState } from 'react';
import { AcademicTask, TaskFilterState } from '../types';
import { calculateUrgency } from '../utils/dateUtils';
import { compareByUrgency } from '../utils/taskHelpers';

const DEFAULT_FILTERS: TaskFilterState = { search: '', status: 'all', type: 'all', sortBy: 'deadline' };

export function useTaskFilters(tasks: AcademicTask[]) {
  const [filters, setFilters] = useState<TaskFilterState>(DEFAULT_FILTERS);

  const filtered = useMemo(() => {
    const q = filters.search.trim().toLowerCase();
    let out = tasks.filter((t) => {
      if (filters.type !== 'all' && t.type !== filters.type) return false;
      if (filters.status === 'completed' && !t.isCompleted) return false;
      if (filters.status === 'pending' && t.isCompleted) return false;
      if (filters.status === 'overdue') {
        if (t.isCompleted) return false;
        if (calculateUrgency(t.deadline, false) !== 'overdue') return false;
      }
      if (q && !`${t.title} ${t.description}`.toLowerCase().includes(q)) return false;
      return true;
    });

    out = [...out].sort((a, b) => {
      switch (filters.sortBy) {
        case 'deadline':
          return new Date(a.deadline).getTime() - new Date(b.deadline).getTime();
        case 'urgency':
          return compareByUrgency(a, b);
        case 'newest':
          return b.id.localeCompare(a.id);
        case 'title':
          return a.title.localeCompare(b.title);
        default:
          return 0;
      }
    });
    return out;
  }, [tasks, filters]);

  return { filters, setFilters, filtered };
}
