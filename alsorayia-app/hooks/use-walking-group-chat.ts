import { useCallback, useEffect, useRef, useState } from 'react';
import type {
  ChatMessageItem,
  ChatModerationMeta,
  ChatReportReason,
  SendMessagePayload,
  WalkingGroupChatRoom,
} from '@/lib/api/chatApi';
import {
  fetchWalkingGroupChat,
  fetchWalkingGroupMessages,
  markWalkingGroupChatAsRead,
  sendWalkingGroupMessage,
  toggleWalkingGroupChatMute,
  deleteWalkingGroupMessage,
  clearWalkingGroupChat,
  updateWalkingGroupMessage,
  reportChatMessage,
} from '@/lib/api/chatApi';
import { useAuth } from '@/contexts/auth-context';
import * as Haptics from 'expo-haptics';

interface UseWalkingGroupChatOptions {
  groupId: number | null;
  isActive: boolean;
  canChat?: boolean;
}

export function useWalkingGroupChat({ groupId, isActive, canChat = true }: UseWalkingGroupChatOptions) {
  const { user } = useAuth();
  const currentUserId = user?.id;

  const [messages, setMessages] = useState<ChatMessageItem[]>([]);
  const [room, setRoom] = useState<WalkingGroupChatRoom | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [fetchingOlder, setFetchingOlder] = useState<boolean>(false);
  const [hasMoreOlder, setHasMoreOlder] = useState<boolean>(true);
  const [sending, setSending] = useState<boolean>(false);
  const [isMuted, setIsMuted] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [replyingTo, setReplyingTo] = useState<ChatMessageItem | null>(null);
  const [moderation, setModeration] = useState<ChatModerationMeta | null>(null);

  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const latestMessageIdRef = useRef<number | null>(null);
  const pinnedMessageIdRef = useRef<number | null>(null);

  const moderationFromRoom = useCallback(
    (data: WalkingGroupChatRoom): ChatModerationMeta => ({
      is_locked: Boolean(data.is_locked),
      lock_reason: data.lock_reason ?? null,
      slow_mode_seconds: data.slow_mode_seconds ?? 0,
      allow_attachments: data.allow_attachments !== false,
      pinned_message_id: data.pinned_message_id ?? null,
      can_send: data.can_send !== false,
      send_blocked_reason: data.send_blocked_reason ?? null,
      send_blocked_message: data.send_blocked_message ?? null,
      retry_after: data.retry_after ?? null,
    }),
    []
  );

  const normalizeItem = useCallback(
    (item: ChatMessageItem): ChatMessageItem => ({
      ...item,
      is_me: Boolean(
        item.is_me || (currentUserId && Number(item.sender?.id) === Number(currentUserId))
      ),
    }),
    [currentUserId]
  );

  const normalizeList = useCallback(
    (items: ChatMessageItem[]): ChatMessageItem[] => {
      return (items || []).map(normalizeItem);
    },
    [normalizeItem]
  );

  // تحديث المرجع بأحدث رسالة لمقارنة واسترجاع الجديد
  useEffect(() => {
    if (messages.length > 0) {
      const maxId = Math.max(...messages.map((m) => m.id));
      latestMessageIdRef.current = maxId;
    } else {
      latestMessageIdRef.current = null;
    }
  }, [messages]);

  // 1. جلب البيانات الأولية للغرفة والرسائل
  const loadInitialData = useCallback(async () => {
    if (!groupId) return;
    setLoading(true);
    setError(null);
    try {
      const [roomData, messagesData] = await Promise.all([
        fetchWalkingGroupChat(groupId),
        fetchWalkingGroupMessages(groupId, { limit: 30 }),
      ]);

      if (roomData) {
        setRoom(roomData);
        setIsMuted(roomData.is_muted);
        setModeration(moderationFromRoom(roomData));
        pinnedMessageIdRef.current = roomData.pinned_message_id ?? null;
      }

      if (messagesData?.moderation) {
        setModeration(messagesData.moderation);
        pinnedMessageIdRef.current = messagesData.moderation.pinned_message_id;
      }

      setMessages(normalizeList(messagesData || []));
      setHasMoreOlder((messagesData?.length || 0) >= 30);

      // تحديد القراءة لآخر رسالة تم استلامها
      if (messagesData && messagesData.length > 0) {
        const topMessage = messagesData[0];
        void markWalkingGroupChatAsRead(groupId, topMessage.id);
      }
    } catch (err: any) {
      setError(err?.message || 'تعذر تحميل المحادثة');
    } finally {
      setLoading(false);
    }
  }, [groupId, normalizeList, moderationFromRoom]);

  useEffect(() => {
    if (groupId && isActive) {
      loadInitialData();
    } else {
      setMessages([]);
      setRoom(null);
      setModeration(null);
      setReplyingTo(null);
      pinnedMessageIdRef.current = null;
      setLoading(false);
    }
  }, [groupId, isActive, loadInitialData]);

  // 2. الاستطلاع الذكي الخفيف للرسائل الجديدة أثناء فتح الشات (Smart Light Polling)
  useEffect(() => {
    if (!groupId || !isActive) {
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
      return;
    }

    pollingRef.current = setInterval(async () => {
      const afterId = latestMessageIdRef.current;
      if (!afterId || !groupId) return;

      try {
        const rawNew = await fetchWalkingGroupMessages(groupId, { after_id: afterId });

        // مزامنة حالة الإشراف (قفل / وضع بطيء / منع الإرسال) مع كل استطلاع
        const nextModeration = rawNew?.moderation;
        if (nextModeration) {
          setModeration(nextModeration);
          if (nextModeration.pinned_message_id !== pinnedMessageIdRef.current) {
            pinnedMessageIdRef.current = nextModeration.pinned_message_id;
            void fetchWalkingGroupChat(groupId).then((fresh) => {
              if (fresh) setRoom(fresh);
            });
          }
        }

        // التزامن اللحظي للرسائل المحذوفة لدى الجميع
        const deletedIds = (rawNew as any)?.deleted_message_ids;
        if (Array.isArray(deletedIds) && deletedIds.length > 0) {
          const setDel = new Set(deletedIds);
          setMessages((prev) => prev.filter((m) => !setDel.has(m.id)));
        }

        // التزامن اللحظي للرسائل المعدلة لدى الجميع
        const editedMessages = (rawNew as any)?.edited_messages;
        if (Array.isArray(editedMessages) && editedMessages.length > 0) {
          const editMap = new Map<number, ChatMessageItem>(
            editedMessages.map((em: ChatMessageItem) => [em.id, em])
          );
          setMessages((prev) =>
            prev.map((m) => {
              const updated = editMap.get(m.id);
              if (updated) {
                return {
                  ...m,
                  message: updated.message,
                  is_edited: true,
                  edited_at: updated.edited_at,
                };
              }
              return m;
            })
          );
        }

        const newMessages = normalizeList(rawNew || []);
        if (newMessages && newMessages.length > 0) {
          setMessages((prev) => {
            const existingIds = new Set(prev.map((m) => m.id));
            const freshOnes = newMessages.filter((m) => !existingIds.has(m.id));
            if (freshOnes.length === 0) return prev;

            try {
              Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            } catch {}

            return [...freshOnes, ...prev];
          });

          // تمييز المقروء للرسائل الجديدة
          const newest = newMessages[0];
          if (newest) {
            void markWalkingGroupChatAsRead(groupId, newest.id);
          }
        }
      } catch {
        // تجاهل أخطاء الشبكة أثناء الاستطلاع في الخلفية
      }
    }, 4500);

    return () => {
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
    };
  }, [groupId, isActive, normalizeList]);

  // 3. تحميل الرسائل الأقدم بالسحب لأعلى
  const loadOlderMessages = useCallback(async () => {
    if (!groupId || fetchingOlder || !hasMoreOlder || messages.length === 0) return;
    const oldestId = Math.min(...messages.map((m) => m.id));
    setFetchingOlder(true);

    try {
      const rawOlder = await fetchWalkingGroupMessages(groupId, { before_id: oldestId, limit: 30 });
      const older = normalizeList(rawOlder || []);
      if (!older || older.length === 0) {
        setHasMoreOlder(false);
      } else {
        setMessages((prev) => {
          const existingIds = new Set(prev.map((m) => m.id));
          const uniqueOlder = older.filter((m) => !existingIds.has(m.id));
          return [...prev, ...uniqueOlder];
        });
        if (older.length < 30) {
          setHasMoreOlder(false);
        }
      }
    } catch {
      //
    } finally {
      setFetchingOlder(false);
    }
  }, [groupId, fetchingOlder, hasMoreOlder, messages, normalizeList]);

  // 4. إرسال رسالة جديدة (مع دعم الرد على رسالة)
  const sendMessage = useCallback(
    async (payload: SendMessagePayload) => {
      if (!groupId || sending) return { success: false };
      setSending(true);

      const effectivePayload: SendMessagePayload = {
        ...payload,
        reply_to_message_id: payload.reply_to_message_id ?? replyingTo?.id ?? null,
      };

      try {
        const result = await sendWalkingGroupMessage(groupId, effectivePayload);
        if (result.success && result.data) {
          const normalized = normalizeItem(result.data);
          try {
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          } catch {}

          setMessages((prev) => [normalized, ...prev.filter((m) => m.id !== normalized.id)]);
          setReplyingTo(null);
          return { success: true };
        }

        // مزامنة حالة الإشراف عند الرفض من الخادم (حظر / قفل / وضع بطيء)
        if (result.status === 403 || result.status === 429) {
          const isSlowMode = result.reason === 'slow_mode';
          setModeration((prev) => ({
            is_locked: result.reason === 'conversation_locked' ? true : Boolean(prev?.is_locked),
            lock_reason: prev?.lock_reason ?? null,
            slow_mode_seconds: prev?.slow_mode_seconds ?? 0,
            allow_attachments: prev?.allow_attachments !== false,
            pinned_message_id: prev?.pinned_message_id ?? null,
            can_send: isSlowMode ? prev?.can_send !== false : false,
            send_blocked_reason: result.reason ?? prev?.send_blocked_reason ?? null,
            send_blocked_message: result.message ?? prev?.send_blocked_message ?? null,
            retry_after: result.retry_after ?? null,
          }));
        }

        return { success: false, message: result.message, reason: result.reason ?? null };
      } catch (err: any) {
        return { success: false, message: err?.message || 'حدث خطأ أثناء الإرسال' };
      } finally {
        setSending(false);
      }
    },
    [groupId, sending, normalizeItem, replyingTo]
  );

  // 4.1 بدء / إلغاء الرد على رسالة
  const replyToMessage = useCallback((message: ChatMessageItem) => {
    setReplyingTo(message);
  }, []);

  const cancelReply = useCallback(() => {
    setReplyingTo(null);
  }, []);

  // 4.2 الإبلاغ عن رسالة
  const reportMessage = useCallback(
    async (messageId: number, reason: ChatReportReason, details?: string) =>
      reportChatMessage(messageId, reason, details),
    []
  );

  // 5. تبديل كتم الإشعارات
  const toggleMute = useCallback(async () => {
    if (!groupId) return;
    const updated = await toggleWalkingGroupChatMute(groupId);
    if (updated !== null) {
      setIsMuted(updated);
      try {
        Haptics.selectionAsync();
      } catch {}
    }
  }, [groupId]);

  // 6. حذف رسالة (لدي فقط أو لدى الجميع)
  const deleteMessage = useCallback(
    async (messageId: number, mode: 'me' | 'everyone' = 'me') => {
      if (!groupId) return { success: false, message: 'المحادثة غير محددة' };

      // حذف فوري من الواجهة للمستخدم (Optimistic UI)
      setMessages((prev) => prev.filter((m) => m.id !== messageId));

      try {
        const res = await deleteWalkingGroupMessage(groupId, messageId, mode);
        if (!res.success) {
          void loadInitialData();
          return { success: false, message: res.message };
        }
        return { success: true };
      } catch (err: any) {
        void loadInitialData();
        return { success: false, message: err?.message || 'تعذر حذف الرسالة' };
      }
    },
    [groupId, loadInitialData]
  );

  // 7. تعديل نص رسالة سابقة (خلال أول 60 دقيقة)
  const editMessage = useCallback(
    async (messageId: number, newText: string): Promise<{ success: boolean; message?: string }> => {
      if (!groupId) return { success: false, message: 'المحادثة غير محددة' };

      const trimmed = newText.trim();
      if (!trimmed) return { success: false, message: 'نص الرسالة لا يمكن أن يكون فارغاً' };

      // التحديث الفوري المسبق في واجهة المستخدم (Optimistic UI)
      setMessages((prev) =>
        prev.map((m) =>
          m.id === messageId
            ? { ...m, message: trimmed, is_edited: true, edited_at: new Date().toISOString() }
            : m
        )
      );

      try {
        const res = await updateWalkingGroupMessage(groupId, messageId, trimmed);
        if (!res.success) {
          void loadInitialData();
          return { success: false, message: res.message || 'فشل تعديل الرسالة' };
        }

        if (res.data) {
          const freshData = res.data;
          setMessages((prev) =>
            prev.map((m) => (m.id === messageId ? { ...m, ...freshData } : m))
          );
        }

        try {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch {}

        return { success: true, message: res.message };
      } catch (err: any) {
        void loadInitialData();
        return { success: false, message: err?.message || 'حدث خطأ أثناء تعديل الرسالة' };
      }
    },
    [groupId, loadInitialData]
  );

  // 8. مسح محادثة التحدي من شاشة المستخدم ("مسح الشات من عندي")
  const clearChat = useCallback(async () => {
    if (!groupId) return { success: false, message: 'المحادثة غير محددة' };

    // مسح فوري من الواجهة
    setMessages([]);
    setReplyingTo(null);

    try {
      const res = await clearWalkingGroupChat(groupId);
      if (!res.success) {
        void loadInitialData();
        return { success: false, message: res.message };
      }
      return { success: true };
    } catch (err: any) {
      void loadInitialData();
      return { success: false, message: err?.message || 'تعذر مسح المحادثة' };
    }
  }, [groupId, loadInitialData]);

  return {
    messages,
    room,
    loading,
    fetchingOlder,
    hasMoreOlder,
    sending,
    isMuted,
    error,
    moderation,
    replyingTo,
    canSend: moderation ? moderation.can_send : (room?.can_send !== false),
    refresh: loadInitialData,
    loadOlderMessages,
    sendMessage,
    toggleMute,
    deleteMessage,
    editMessage,
    clearChat,
    replyToMessage,
    cancelReply,
    reportMessage,
  };
}
