import { api, WEB_BASE_URL } from '@/lib/api/alsorayiaClient';
import type { InvitationResponse } from '@/lib/api/invitations';

export type InboxInvitation = {
  id: number;
  response: InvitationResponse | null;
};

export type InboxNotification = {
  id: number | string;
  title: string;
  body: string;
  media_type: string | null;
  media_url: string | null;
  created_at: string;
  time_ago: string;
  read: boolean;
  link_url?: string | null;
  action_url?: string | null;
  url?: string | null;
  data?: Record<string, unknown> | null;
  invitation?: InboxInvitation | null;
};

/**
 * دالة لتطبيع وتصحيح روابط الوسائط والتأكد من أنها رابط كامل صالح للاستخدام
 */
export function normalizeNotificationMediaUrl(rawUrl: string | null | undefined): string | null {
  if (!rawUrl || typeof rawUrl !== 'string') return null;
  const trimmed = rawUrl.trim();
  if (!trimmed) return null;
  if (/^https?:\/\//i.test(trimmed)) return trimmed;
  const base = WEB_BASE_URL.replace(/\/$/, '');
  const path = trimmed.replace(/^\//, '');
  return `${base}/${path}`;
}

/**
 * استخراج نوع الوسائط بشكل دقيق
 */
export function inferNotificationMediaType(
  mediaType: string | null | undefined,
  mediaUrl: string | null | undefined
): 'image' | 'video' | 'audio' | 'none' {
  const typeStr = (mediaType || '').toLowerCase();
  const urlStr = (mediaUrl || '').toLowerCase();

  if (typeStr.includes('image') || typeStr.includes('photo') || typeStr.includes('pic') || /\.(jpeg|jpg|png|webp|gif|svg)(\?.*)?$/i.test(urlStr)) {
    return 'image';
  }
  if (typeStr.includes('video') || typeStr.includes('youtube') || typeStr.includes('youtu.be') || /\.(mp4|mov|m4v|webm)(\?.*)?$/i.test(urlStr) || urlStr.includes('youtube.com') || urlStr.includes('youtu.be')) {
    return 'video';
  }
  if (typeStr.includes('audio') || typeStr.includes('voice') || typeStr.includes('sound') || /\.(mp3|wav|m4a|aac|ogg)(\?.*)?$/i.test(urlStr)) {
    return 'audio';
  }
  if (mediaUrl) {
    // الافتراضي في حال توفر الرابط بدون نوع صريح
    return 'image';
  }
  return 'none';
}

/**
 * القائمة في `data.data` (باجينيشن Laravel)، وليست `data` كمصفوفة.
 * العميل يعيد جسم JSON مباشرة بعد الـ interceptor.
 */
export function extractNotificationItems(body: unknown): InboxNotification[] {
  if (!body || typeof body !== 'object') return [];
  const data = (body as { data?: unknown }).data;
  if (Array.isArray(data)) return data as InboxNotification[];
  if (data && typeof data === 'object') {
    const inner = (data as { data?: unknown }).data;
    if (Array.isArray(inner)) return inner as InboxNotification[];
  }
  return [];
}

export async function fetchNotifications(notificationId?: string): Promise<InboxNotification[]> {
  const raw = (await api.get('/notifications', {
    skipGlobalErrorHandler: true,
    params: notificationId ? { notification_id: notificationId } : undefined,
  })) as unknown;
  return extractNotificationItems(raw);
}

export async function fetchUnreadCount(): Promise<number | null> {
  const raw = (await api.get('/notifications/unread-count', {
    skipGlobalErrorHandler: true,
  })) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  const data = (raw as { data?: { unread_count?: unknown } }).data;
  const count = data?.unread_count;
  return typeof count === 'number' ? count : null;
}

export async function markNotificationRead(id: string | number): Promise<boolean> {
  const raw = (await api.post(`/notifications/${id}/read`, {}, {
    skipGlobalErrorHandler: true,
  })) as unknown;
  if (!raw || typeof raw !== 'object') return false;
  return (raw as { success?: boolean }).success === true;
}

export async function markAllNotificationsRead(): Promise<boolean> {
  const raw = (await api.post('/notifications/read-all', {}, {
    skipGlobalErrorHandler: true,
  })) as unknown;
  if (!raw || typeof raw !== 'object') return false;
  return (raw as { success?: boolean }).success === true;
}

export async function deleteNotification(id: string | number): Promise<boolean> {
  try {
    const raw = (await api.delete(`/notifications/${id}`, {
      skipGlobalErrorHandler: true,
    })) as unknown;
    if (!raw || typeof raw !== 'object') return false;
    return (raw as { success?: boolean }).success === true;
  } catch {
    return false;
  }
}

export async function deleteMultipleNotifications(ids: (string | number)[]): Promise<boolean> {
  try {
    const raw = (await api.post('/notifications/delete-multiple', { ids }, {
      skipGlobalErrorHandler: true,
    })) as unknown;
    if (!raw || typeof raw !== 'object') return false;
    return (raw as { success?: boolean }).success === true;
  } catch {
    return false;
  }
}

export async function clearAllNotifications(): Promise<boolean> {
  try {
    const raw = (await api.post('/notifications/clear-all', {}, {
      skipGlobalErrorHandler: true,
    })) as unknown;
    if (!raw || typeof raw !== 'object') return false;
    return (raw as { success?: boolean }).success === true;
  } catch {
    return false;
  }
}
