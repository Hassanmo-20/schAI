import React from 'react';
import { TaskStatisticsData } from '../../types';
import { safePercentage } from '../../services/statisticsService';
import { ProgressBar } from '../common/Progress';
import '../tasks/tasks.css';

const TaskStatistics: React.FC<{ stats: TaskStatisticsData }> = ({ stats }) => {
  const pct = safePercentage(stats.completedStudents, stats.totalStudents);
  return (
    <div className="card stats-card" aria-label={`Completion statistics for ${stats.taskTitle}`}>
      <div className="stats-top">
        <div>
          <span className="section-title">Completion</span>
          <p className="stats-big">
            {stats.completedStudents} / {stats.totalStudents}
            <span className="stats-muted"> completed</span>
          </p>
        </div>
        <div className="stats-ring" role="img" aria-label={`${pct}% completed`}>
          <svg width="76" height="76" viewBox="0 0 76 76">
            <circle cx="38" cy="38" r="30" fill="none" stroke="#e8edf1" strokeWidth="10" />
            <circle
              cx="38" cy="38" r="30" fill="none" stroke="#2c8ba0" strokeWidth="10"
              strokeDasharray={`${(pct / 100) * 2 * Math.PI * 30} ${2 * Math.PI * 30}`}
              transform="rotate(-90 38 38)"
              strokeLinecap="round"
            />
            <text x="38" y="43" textAnchor="middle" fontSize="15" fontWeight="700" fill="#1f2a35">
              {pct}%
            </text>
          </svg>
        </div>
      </div>
      <ProgressBar completed={stats.completedStudents} total={stats.totalStudents} label="Completion rate" />
      <dl className="stats-list">
        <div><dt>Total students</dt><dd>{stats.totalStudents}</dd></div>
        <div><dt>Completed</dt><dd>{stats.completedStudents}</dd></div>
        <div><dt>Remaining</dt><dd>{stats.remainingStudents}</dd></div>
      </dl>
    </div>
  );
};

export default TaskStatistics;
