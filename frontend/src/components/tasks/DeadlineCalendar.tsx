import React, { useMemo, useState } from 'react';
import { AcademicTask } from '../../types';
import './tasks.css';

interface DeadlineCalendarProps {
  tasks: AcademicTask[];
}

function startOfMonth(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

const DeadlineCalendar: React.FC<DeadlineCalendarProps> = ({ tasks }) => {
  const [cursor, setCursor] = useState(() => startOfMonth(new Date()));

  const cells = useMemo(() => {
    const first = startOfMonth(cursor);
    const lead = first.getDay(); // 0 = Sunday
    const start = new Date(first);
    start.setDate(first.getDate() - lead);
    const out: { date: Date; inMonth: boolean }[] = [];
    for (let i = 0; i < 42; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      out.push({ date: d, inMonth: d.getMonth() === cursor.getMonth() });
    }
    return out;
  }, [cursor]);

  const tasksByDay = useMemo(() => {
    const map = new Map<string, AcademicTask[]>();
    tasks.forEach((t) => {
      const d = new Date(t.deadline);
      const key = `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
      const arr = map.get(key) ?? [];
      arr.push(t);
      map.set(key, arr);
    });
    return map;
  }, [tasks]);

  const title = cursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  const shift = (delta: number) =>
    setCursor((c) => new Date(c.getFullYear(), c.getMonth() + delta, 1));

  return (
    <section className="card calendar" aria-label="Academic deadline calendar">
      <div className="cal-toolbar">
        <div className="btn-group">
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => shift(-1)} aria-label="Previous month">Prev</button>
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => shift(1)} aria-label="Next month">Next</button>
          <button className="btn btn-secondary btn-sm" type="button" onClick={() => setCursor(startOfMonth(new Date()))}>Today</button>
        </div>
        <div className="cal-title">{title}</div>
      </div>
      <div className="cal-grid" role="grid" aria-label={title}>
        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
          <div key={d} className="dow" role="columnheader">{d}</div>
        ))}
        {cells.map(({ date, inMonth }, i) => {
          const key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
          const dayTasks = tasksByDay.get(key) ?? [];
          const isToday = new Date().toDateString() === date.toDateString();
          return (
            <div key={i} className={`day${inMonth ? '' : ' muted'}${isToday ? ' today' : ''}`} role="gridcell" aria-label={date.toDateString()}>
              <span className="num">{date.getDate()}</span>
              {dayTasks.slice(0, 2).map((t) => (
                <span key={t.id} className={`event ev-${t.type.toLowerCase()}`} title={`${t.title} (${t.type})`}>
                  {t.title}
                </span>
              ))}
              {dayTasks.length > 2 && <span className="event-more">+{dayTasks.length - 2} more</span>}
            </div>
          );
        })}
      </div>
    </section>
  );
};

export default DeadlineCalendar;
