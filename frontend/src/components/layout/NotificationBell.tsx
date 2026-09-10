import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { notificationService } from '../../services/notificationService';
import { AppNotification } from '../../types';

/** How often the badge re-checks the server while the app is open. */
const POLL_INTERVAL_MS = 60_000;

const TYPE_ICON: Record<string, string> = {
  task_published: '✚',
  task_updated: '✎',
  deadline_approaching: '⏰',
  notification: '•',
};

function relativeTime(iso?: string): string {
  if (!iso) return '';
  const diffMs = Date.now() - new Date(iso).getTime();
  const minutes = Math.round(diffMs / 60_000);
  if (minutes < 1) return 'just now';
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  return `${Math.round(hours / 24)}d ago`;
}

/**
 * Bell + unread badge + dropdown list.
 *
 * Every call goes to an endpoint that resolves rows from the bearer token, so
 * this component never needs — and never sends — a user id.
 */
const NotificationBell: React.FC = () => {
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<AppNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const wrapRef = useRef<HTMLDivElement>(null);

  const refreshCount = useCallback(async () => {
    try {
      setUnread(await notificationService.getUnreadCount());
    } catch {
      // A failed badge poll is not worth surfacing — the next tick retries.
    }
  }, []);

  useEffect(() => {
    void refreshCount();
    const id = window.setInterval(refreshCount, POLL_INTERVAL_MS);
    return () => window.clearInterval(id);
  }, [refreshCount]);

  // Close on outside click and on Escape, so the dropdown never traps focus.
  useEffect(() => {
    if (!open) return;

    const onPointerDown = (e: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
    };
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };

    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [open]);

  const toggle = async () => {
    const next = !open;
    setOpen(next);
    if (!next) return;

    setLoading(true);
    setError(null);
    try {
      const rows = await notificationService.getNotifications();
      setItems(rows);
      setUnread(rows.filter((n) => !n.isRead).length);
    } catch {
      setError('Could not load notifications.');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenItem = async (item: AppNotification) => {
    if (!item.isRead) {
      setItems((prev) => prev.map((n) => (n.id === item.id ? { ...n, isRead: true } : n)));
      try {
        setUnread(await notificationService.markAsRead(item.id));
      } catch {
        void refreshCount();
      }
    }

    if (item.taskId) {
      setOpen(false);
      navigate(`/tasks/${item.taskId}`);
    }
  };

  const handleMarkAll = async () => {
    setItems((prev) => prev.map((n) => ({ ...n, isRead: true })));
    try {
      setUnread(await notificationService.markAllAsRead());
    } catch {
      void refreshCount();
    }
  };

  const badge = unread > 9 ? '9+' : String(unread);

  return (
    <div className="notif" ref={wrapRef}>
      <button
        type="button"
        className="notif-trigger"
        onClick={toggle}
        aria-expanded={open}
        aria-haspopup="true"
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'}
      >
        <span aria-hidden="true">🔔</span>
        {unread > 0 && <span className="notif-badge" aria-hidden="true">{badge}</span>}
      </button>

      {open && (
        <div className="notif-panel" role="dialog" aria-label="Notifications">
          <div className="notif-panel-head">
            <span>Notifications</span>
            {unread > 0 && (
              <button type="button" className="notif-mark-all" onClick={handleMarkAll}>
                Mark all read
              </button>
            )}
          </div>

          <div className="notif-list">
            {loading && <p className="notif-empty">Loading…</p>}
            {!loading && error && <p className="notif-empty notif-error" role="alert">{error}</p>}
            {!loading && !error && items.length === 0 && (
              <p className="notif-empty">You&apos;re all caught up.</p>
            )}
            {!loading &&
              !error &&
              items.map((item) => (
                <button
                  type="button"
                  key={item.id}
                  className={`notif-item${item.isRead ? '' : ' unread'}`}
                  onClick={() => handleOpenItem(item)}
                >
                  <span className="notif-icon" aria-hidden="true">
                    {TYPE_ICON[item.type] ?? TYPE_ICON.notification}
                  </span>
                  <span className="notif-body">
                    <span className="notif-title">{item.title}</span>
                    <span className="notif-message">{item.message}</span>
                    <span className="notif-time">{relativeTime(item.createdAt)}</span>
                  </span>
                </button>
              ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default NotificationBell;
