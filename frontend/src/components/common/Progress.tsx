import React from 'react';
import { safePercentage } from '../../services/statisticsService';
import './common.css';

export const ProgressBar: React.FC<{
  completed: number;
  total: number;
  label?: string;
}> = ({ completed, total, label }) => {
  const pct = safePercentage(completed, total);
  return (
    <div className="progress-linear" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100} aria-label={label ?? 'Progress'}>
      <div className="progress-track">
        <div className="progress-fill" style={{ width: `${pct}%` }} />
      </div>
      <span className="progress-pct">{pct}%</span>
    </div>
  );
};

export const ProgressRing: React.FC<{
  completed: number;
  total: number;
  size?: number;
}> = ({ completed, total, size = 140 }) => {
  const pct = safePercentage(completed, total);
  const r = 56;
  const circumference = 2 * Math.PI * r;
  const filled = (pct / 100) * circumference;
  return (
    <div className="donut-wrap" style={{ width: size, height: size }}>
      <svg className="donut" width={size} height={size} viewBox="0 0 140 140" role="img" aria-label={`${pct}% completed`}>
        <circle cx="70" cy="70" r={r} fill="none" stroke="#d5dbe0" strokeWidth="20" />
        <circle
          cx="70" cy="70" r={r} fill="none" stroke="#27323f" strokeWidth="20"
          strokeDasharray={`${filled} ${circumference}`}
          strokeLinecap="butt"
        />
      </svg>
      <div className="donut-label">
        <span className="donut-value">{pct}%</span>
        <span className="donut-caption">COMPLETED</span>
      </div>
    </div>
  );
};

export const StatCard: React.FC<{ label: string; value: string | number; sub?: string }> = ({
  label,
  value,
  sub,
}) => (
  <div className="card stat-card">
    <span className="stat-label">{label}</span>
    <span className="stat-value">{value}</span>
    {sub && <span className="stat-sub">{sub}</span>}
  </div>
);
