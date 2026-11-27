import { type IconSymbolName } from '@/components/ui/icon-symbol';
import type { InboxInvitation } from '@/lib/api/notifications';
import {
  extractChatId,
  extractGroupId,
  normalizeNotificationRoute,
} from '@/lib/utils/notificationRoutes';

export interface NotificationDetailItem {
  id: string;
  type: 'event' | 'participant' | 'system' | 'reminder' | 'update';
  title: string;
  body: string;
  time: string;
  timeAgo: string;
  read: boolean;
  meta?: string;
  mediaType: 'image' | 'video' | 'audio' | 'none';
  mediaUrl?: string | null;
  linkUrl?: string | null;
  actionUrl?: string | null;
  data?: Record<string, unknown> | null;
  invitation?: InboxInvitation | null;
}

export interface ResolvedNotificationAction {
  label: string;
  icon: IconSymbolName;
  route?: string;
  url?: string;
  isExternal?: boolean;
}

/**
 * استخراج الروابط المضمنة داخل نص الإشعار
 */
export function extractUrls(text: string): string[] {
  if (!text) return [];
  const urlRegex = /(https?:\/\/[^\s]+)/gi;
  const matches = text.match(urlRegex);
  return matches ? Array.from(new Set(matches)) : [];
}

/**
 * تحديد التسمية والأيقونة المناسبة حسب الوجهة المستخرجة
 */
function resolveIconForRoute(route: string): { label: string; icon: IconSymbolName } {
  const lower = route.toLowerCase();

  if (lower.includes('walking-chat')) {
    return { label: 'فتح دردشة التحدي 💬', icon: 'bubble.left.and.bubble.right.fill' };
  }
  if (lower.includes('quiz-chat')) {
    return { label: 'فتح نقاش السؤال 💬', icon: 'bubble.left.and.bubble.right.fill' };
  }
  if (lower.includes('walking')) {
    return { label: 'الانتقال لتحدي المشي 🏃', icon: 'figure.walk' };
  }
  if (lower.includes('profile')) {
    return { label: 'الانتقال للملف الشخصي 👤', icon: 'person.crop.circle.fill' };
  }
  if (lower.includes('article')) {
    return { label: 'الانتقال لقراءة المقال 📖', icon: 'doc.text.fill' };
  }
  if (lower.includes('gallery')) {
    return { label: 'الانتقال إلى المعرض 🖼️', icon: 'photo.stack.fill' };
  }
  if (lower.includes('quran')) {
    return { label: 'الانتقال لمسابقات القرآن 📖', icon: 'book.fill' };
  }
  if (lower.includes('store')) {
    return { label: 'الانتقال إلى المتجر 🛍️', icon: 'bag.fill' };
  }
  if (lower.includes('sila')) {
    return { label: 'الانتقال لشجرة العائلة 🌿', icon: 'person.3.fill' };
  }

  return { label: 'الانتقال إلى الشاشة 📲', icon: 'arrow.right.circle.fill' };
}

/**
 * تحليل وتحديد وجهة الإجراء المباشر بناءً على بيانات ونص الإشعار
 */
export function resolveNotificationAction(
  item: NotificationDetailItem | null
): ResolvedNotificationAction | null {
  if (!item) return null;

  const data = (item.data || {}) as Record<string, unknown>;
  if (data.type === 'contact_message_reply') return null;
  const actionUrl = item.actionUrl;

  const fullText = `${item.title} ${item.body}`.toLowerCase();
  const groupId = extractGroupId(data);
  const chatId = extractChatId(data);

  const isWalkingChat =
    data.type === 'walking_group_chat' ||
    data.type === 'walking_chat' ||
    data.type === 'chat' ||
    data.type === 'group_chat' ||
    data.type === 'challenge_chat' ||
    data.type === 'chat_message' ||
    data.is_chat === true ||
    data.is_chat === '1' ||
    Boolean(data.message_id) ||
    Boolean(data.chat_message_id) ||
    (typeof data.action_url === 'string' && data.action_url.includes('walking-chat')) ||
    fullText.includes('دردشة') ||
    fullText.includes('رسالة جديدة') ||
    fullText.includes('أرسل رسالة') ||
    fullText.includes('أرسلت رسالة');

  const isQuizChat =
    data.type === 'question_chat' ||
    data.type === 'quiz_chat' ||
    (typeof data.action_url === 'string' && data.action_url.includes('quiz-chat'));

  // 1) حمولة بيانات دردشة: نبني المسار الصحيح دائماً حتى لا يحدث 404
  if (isWalkingChat || isQuizChat) {
    const chatTitle = String(
      data.group_name || data.title || item.title || 'دردشة التحدي'
    );

    if (isQuizChat && chatId) {
      return {
        label: 'فتح نقاش السؤال 💬',
        icon: 'bubble.left.and.bubble.right.fill',
        route: `/(app)/quiz-chat/${chatId}?title=${encodeURIComponent(chatTitle)}`,
      };
    }

    if (isWalkingChat && groupId) {
      return {
        label: 'فتح دردشة التحدي 💬',
        icon: 'bubble.left.and.bubble.right.fill',
        route: `/(app)/walking-chat/${groupId}?title=${encodeURIComponent(chatTitle)}`,
      };
    }
  }

  // 2) رابط إجراء صريح ومباشر — يُطبَّع قبل الاستخدام حتى لا يسبب 404
  if (actionUrl) {
    const normalized = normalizeNotificationRoute(actionUrl, {
      title: item.title,
      groupId,
      chatId,
      chatKind: isQuizChat ? 'quiz' : 'walking',
    });

    if (normalized.route) {
      const meta = resolveIconForRoute(normalized.route);
      return { label: meta.label, icon: meta.icon, route: normalized.route };
    }

    if (normalized.url) {
      return {
        label: 'فتح الرابط المرفق 🌐',
        icon: 'arrow.up.right.square.fill',
        url: normalized.url,
        isExternal: true,
      };
    }
  }

  // 3. حمولة بيانات مجموعة المشي العامة
  if (
    data.type === 'walking_group' ||
    data.type === 'walking_group_removed' ||
    groupId
  ) {
    const route = groupId
      ? `/(tabs)/walking?tab=groups&groupId=${groupId}`
      : '/(tabs)/walking?tab=groups';
    return {
      label: 'الانتقال لتحدي المشي 🏃',
      icon: 'figure.walk',
      route,
    };
  }

  // 4. التعرف الذكي على النص لإشعارات المشي القديمة أو المخزنة مسبقاً
  if (
    fullText.includes('تحدي المشي') ||
    fullText.includes('برنامج المشي') ||
    fullText.includes('تحدي مشي')
  ) {
    const groupMatch =
      fullText.match(/مجموعة\s*#?(\d+)/i) || fullText.match(/المجموعة\s*#?(\d+)/i);
    const extractedGroupId = groupMatch ? groupMatch[1] : '';
    const route = extractedGroupId
      ? `/(tabs)/walking?tab=groups&groupId=${extractedGroupId}`
      : '/(tabs)/walking?tab=groups';

    return {
      label: 'الانتقال لتحدي المشي 🏃',
      icon: 'figure.walk',
      route,
    };
  }

  // 5. إشعارات تعديل وتحديث الملف الشخصي
  if (
    data.type?.toString().startsWith('profile_update') ||
    (fullText.includes('طلبك') && fullText.includes('البيانات'))
  ) {
    return {
      label: 'الانتقال إلى ملفي الشخصي 👤',
      icon: 'person.crop.circle.fill',
      route: '/(app)/my-profile',
    };
  }

  // 6. رابط مرفق عام في حقل linkUrl
  if (item.linkUrl) {
    return {
      label: 'فتح الرابط المرفق 🌐',
      icon: 'arrow.up.right.square.fill',
      url: item.linkUrl,
      isExternal: true,
    };
  }

  // 7. الروابط المكتشفة تلقائياً في نص الإشعار
  const embeddedUrls = extractUrls(item.body);
  if (embeddedUrls.length > 0) {
    return {
      label: 'فتح الرابط المرفق 🌐',
      icon: 'arrow.up.right.square.fill',
      url: embeddedUrls[0],
      isExternal: true,
    };
  }

  return null;
}
