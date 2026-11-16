import * as Clipboard from 'expo-clipboard';
import * as Haptics from 'expo-haptics';

import { CustomAlert } from '@/components/ui/AlertModal';
import type { ChatActionItem } from '@/components/chat/ChatActionSheetModal';
import type { ChatMessageItem } from '@/lib/api/chatApi';
import { audioPlaybackManager } from '@/lib/audio/audioPlaybackManager';

export interface MessageActionsContext {
  message: ChatMessageItem;
  isMe: boolean;
  canSend?: boolean;
  onDeletePress?: (message: ChatMessageItem, mode: 'me' | 'everyone') => void;
  onReplyPress?: (message: ChatMessageItem) => void;
  onReportPress?: (message: ChatMessageItem) => void;
  onEditPress?: (message: ChatMessageItem) => void;
}

/**
 * التحقق من ملكية الرسالة بشكل موحّد في كل الشاشات
 */
export function resolveIsOwnMessage(
  message: ChatMessageItem,
  currentUserId?: string | number | null
): boolean {
  return Boolean(
    message.is_me ||
    (currentUserId != null &&
      Number(message.sender?.id) === Number(currentUserId))
  );
}

/**
 * بناء قائمة خيارات الرسالة (نسخ / مسح من عندي / مسح لدى الجميع)
 * تُستخدم خارج قائمة الرسائل المعكوسة حتى لا تتأثر بالتحويل scaleY:-1
 */
export function buildMessageActions({
  message,
  isMe,
  canSend = true,
  onDeletePress,
  onReplyPress,
  onReportPress,
  onEditPress,
}: MessageActionsContext): ChatActionItem[] {
  const createdTime = new Date(message.created_at).getTime();
  const diffMinutes = (Date.now() - createdTime) / (1000 * 60);
  const isWithinHour = !Number.isNaN(diffMinutes) && diffMinutes <= 60;
  const remainingMinutes = Math.max(1, Math.round(60 - diffMinutes));

  const items: ChatActionItem[] = [];

  // 0. خيار الرد على الرسالة (لا يُعرض للرسائل الرسمية ولا عند منع الإرسال)
  if (onReplyPress && canSend && !message.is_system) {
    items.push({
      id: 'reply',
      label: 'رد على الرسالة',
      sublabel: 'اقتباس هذه الرسالة في ردك',
      icon: 'arrow-undo-outline',
      onPress: () => {
        onReplyPress(message);
      },
    });
  }

  // 0.5 خيار تعديل رسالتي النصية (خلال أول 60 دقيقة من الإرسال)
  if (onEditPress && isMe && !message.is_system && message.type === 'text' && message.message) {
    if (isWithinHour) {
      items.push({
        id: 'edit',
        label: 'تعديل الرسالة',
        sublabel: `يمكنك تعديل النص خلال ${remainingMinutes} دقيقة`,
        icon: 'create-outline',
        onPress: () => {
          onEditPress(message);
        },
      });
    } else {
      items.push({
        id: 'edit_expired',
        label: 'تعديل الرسالة',
        sublabel: 'متاح فقط خلال أول 60 دقيقة من وقت الإرسال',
        icon: 'time-outline',
        variant: 'disabled',
        onPress: () => {},
      });
    }
  }

  // 1. خيار نسخ النص إن وُجد
  if (message.message) {
    items.push({
      id: 'copy',
      label: 'نسخ نص الرسالة',
      icon: 'copy-outline',
      onPress: async () => {
        try {
          await Clipboard.setStringAsync(message.message || '');
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
          CustomAlert.alert('تم النسخ', 'تم نسخ نص الرسالة إلى الحافظة بنجاح.');
        } catch {}
      },
    });
  }

  // 1.5 خيار الإبلاغ عن رسائل الآخرين
  if (onReportPress && !isMe && !message.is_system) {
    items.push({
      id: 'report',
      label: 'إبلاغ عن الرسالة',
      sublabel: 'إرسال بلاغ لفريق الإشراف لمراجعة المحتوى',
      icon: 'flag-outline',
      iconColor: '#E5484D',
      onPress: () => {
        onReportPress(message);
      },
    });
  }

  // 2. خيار مسح الرسالة من عندي
  items.push({
    id: 'delete_me',
    label: 'مسح من عندي',
    sublabel: 'إخفاء الرسالة من جهازك فقط دون التأثير على البقية',
    icon: 'trash-outline',
    variant: 'destructive',
    onPress: () => {
      audioPlaybackManager.stopAll();
      onDeletePress?.(message, 'me');
    },
  });

  // 3. خيار مسح الرسالة لدى الجميع (إذا كانت رسالتي)
  if (isMe) {
    if (isWithinHour) {
      items.push({
        id: 'delete_everyone',
        label: 'مسح لدى الجميع',
        sublabel: 'حذف الرسالة نهائياً لدى جميع أعضاء المحادثة',
        badge: `متبقي ${remainingMinutes} دقيقة`,
        icon: 'people-outline',
        variant: 'destructive',
        onPress: () => {
          audioPlaybackManager.stopAll();
          CustomAlert.alert(
            'مسح لدى الجميع',
            'هل أنت متأكد من مسح هذه الرسالة لدى جميع المشاركين في المحادثة؟\nلن يتمكن أحد من رؤيتها بعد الآن.',
            [
              { text: 'تراجع', style: 'cancel' },
              {
                text: 'نعم، مسح لدى الجميع',
                style: 'destructive',
                icon: 'trash-outline',
                onPress: () => {
                  audioPlaybackManager.stopAll();
                  onDeletePress?.(message, 'everyone');
                },
              },
            ]
          );
        },
      });
    } else {
      items.push({
        id: 'delete_everyone_expired',
        label: 'مسح لدى الجميع',
        sublabel: 'متاح فقط خلال أول 60 دقيقة من وقت الإرسال',
        icon: 'time-outline',
        variant: 'disabled',
        onPress: () => {},
      });
    }
  }

  return items;
}
