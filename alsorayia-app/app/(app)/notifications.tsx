import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  deleteNotification,
  deleteMultipleNotifications,
  clearAllNotifications,
  normalizeNotificationMediaUrl,
  inferNotificationMediaType,
  type InboxNotification,
} from '@/lib/api/notifications';
import {
  extractInvitationError,
  fetchInvitationLogDetail,
  respondToInvitation,
  type InvitationResponse,
} from '@/lib/api/invitations';
import {
  resolveNotificationAction,
  type NotificationDetailItem,
  type ResolvedNotificationAction,
} from '@/components/notifications/notificationActions';
import { SwipeableNotificationCard } from '@/components/notifications/SwipeableNotificationCard';
import { CustomAlert } from '@/components/ui/AlertModal';
import MediaLightbox from '@/components/quiz/MediaLightbox';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useEffect, useState, useCallback, useMemo, useRef } from 'react';
import { ListSkeleton } from '@/components/ui/skeleton';
import {
  FlatList,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
  RefreshControl,
  LayoutAnimation,
  Platform,
  UIManager,
  Share,
  Linking,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { appStorage } from '@/lib/storage/appStorage';
import * as Haptics from 'expo-haptics';
import * as WebBrowser from 'expo-web-browser';

if (Platform.OS === 'android' && UIManager.setLayoutAnimationEnabledExperimental) {
  UIManager.setLayoutAnimationEnabledExperimental(true);
}

type FilterType = 'all' | 'unread' | 'media';

function cleanNotificationText(text: string | null | undefined, viewerName?: string | null): string {
  if (!text) return '';
  const fallback = viewerName?.trim() || 'ضيفنا العزيز';
  return text
    .replace(/\{(?:first_)?name\}/gi, fallback)
    .replace(/\{full_name\}/gi, viewerName?.trim() || 'زائرنا الكريم')
    .replace(/\{\{(?:first_)?name\}\}/gi, fallback)
    .replace(/:(?:first_)?name\b/gi, fallback)
    .replace(/(مرحباً|أهلاً|عزيزي|عزيزتي|الغالي\/ة|الغالي|الغالية|حياك)\s+name\b/gu, `$1 ${fallback}`)
    .replace(/\bname\b/gu, fallback);
}

function mapInboxItem(
  n: InboxNotification,
  localReadIds: string[],
  viewerName?: string | null
): NotificationDetailItem {
  const idStr = String(n.id);
  const normalizedMediaUrl = normalizeNotificationMediaUrl(n.media_url);
  const mediaType = inferNotificationMediaType(n.media_type, normalizedMediaUrl);

  return {
    id: idStr,
    title: cleanNotificationText(n.title, viewerName) || 'إشعار جديد',
    body: n.data?.type === 'contact_message_reply' ? n.body : cleanNotificationText(n.body, viewerName),
    time: n.created_at || '',
    timeAgo: n.time_ago || '',
    read: n.read || localReadIds.includes(idStr),
    type:
      mediaType === 'image'
        ? 'event'
        : mediaType === 'video'
          ? 'update'
          : 'system',
    meta: n.media_type
      ? n.media_type === 'image'
        ? 'صورة'
        : n.media_type === 'video'
          ? 'فيديو'
          : 'مقطع صوتي'
      : undefined,
    mediaType,
    mediaUrl: normalizedMediaUrl,
    // link_url هو رابط خارجي فقط، أما action_url فمسار داخل التطبيق
    linkUrl: n.link_url || n.url || null,
    actionUrl: n.action_url || null,
    data: n.data || null,
    invitation: n.invitation ?? null,
  };
}

export default function NotificationsScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const router = useRouter();
  const params = useLocalSearchParams<{ id?: string }>();
  const { hasIdentity, user } = useAuth();
  const invitationViewerId = hasIdentity ? user?.id ?? null : null;
  const currentViewerId = useRef(invitationViewerId);
  const savedInvitationResponses = useRef(new Map<string, InvitationResponse>());
  const responseRequests = useRef(new Set<string>());
  const [pendingResponseKeys, setPendingResponseKeys] = useState<string[]>([]);

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [notifications, setNotifications] = useState<NotificationDetailItem[]>([]);
  const [readLocalIds, setReadLocalIds] = useState<string[]>([]);
  const [deletedLocalIds, setDeletedLocalIds] = useState<string[]>([]);
  const [selectedFilter, setSelectedFilter] = useState<FilterType>('all');
  const [expandedIds, setExpandedIds] = useState<string[]>([]);

  // حالة وضع التحديد المتعدد (Select Mode)
  const [isSelectionMode, setIsSelectionMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  // عارض الصور المباشر بدقة كاملة وتكبير
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);
  const [lightboxTitle, setLightboxTitle] = useState<string | undefined>(undefined);

  useEffect(() => {
    currentViewerId.current = invitationViewerId;
  }, [invitationViewerId]);

  const loadNotifications = useCallback(async (isRefresh = false) => {
    try {
      if (isRefresh) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      // جلب معرفات المقروءة والمحذوفة محلياً
      const [localReadStr, localDeletedStr] = await Promise.all([
        appStorage.getItem('read_notification_ids'),
        appStorage.getItem('deleted_notification_ids'),
      ]);

      const localIds: string[] = localReadStr ? JSON.parse(localReadStr) : [];
      setReadLocalIds(localIds);

      const deletedIds: string[] = localDeletedStr ? JSON.parse(localDeletedStr) : [];
      setDeletedLocalIds(deletedIds);

      const items = await fetchNotifications();
      const requestedId = params.id ? String(params.id) : null;
      if (requestedId && !deletedIds.includes(requestedId) && !items.some((item) => String(item.id) === requestedId)) {
        const [requestedItem] = await fetchNotifications(requestedId).catch(() => []);
        if (requestedItem && String(requestedItem.id) === requestedId) items.unshift(requestedItem);
      }
      if (currentViewerId.current !== invitationViewerId) return;

      // فلترة الإشعارات المحذوفة ومطابقة المقروءة
      const mapped = items
        .filter((n) => !deletedIds.includes(String(n.id)))
        .map((n) => {
          const item = mapInboxItem(n, localIds, user?.first_name);
          if (item.invitation?.response !== null || invitationViewerId === null) return item;

          const response = savedInvitationResponses.current.get(`${invitationViewerId}:${item.invitation.id}`);
          return response ? { ...item, invitation: { ...item.invitation, response } } : item;
        });

      setNotifications(mapped);

      // التعامل مع المعرف القادم من الإشعار الخارجي (Push Notification / Deep Link)
      if (requestedId) {
        const target = mapped.find((item) => item.id === requestedId);
        if (target) {
          const act = resolveNotificationAction(target);
          if (act?.route) {
            router.push(act.route as any);
          } else if (act?.url) {
            WebBrowser.openBrowserAsync(act.url);
          } else {
            // توسيع الإشعار في مكانه إذا كان نصاً إعلانياً
            setExpandedIds((prev) => [...prev, target.id]);
          }
        }
      }
    } catch (e) {
      console.error('[Notifications] Failed to load notifications:', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [invitationViewerId, params.id, user?.first_name, router]);

  useEffect(() => {
    loadNotifications();
  }, [loadNotifications, hasIdentity]);

  const saveInvitationResponse = async (invitationId: number, response: InvitationResponse) => {
    if (invitationViewerId === null || currentViewerId.current !== invitationViewerId) return;
    const key = `${invitationViewerId}:${invitationId}`;
    if (responseRequests.current.has(key) || savedInvitationResponses.current.has(key)) return;

    responseRequests.current.add(key);
    setPendingResponseKeys(Array.from(responseRequests.current));

    try {
      const result = await respondToInvitation(invitationId, response);
      let savedResponse = result.success ? result.data?.invitation_response : null;

      if (result.code === 'INVITATION_RESPONSE_ALREADY_RECORDED') {
        const detail = await fetchInvitationLogDetail(invitationId, 'incoming');
        if (detail.success) savedResponse = detail.data?.notification.invitation_response;
      }

      if (currentViewerId.current !== invitationViewerId) return;
      if (!savedResponse) {
        CustomAlert.alert('تعذّر حفظ الرد', extractInvitationError(result));
        return;
      }

      savedInvitationResponses.current.set(key, savedResponse);
      setNotifications((current) => current.map((item) =>
        item.invitation?.id === invitationId
          ? { ...item, invitation: { ...item.invitation, response: savedResponse } }
          : item
      ));
    } finally {
      responseRequests.current.delete(key);
      setPendingResponseKeys(Array.from(responseRequests.current));
    }
  };

  const confirmInvitationResponse = (item: NotificationDetailItem, response: InvitationResponse) => {
    if (invitationViewerId === null || item.invitation?.response !== null) return;
    const invitationId = item.invitation.id;
    const accepted = response === 'accepted';
    const confirmLabel = accepted ? 'تأكيد القبول' : 'تأكيد الرفض';

    CustomAlert.alert(confirmLabel, 'بعد التأكيد، لا يمكنك تغيير ردك.', [
      { text: 'إلغاء', style: 'cancel' },
      { text: confirmLabel, onPress: () => void saveInvitationResponse(invitationId, response) },
    ]);
  };

  /**
   * تبديل حالة القراءة لإشعار فردي فورياً مع تحديث الذاكرة والـ Backend
   */
  const toggleReadStatus = async (id: string, forceReadState?: boolean) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});

    const targetItem = notifications.find((n) => n.id === id);
    if (!targetItem) return;

    const nextRead = forceReadState !== undefined ? forceReadState : !targetItem.read;

    // تحديث تفاؤلي فوري في الـ UI
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, read: nextRead } : n))
    );

    let updatedIds: string[];
    if (nextRead) {
      updatedIds = Array.from(new Set([...readLocalIds, id]));
    } else {
      updatedIds = readLocalIds.filter((item) => item !== id);
    }
    setReadLocalIds(updatedIds);

    try {
      await appStorage.setItem('read_notification_ids', JSON.stringify(updatedIds));

      if (hasIdentity && nextRead) {
        markNotificationRead(id).catch((e) => {
          console.warn('[Notifications] markNotificationRead error:', e);
        });
      }
    } catch (e) {
      console.warn('[Notifications] Error persisting read state:', e);
    }
  };

  /**
   * حذف إشعار فردي فورياً (Swipe to delete أو من الزر)
   */
  const handleDeleteNotification = async (id: string) => {
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setNotifications((prev) => prev.filter((n) => n.id !== id));
    setSelectedIds((prev) => prev.filter((itemId) => itemId !== id));

    const updatedDeletedIds = Array.from(new Set([...deletedLocalIds, id]));
    setDeletedLocalIds(updatedDeletedIds);

    try {
      await appStorage.setItem(
        'deleted_notification_ids',
        JSON.stringify(updatedDeletedIds)
      );
      if (hasIdentity) {
        deleteNotification(id).catch((e) => {
          console.warn('[Notifications] deleteNotification error:', e);
        });
      }
    } catch (e) {
      console.warn('[Notifications] Error persisting single delete:', e);
    }
  };

  /**
   * تفعيل وضع التحديد المتعدد
   */
  const handleEnterSelectionMode = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
    setIsSelectionMode(true);
    setSelectedIds([]);
  };

  /**
   * إلغاء وضع التحديد المتعدد
   */
  const handleExitSelectionMode = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
    setIsSelectionMode(false);
    setSelectedIds([]);
  };

  /**
   * تبديل تحديد إشعار معين
   */
  const handleToggleSelectNotification = (id: string) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((itemId) => itemId !== id) : [...prev, id]
    );
  };

  /**
   * تحديد الكل / إلغاء تحديد الكل في الفلتر الحالي
   */
  const handleToggleSelectAll = () => {
    Haptics.selectionAsync().catch(() => {});
    const currentListIds = filteredNotifications.map((n) => n.id);
    const isAllCurrentSelected = currentListIds.every((id) => selectedIds.includes(id));

    if (isAllCurrentSelected) {
      setSelectedIds((prev) => prev.filter((id) => !currentListIds.includes(id)));
    } else {
      setSelectedIds((prev) => Array.from(new Set([...prev, ...currentListIds])));
    }
  };

  /**
   * تأكيد وحذف الإشعارات المحددة
   */
  const handlePromptDeleteSelected = () => {
    if (selectedIds.length === 0) return;
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});

    CustomAlert.alert(
      'حذف الإشعارات المحددة',
      `هل أنت متأكد من رغبتك في حذف ${selectedIds.length} إشعار نهائياً من صندوق الوارد؟`,
      [
        { text: 'تراجع', style: 'cancel' },
        {
          text: `حذف (${selectedIds.length})`,
          style: 'destructive',
          onPress: async () => {
            const idsToDelete = [...selectedIds];
            LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
            setNotifications((prev) => prev.filter((n) => !idsToDelete.includes(n.id)));
            setIsSelectionMode(false);
            setSelectedIds([]);

            const updatedDeletedIds = Array.from(new Set([...deletedLocalIds, ...idsToDelete]));
            setDeletedLocalIds(updatedDeletedIds);

            try {
              await appStorage.setItem(
                'deleted_notification_ids',
                JSON.stringify(updatedDeletedIds)
              );
              if (hasIdentity) {
                deleteMultipleNotifications(idsToDelete).catch((e) => {
                  console.warn('[Notifications] deleteMultipleNotifications error:', e);
                });
              }
              Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
            } catch (e) {
              console.warn('[Notifications] Error persisting batch delete:', e);
            }
          },
        },
      ]
    );
  };

  /**
   * تأكيد ومسح جميع الإشعارات
   */
  const handlePromptClearAll = () => {
    if (notifications.length === 0) return;
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});

    CustomAlert.alert(
      'مسح كافة الإشعارات',
      'هل أنت متأكد من رغبتك في مسح جميع الإشعارات والتنبيهات؟ لن تتمكن من استرجاعها.',
      [
        { text: 'تراجع', style: 'cancel' },
        {
          text: 'مسح الكل',
          style: 'destructive',
          onPress: async () => {
            const allIds = notifications.map((n) => n.id);
            LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
            setNotifications([]);
            setIsSelectionMode(false);
            setSelectedIds([]);

            const updatedDeletedIds = Array.from(new Set([...deletedLocalIds, ...allIds]));
            setDeletedLocalIds(updatedDeletedIds);

            try {
              await appStorage.setItem(
                'deleted_notification_ids',
                JSON.stringify(updatedDeletedIds)
              );
              if (hasIdentity) {
                clearAllNotifications().catch((e) => {
                  console.warn('[Notifications] clearAllNotifications error:', e);
                });
              }
              Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
            } catch (e) {
              console.warn('[Notifications] Error persisting clear all:', e);
            }
          },
        },
      ]
    );
  };

  /**
   * توسيع وقراءة تفاصيل الإشعار بالكامل في مكانه (Inline Fluid Expansion)
   */
  const handleToggleExpand = (item: NotificationDetailItem) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});

    if (!item.read) {
      toggleReadStatus(item.id, true);
    }

    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setExpandedIds((prev) =>
      prev.includes(item.id)
        ? prev.filter((id) => id !== item.id)
        : [...prev, item.id]
    );
  };

  /**
   * تنفيذ الإجراء التفاعلي المباشر للانتقال إلى شاشة الوجهة
   */
  const handleExecuteAction = async (
    action: ResolvedNotificationAction,
    item: NotificationDetailItem
  ) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});

    if (!item.read) {
      toggleReadStatus(item.id, true);
    }

    if (action.route) {
      router.push(action.route as any);
      return;
    }

    if (action.url) {
      try {
        if (action.url.startsWith('http://') || action.url.startsWith('https://')) {
          await WebBrowser.openBrowserAsync(action.url);
        } else {
          await Linking.openURL(action.url);
        }
      } catch (e) {
        console.warn('[Notifications] Failed to open URL:', e);
      }
    }
  };

  /**
   * تحديد جميع الإشعارات كمقروءة دفعة واحدة
   */
  const handleMarkAllAsRead = async () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
    const unreadIds = notifications.filter((n) => !n.read).map((n) => n.id);
    if (unreadIds.length === 0) return;

    const updatedReadIds = Array.from(new Set([...readLocalIds, ...unreadIds]));
    setReadLocalIds(updatedReadIds);
    setNotifications((prev) => prev.map((n) => ({ ...n, read: true })));

    try {
      await appStorage.setItem('read_notification_ids', JSON.stringify(updatedReadIds));
      if (hasIdentity) {
        await markAllNotificationsRead();
      }
    } catch (e) {
      console.warn('[Notifications] Error marking all as read:', e);
    }
  };

  /**
   * مشاركة نص الإشعار
   */
  const handleShareNotification = async (item: NotificationDetailItem) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
      let message = `${item.title}\n\n${item.body}`;
      if (item.linkUrl) {
        message += `\n\n🔗 ${item.linkUrl}`;
      } else if (item.mediaUrl) {
        message += `\n\n📎 ${item.mediaUrl}`;
      }
      await Share.share({
        title: item.title,
        message,
      });
    } catch (e) {
      console.warn('[Notifications] Share failed:', e);
    }
  };

  const unreadCount = useMemo(
    () => notifications.filter((n) => !n.read).length,
    [notifications]
  );

  const mediaCount = useMemo(
    () => notifications.filter((n) => n.mediaUrl && n.mediaType !== 'none').length,
    [notifications]
  );

  // يعيد مُحسِّن React Compiler تذكّر هذا الحساب تلقائياً — بلا memoization يدوية
  const unread = notifications.filter((n) => !n.read);
  const mediaOnly = notifications.filter((n) => n.mediaUrl && n.mediaType !== 'none');
  const filteredNotifications =
    selectedFilter === 'unread' ? unread : selectedFilter === 'media' ? mediaOnly : notifications;

  return (
    <ThemedView style={styles.container}>
      {/* الرأس التفاعلي المتكيف مع وضع العرض أو وضع التحديد */}
      {!isSelectionMode ? (
        <HeaderBar
          title="الإشعارات"
          subtitle={
            loading
              ? 'جاري التحميل…'
              : notifications.length === 0
                ? undefined
                : unreadCount > 0
                  ? `${unreadCount} غير مقروءة`
                  : 'جميع الإشعارات مقروءة'
          }
          showBack
          onBackPress={() => router.back()}
        >
          {notifications.length > 0 && (
            <TouchableOpacity
              activeOpacity={0.7}
              onPress={handleEnterSelectionMode}
              style={[
                styles.headerActionTextBtn,
                { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F1F5F9' },
              ]}
              accessibilityLabel="تحديد متعدد"
            >
              <ThemedText style={[styles.headerActionTextBtnLabel, { color: colors.primary }]}>
                تحديد
              </ThemedText>
            </TouchableOpacity>
          )}
        </HeaderBar>
      ) : (
        <HeaderBar
          title={`المحدد: ${selectedIds.length}`}
          subtitle={
            selectedIds.length === filteredNotifications.length && filteredNotifications.length > 0
              ? 'تم تحديد كل المعروض'
              : 'اضغط على أي إشعار لتحديده'
          }
          showBack={false}
        >
          <View style={styles.headerActionsWrap}>
            <TouchableOpacity
              activeOpacity={0.7}
              onPress={handleToggleSelectAll}
              style={[
                styles.headerActionTextBtn,
                { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F1F5F9' },
              ]}
            >
              <ThemedText style={[styles.headerActionTextBtnLabel, { color: colors.primary }]}>
                {selectedIds.length === filteredNotifications.length && filteredNotifications.length > 0
                  ? 'إلغاء التحديد'
                  : 'تحديد الكل'}
              </ThemedText>
            </TouchableOpacity>

            <TouchableOpacity
              activeOpacity={0.7}
              onPress={handleExitSelectionMode}
              style={[
                styles.headerActionTextBtn,
                { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F1F5F9' },
              ]}
            >
              <ThemedText style={[styles.headerActionTextBtnLabel, { color: colors.text }]}>
                إلغاء
              </ThemedText>
            </TouchableOpacity>
          </View>
        </HeaderBar>
      )}

      {/* شريط الفلاتر والإجراءات السريعة */}
      <View style={styles.filterSection}>
        <View style={styles.filterTabs}>
          <TouchableOpacity
            activeOpacity={0.8}
            onPress={() => {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
              setSelectedFilter('all');
            }}
            style={[
              styles.filterTab,
              selectedFilter === 'all' && [
                styles.filterTabActive,
                { backgroundColor: colors.primary },
              ],
              selectedFilter !== 'all' && {
                backgroundColor: isDark ? 'rgba(255,255,255,0.06)' : '#F1F5F9',
              },
            ]}
          >
            <ThemedText
              style={[
                styles.filterTabText,
                selectedFilter === 'all'
                  ? styles.filterTabTextActive
                  : { color: colors.text },
              ]}
            >
              الكل ({notifications.length})
            </ThemedText>
          </TouchableOpacity>

          <TouchableOpacity
            activeOpacity={0.8}
            onPress={() => {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
              setSelectedFilter('unread');
            }}
            style={[
              styles.filterTab,
              selectedFilter === 'unread' && [
                styles.filterTabActive,
                { backgroundColor: colors.primary },
              ],
              selectedFilter !== 'unread' && {
                backgroundColor: isDark ? 'rgba(255,255,255,0.06)' : '#F1F5F9',
              },
            ]}
          >
            <ThemedText
              style={[
                styles.filterTabText,
                selectedFilter === 'unread'
                  ? styles.filterTabTextActive
                  : { color: colors.text },
              ]}
            >
              غير مقروءة {unreadCount > 0 ? `(${unreadCount})` : ''}
            </ThemedText>
          </TouchableOpacity>

          <TouchableOpacity
            activeOpacity={0.8}
            onPress={() => {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
              setSelectedFilter('media');
            }}
            style={[
              styles.filterTab,
              selectedFilter === 'media' && [
                styles.filterTabActive,
                { backgroundColor: colors.primary },
              ],
              selectedFilter !== 'media' && {
                backgroundColor: isDark ? 'rgba(255,255,255,0.06)' : '#F1F5F9',
              },
            ]}
          >
            <ThemedText
              style={[
                styles.filterTabText,
                selectedFilter === 'media'
                  ? styles.filterTabTextActive
                  : { color: colors.text },
              ]}
            >
              الصور والوسائط {mediaCount > 0 ? `(${mediaCount})` : ''}
            </ThemedText>
          </TouchableOpacity>
        </View>

        {!isSelectionMode && unreadCount > 0 && (
          <TouchableOpacity
            onPress={handleMarkAllAsRead}
            style={[styles.readAllBtn, { borderColor: colors.primary + '33' }]}
            activeOpacity={0.7}
          >
            <IconSymbol name="checkmark.circle.fill" size={14} color={colors.primary} />
            <ThemedText style={[styles.readAllText, { color: colors.primary }]}>
              تحديد الكل كمقروء
            </ThemedText>
          </TouchableOpacity>
        )}
      </View>

      {loading ? (
        <ListSkeleton variant="notification" count={6} />
      ) : (
        <FlatList
          data={filteredNotifications}
          keyExtractor={(item) => item.id}
          style={{ flex: 1 }}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => loadNotifications(true)}
              tintColor={colors.primary}
              colors={[colors.primary]}
            />
          }
          contentContainerStyle={[
            styles.listContent,
            filteredNotifications.length === 0 && styles.listContentEmpty,
            isSelectionMode && styles.listContentInSelection,
          ]}
          showsVerticalScrollIndicator={false}
          ListEmptyComponent={
            <View style={styles.emptyWrap}>
              <View
                style={[
                  styles.emptyIconCircle,
                  { backgroundColor: colors.primary + '18' },
                ]}
              >
                <IconSymbol
                  name={
                    selectedFilter === 'media'
                      ? 'photo.stack.fill'
                      : selectedFilter === 'unread'
                        ? 'checkmark.circle.fill'
                        : 'bell.fill'
                  }
                  size={40}
                  color={colors.primary}
                />
              </View>
              <ThemedText
                type="subtitle"
                style={[styles.emptyTitle, { color: colors.text }]}
              >
                {selectedFilter === 'unread'
                  ? 'لا توجد إشعارات غير مقروءة'
                  : selectedFilter === 'media'
                    ? 'لا توجد وسائط أو صور حالياً'
                    : 'صندوق الإشعارات فارغ'}
              </ThemedText>
              <ThemedText style={[styles.emptyHint, { color: colors.icon }]}>
                {selectedFilter === 'unread'
                  ? 'رائع! لقد قمت بقراءة جميع الإشعارات والتنبيهات.'
                  : selectedFilter === 'media'
                    ? 'ستظهر هنا صور الفعاليات والإعلانات عند إرسالها.'
                    : 'لا توجد إشعارات حالياً. ستظهر هنا التنبيهات والأخبار فور إرسالها.'}
              </ThemedText>
            </View>
          }
          renderItem={({ item }) => (
            <SwipeableNotificationCard
              item={item}
              colors={colors}
              isDark={isDark}
              isExpanded={expandedIds.includes(item.id)}
              isSelectionMode={isSelectionMode}
              isSelected={selectedIds.includes(item.id)}
              onToggleSelect={() => {
                if (!isSelectionMode) {
                  setIsSelectionMode(true);
                  setSelectedIds([item.id]);
                } else {
                  handleToggleSelectNotification(item.id);
                }
              }}
              onToggleExpand={() => handleToggleExpand(item)}
              onExecuteAction={(action) => handleExecuteAction(action, item)}
              onToggleRead={() => toggleReadStatus(item.id)}
              onImagePress={(url) => {
                setLightboxTitle(item.title);
                setLightboxUrl(url);
              }}
              onShare={() => handleShareNotification(item)}
              onDelete={() => handleDeleteNotification(item.id)}
              onInvitationResponse={hasIdentity ? (response) => confirmInvitationResponse(item, response) : undefined}
              invitationResponsePending={pendingResponseKeys.includes(`${invitationViewerId}:${item.invitation?.id}`)}
            />
          )}
        />
      )}

      {/* شريط الإجراءات العائم السفلي عند التحديد المتعدد */}
      {isSelectionMode && (
        <View
          style={[
            styles.floatingSelectionBar,
            {
              backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
              borderColor: isDark ? '#334155' : '#E2E8F0',
            },
          ]}
        >
          <View style={styles.floatingSelectionInfo}>
            <Ionicons name="checkbox" size={20} color={colors.primary} />
            <ThemedText style={[styles.floatingSelectionText, { color: colors.text }]}>
              {selectedIds.length > 0
                ? `تم تحديد ${selectedIds.length} إشعار`
                : 'حدد إشعاراً واحداً على الأقل'}
            </ThemedText>
          </View>

          <TouchableOpacity
            activeOpacity={0.8}
            disabled={selectedIds.length === 0}
            onPress={handlePromptDeleteSelected}
            style={[
              styles.floatingDeleteBtn,
              {
                backgroundColor:
                  selectedIds.length > 0 ? '#DC2626' : (isDark ? '#374151' : '#CBD5E1'),
              },
            ]}
          >
            <Ionicons name="trash-outline" size={17} color="#FFFFFF" />
            <ThemedText style={styles.floatingDeleteBtnText}>
              {selectedIds.length > 0 ? `حذف المحدد (${selectedIds.length})` : 'حذف'}
            </ThemedText>
          </TouchableOpacity>
        </View>
      )}

      {/* عارض الصور المباشر بملء الشاشة مع التكبير */}
      <MediaLightbox
        visible={!!lightboxUrl}
        mediaUrl={lightboxUrl}
        title={lightboxTitle}
        onClose={() => {
          setLightboxUrl(null);
          setLightboxTitle(undefined);
        }}
      />
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    direction: 'rtl',
  },
  headerActionsWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  headerActionIconBtn: {
    width: 36,
    height: 36,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerActionTextBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerActionTextBtnLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
  },
  filterSection: {
    paddingHorizontal: 20,
    marginBottom: 12,
    gap: 10,
  },
  filterTabs: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  filterTab: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 20,
  },
  filterTabActive: {},
  filterTabText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  filterTabTextActive: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
  },
  readAllBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
  readAllText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  listContent: {
    paddingHorizontal: 18,
    paddingBottom: 40,
  },
  listContentEmpty: {
    flexGrow: 1,
  },
  listContentInSelection: {
    paddingBottom: 100,
  },
  emptyWrap: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 48,
    paddingHorizontal: 24,
    gap: 10,
  },
  emptyIconCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 6,
  },
  emptyTitle: {
    textAlign: 'center',
    writingDirection: 'rtl',
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
  },
  emptyHint: {
    textAlign: 'center',
    writingDirection: 'rtl',
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 19,
    maxWidth: 290,
  },
  floatingSelectionBar: {
    position: 'absolute',
    bottom: Platform.OS === 'ios' ? 26 : 16,
    left: 18,
    right: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 18,
    borderWidth: 1,
    ...Platform.select({
      ios: { shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.15, shadowRadius: 12 },
      android: { elevation: 6 },
    }),
  },
  floatingSelectionInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  floatingSelectionText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  floatingDeleteBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 12,
  },
  floatingDeleteBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
  },
});
