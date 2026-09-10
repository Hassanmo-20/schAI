import { apiClient, USE_MOCK_DATA } from './apiClient';
import { AppNotification } from '../types';
import { mapNotification } from './apiMappers';
import { MOCK_NOTIFICATIONS } from '../data/mockData';

/**
 * The authenticated user's own notifications.
 *
 * There is no user id in any of these paths: the API always resolves rows from
 * the bearer token, so the browser cannot ask for — or mark read — somebody
 * else's notifications even by guessing an id.
 */

/** Mock mode keeps its own copy so read state survives within a session. */
let mockRows: AppNotification[] = MOCK_NOTIFICATIONS.map((n) => ({ ...n }));

/**
 * The bell is mounted twice (sidebar for desktop, top bar for mobile) so that
 * exactly one is visible at any width. Both poll the badge, so a short-lived
 * cache collapses those into a single request — and keeps the count from
 * flickering between two components holding slightly different values.
 */
const UNREAD_TTL_MS = 15_000;
let unreadCache: { value: number; at: number } | null = null;

function cacheUnread(value: number): number {
  unreadCache = { value, at: Date.now() };
  return value;
}

export const notificationService = {
  async getNotifications(perPage = 20): Promise<AppNotification[]> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 200));
      const rows = mockRows.map((n) => ({ ...n }));
      cacheUnread(rows.filter((n) => !n.isRead).length);
      return rows;
    }

    const response = await apiClient.get<{ data: any[] }>(`/notifications?per_page=${perPage}`);
    return (response?.data ?? []).map(mapNotification);
  },

  async getUnreadCount(): Promise<number> {
    if (unreadCache && Date.now() - unreadCache.at < UNREAD_TTL_MS) {
      return unreadCache.value;
    }

    if (USE_MOCK_DATA) {
      return cacheUnread(mockRows.filter((n) => !n.isRead).length);
    }

    const response = await apiClient.get<{ data: { unread_count: number } }>(
      '/notifications/unread-count'
    );
    return cacheUnread(Number(response?.data?.unread_count ?? 0));
  },

  /** Returns the remaining unread count so the badge never needs a second call. */
  async markAsRead(id: string): Promise<number> {
    if (USE_MOCK_DATA) {
      mockRows = mockRows.map((n) => (n.id === id ? { ...n, isRead: true } : n));
      return cacheUnread(mockRows.filter((n) => !n.isRead).length);
    }

    const response = await apiClient.post<{ data: { unread_count: number } }>(
      `/notifications/${id}/read`
    );
    return cacheUnread(Number(response?.data?.unread_count ?? 0));
  },

  async markAllAsRead(): Promise<number> {
    if (USE_MOCK_DATA) {
      mockRows = mockRows.map((n) => ({ ...n, isRead: true }));
      return cacheUnread(0);
    }

    const response = await apiClient.post<{ data: { unread_count: number } }>(
      '/notifications/read-all'
    );
    return cacheUnread(Number(response?.data?.unread_count ?? 0));
  },

  /** Drop the cached badge so the next read hits the API (used on logout). */
  resetCache(): void {
    unreadCache = null;
    mockRows = MOCK_NOTIFICATIONS.map((n) => ({ ...n }));
  },
};
