import React, { useEffect } from 'react';
import { Button } from './Button';
import './common.css';

export const LoadingState: React.FC<{ message?: string }> = ({ message = 'Loading…' }) => (
  <div className="state-wrap" role="status" aria-label={message}>
    <div className="spinner" aria-hidden="true" />
    <p className="state-text">{message}</p>
  </div>
);

export const SkeletonCards: React.FC<{ count?: number }> = ({ count = 3 }) => (
  <div className="skeleton-grid" aria-hidden="true">
    {Array.from({ length: count }).map((_, i) => (
      <div key={i} className="skeleton-card">
        <div className="skeleton-line skeleton-title" />
        <div className="skeleton-line" />
        <div className="skeleton-line skeleton-short" />
      </div>
    ))}
  </div>
);

export const EmptyState: React.FC<{ title: string; message?: string; action?: React.ReactNode }> = ({
  title,
  message,
  action,
}) => (
  <div className="state-wrap state-empty">
    <div className="empty-icon" aria-hidden="true">📚</div>
    <h3 className="state-title">{title}</h3>
    {message && <p className="state-text">{message}</p>}
    {action}
  </div>
);

export const ErrorState: React.FC<{ message: string; onRetry?: () => void }> = ({ message, onRetry }) => (
  <div className="state-wrap state-error" role="alert">
    <div className="empty-icon" aria-hidden="true">⚠</div>
    <h3 className="state-title">Something went wrong</h3>
    <p className="state-text">{message}</p>
    {onRetry && (
      <Button variant="secondary" size="sm" onClick={onRetry}>
        Try again
      </Button>
    )}
  </div>
);

export const Modal: React.FC<{
  title: string;
  onClose: () => void;
  children: React.ReactNode;
}> = ({ title, onClose, children }) => {
  const dialogRef = React.useRef<HTMLDivElement>(null);
  const previouslyFocused = React.useRef<HTMLElement | null>(null);

  useEffect(() => {
    previouslyFocused.current = document.activeElement as HTMLElement | null;

    const focusables = () =>
      Array.from(
        dialogRef.current?.querySelectorAll<HTMLElement>(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        ) ?? []
      ).filter((el) => !el.hasAttribute('disabled'));

    focusables()[0]?.focus();

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
        return;
      }
      // Trap Tab inside the dialog so keyboard users cannot land on the
      // inert page behind the overlay.
      if (e.key === 'Tab') {
        const items = focusables();
        if (items.length === 0) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };

    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      previouslyFocused.current?.focus();
    };
  }, [onClose]);

  return (
    <div className="modal-overlay" onClick={onClose} role="presentation">
      <div
        ref={dialogRef}
        className="modal"
        role="dialog"
        aria-modal="true"
        aria-label={title}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          <h2 className="modal-title">{title}</h2>
          <button className="modal-close" onClick={onClose} aria-label="Close dialog" type="button">
            ×
          </button>
        </div>
        <div className="modal-body">{children}</div>
      </div>
    </div>
  );
};

export const ConfirmDialog: React.FC<{
  title: string;
  message: string;
  confirmLabel?: string;
  onConfirm: () => void;
  onCancel: () => void;
  danger?: boolean;
}> = ({ title, message, confirmLabel = 'Confirm', onConfirm, onCancel, danger = true }) => (
  <Modal title={title} onClose={onCancel}>
    <p className="confirm-message">{message}</p>
    <div className="confirm-actions">
      <Button variant="secondary" onClick={onCancel}>
        Cancel
      </Button>
      <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm}>
        {confirmLabel}
      </Button>
    </div>
  </Modal>
);
