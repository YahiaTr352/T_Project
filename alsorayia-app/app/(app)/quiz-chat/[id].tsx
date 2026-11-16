import React, { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  I18nManager,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  StatusBar,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import * as Haptics from 'expo-haptics';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { CustomAlert } from '@/components/ui/AlertModal';
import { ChatMessageBubble } from '@/components/walking/ChatMessageBubble';
import { ChatActionSheetModal, type ChatActionItem } from '@/components/chat/ChatActionSheetModal';
import {
  buildMessageActions,
  resolveIsOwnMessage,
} from '@/components/chat/messageActions';
import { CHAT_REPORT_REASONS, type ChatMessageItem, type SendMessagePayload } from '@/lib/api/chatApi';
import { useAuth } from '@/contexts/auth-context';
import { ImageViewerModal } from '@/components/ui/zoomable-image';
import { VoiceRecordComposer } from '@/components/chat/VoiceRecordComposer';
import { MediaCaptionModal, type PendingMediaAttachment } from '@/components/chat/MediaCaptionModal';
import { getVideoThumbnail } from '@/lib/utils/videoThumbnails';
import * as WebBrowser from 'expo-web-browser';
import { Colors, silaTheme } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { useQuestionChat } from '@/hooks/use-question-chat';

export default function QuizChatScreen() {
  const { id: idParam, title: passedTitle } = useLocalSearchParams<{
    id: string;
    title?: string;
  }>();

  const questionId = Number(idParam);
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];
  const isRTL = I18nManager.isRTL;
  const [headerHeight, setHeaderHeight] = useState(0);
  const { user } = useAuth();

  const {
    messages,
    room,
    loading,
    fetchingOlder,
    sending,
    isMuted,
    loadOlderMessages,
    sendMessage,
    toggleMute,
    deleteMessage,
    editMessage,
    clearChat,
    moderation,
    replyingTo,
    canSend: canSendAllowed,
    replyToMessage,
    cancelReply,
    reportMessage,
  } = useQuestionChat({
    questionId: Number.isFinite(questionId) && questionId > 0 ? questionId : null,
    isActive: true,
  });

  const [inputText, setInputText] = useState('');
  const [selectedMedia, setSelectedMedia] = useState<{
    uri: string;
    type: 'image' | 'video';
    duration?: number;
    thumbnailUri?: string;
  } | null>(null);
  const [pendingMedia, setPendingMedia] = useState<PendingMediaAttachment | null>(null);

  // Modals for previewing media
  const [lightboxImageUrl, setLightboxImageUrl] = useState<string | null>(null);
  const [showRoomOptions, setShowRoomOptions] = useState(false);
  // الرسالة التي تم الضغط عليها مطولاً (تُعرض خياراتها خارج القائمة المعكوسة)
  const [messageOptionsTarget, setMessageOptionsTarget] = useState<ChatMessageItem | null>(null);
  // الرسالة التي يجري الإبلاغ عنها (تُعرض أسباب البلاغ في ورقة سفلية)
  const [reportTarget, setReportTarget] = useState<ChatMessageItem | null>(null);
  // الرسالة التي يجري تعديل نصها (خلال أول 60 دقيقة)
  const [editingMessage, setEditingMessage] = useState<ChatMessageItem | null>(null);
  // نحتفظ بمسودة الكتابة السابقة لاستعادتها عند إلغاء التعديل
  const [draftBeforeEdit, setDraftBeforeEdit] = useState('');

  const displayTitle = room?.title || passedTitle || 'منتدى الحوار والتحدي';

  // بدء تعديل رسالة نصية
  const handleStartEdit = useCallback(
    (msg: ChatMessageItem) => {
      try {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      } catch {}
      setMessageOptionsTarget(null);
      if (replyingTo) cancelReply();
      if (!editingMessage) setDraftBeforeEdit(inputText);
      setSelectedMedia(null);
      setEditingMessage(msg);
      setInputText(msg.message || '');
    },
    [cancelReply, editingMessage, inputText, replyingTo]
  );

  // إلغاء التعديل واستعادة المسودة السابقة
  const handleCancelEdit = useCallback(() => {
    setEditingMessage(null);
    setInputText(draftBeforeEdit);
    setDraftBeforeEdit('');
  }, [draftBeforeEdit]);

  // الرد يلغي التعديل الجاري قبل بدء الاقتباس
  const handleStartReply = useCallback(
    (msg: ChatMessageItem) => {
      if (editingMessage) handleCancelEdit();
      replyToMessage(msg);
    },
    [editingMessage, handleCancelEdit, replyToMessage]
  );

  // مسح المحادثة من شاشة المستخدم ("مسح الشات من عندي")
  const handleClearChat = useCallback(() => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}

    CustomAlert.alert(
      'مسح سجل المحادثة',
      'هل أنت متأكد من مسح جميع الرسائل من شاشتك؟\n(لن يتم مسح الرسائل لدى باقي المشاركين في المسابقة).',
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'مسح من عندي',
          style: 'destructive',
          icon: 'trash-outline',
          onPress: async () => {
            const res = await clearChat();
            if (!res.success) {
              CustomAlert.alert('خطأ', res.message || 'تعذر مسح المحادثة');
            } else {
              handleCancelEdit();
              try {
                void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
              } catch {}
            }
          },
        },
      ]
    );
  }, [clearChat, handleCancelEdit]);

  // قائمة خيارات الغرفة الذكية بدون أي إيموجي ومعززة بأيقونات Expo Vector Icons
  const roomActions: ChatActionItem[] = useMemo(
    () => [
      {
        id: 'toggle_mute',
        label: isMuted ? 'تفعيل صوت التنبيهات' : 'كتم صوت التنبيهات',
        sublabel: isMuted
          ? 'استلام تنبيهات الرسائل الجديدة مع الصوت'
          : 'إيقاف صوت تنبيهات الرسائل الجديدة في هذا النقاش',
        icon: isMuted ? 'notifications-outline' : 'notifications-off-outline',
        iconColor: isMuted ? silaTheme.primary : '#F59E0B',
        onPress: () => {
          toggleMute();
        },
      },
      {
        id: 'clear_chat',
        label: 'مسح سجل المحادثة (من عندي)',
        sublabel: 'إفراغ الرسائل السابقة على هذا الجهاز فقط',
        icon: 'trash-outline',
        variant: 'destructive',
        onPress: () => {
          handleClearChat();
        },
      },
    ],
    [isMuted, toggleMute, handleClearChat]
  );

  // فتح ورقة خيارات الغرفة الذكية
  const handleOpenOptionsMenu = useCallback(() => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}
    setShowRoomOptions(true);
  }, []);

  // حذف رسالة (لدي أو لدى الجميع)
  const handleDeleteMessage = useCallback(
    async (msg: any, mode: 'me' | 'everyone') => {
      const res = await deleteMessage(msg.id, mode);
      if (!res.success) {
        CustomAlert.alert('خطأ', res.message || 'تعذر حذف الرسالة');
      }
    },
    [deleteMessage]
  );

  // بدء الإبلاغ عن رسالة (تُعرض الأسباب في ورقة سفلية متوافقة مع نمط التطبيق)
  const handleReportMessage = useCallback((msg: ChatMessageItem) => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}
    setMessageOptionsTarget(null);
    setReportTarget(msg);
  }, []);

  // خيارات الرسالة المحددة (رد / نسخ / إبلاغ / مسح من عندي / مسح لدى الجميع)
  const messageActionItems: ChatActionItem[] = useMemo(() => {
    if (!messageOptionsTarget) return [];
    return buildMessageActions({
      message: messageOptionsTarget,
      isMe: resolveIsOwnMessage(messageOptionsTarget, user?.id),
      canSend: canSendAllowed,
      onDeletePress: handleDeleteMessage,
      onReplyPress: handleStartReply,
      onReportPress: handleReportMessage,
      onEditPress: handleStartEdit,
    });
  }, [
    messageOptionsTarget,
    user?.id,
    handleDeleteMessage,
    canSendAllowed,
    handleStartReply,
    handleReportMessage,
    handleStartEdit,
  ]);

  // أسباب الإبلاغ المتاحة (spam / abuse / inappropriate / personal_info / impersonation / other)
  const reportActionItems: ChatActionItem[] = useMemo(
    () =>
      CHAT_REPORT_REASONS.map((reason) => ({
        id: `report_${reason.key}`,
        label: reason.label,
        icon: 'flag-outline' as const,
        iconColor: '#E5484D',
        onPress: async () => {
          const target = reportTarget;
          setReportTarget(null);
          if (!target) return;

          const res = await reportMessage(target.id, reason.key);
          if (res.success) {
            CustomAlert.alert('تم إرسال البلاغ', 'شكراً لك، سيقوم فريق الإشراف بمراجعة الرسالة.');
          } else {
            CustomAlert.alert('خطأ', res.message || 'تعذر إرسال البلاغ');
          }
        },
      })),
    [reportTarget, reportMessage]
  );

  // رسالة تنبيه الإشراف الظاهرة أعلى شريط الإدخال (قفل / حظر / وضع بطيء)
  const moderationNotice = useMemo(() => {
    if (!moderation) return null;

    if (moderation.is_locked) {
      return {
        icon: 'lock-closed-outline' as const,
        text: moderation.lock_reason
          ? `المحادثة مقفلة مؤقتاً: ${moderation.lock_reason}`
          : 'المحادثة مقفلة مؤقتاً من قِبل الإشراف.',
      };
    }

    if (moderation.can_send === false) {
      return {
        icon: 'ban-outline' as const,
        text: moderation.send_blocked_message || 'لا يمكنك الإرسال في هذه المحادثة حالياً.',
      };
    }

    if ((moderation.slow_mode_seconds ?? 0) > 0) {
      return {
        icon: 'time-outline' as const,
        text: `الوضع البطيء مفعّل: يمكنك إرسال رسالة كل ${moderation.slow_mode_seconds} ثانية.`,
      };
    }

    return null;
  }, [moderation]);

  const allowAttachments = moderation ? moderation.allow_attachments !== false : true;

  // ── التقاط أو اختيار وسائط (صور وفيديوهات حتى 30 ثانية) ────────
  const handlePickMedia = useCallback(async () => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

      // 1. تصوير فيديو (30 ثانية)
      const recordVideoCamera = async () => {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن الكاميرا مطلوب', 'يرجى السماح بالوصول للكاميرا لتسجيل مقطع فيديو.');
          return;
        }
        const res = await ImagePicker.launchCameraAsync({
          mediaTypes: ['videos'],
          videoMaxDuration: 30,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          const asset = res.assets[0];
          const dur = asset.duration ? asset.duration / 1000 : 30;
          if (dur > 30.5) {
            CustomAlert.alert('تنبيه', 'مدة الفيديو لا يجب أن تتجاوز 30 ثانية.');
            return;
          }
          let thumb: string | null = null;
          try {
            thumb = await getVideoThumbnail(asset.uri);
          } catch {}
          const mediaObj = { uri: asset.uri, type: 'video' as const, duration: dur, thumbnailUri: thumb || undefined };
          setSelectedMedia(mediaObj);
          setPendingMedia(mediaObj);
        }
      };

      // 2. اختيار فيديو من المعرض (فحص <= 30.5s)
      const pickVideoGallery = async () => {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن المعرض مطلوب', 'يرجى السماح بالوصول إلى المعرض لاختيار الفيديو.');
          return;
        }
        const res = await ImagePicker.launchImageLibraryAsync({
          mediaTypes: ['videos'],
          allowsEditing: false,
          quality: 0.8,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          const asset = res.assets[0];
          const dur = asset.duration ? asset.duration / 1000 : 0;
          if (dur > 30.5) {
            CustomAlert.alert(
              'الفيديو طويل جداً',
              `مدة الفيديو المختار ${Math.round(dur)} ثانية. يُشترط ألا تزيد مدة الفيديو عن 30 ثانية لتقليل حجم التحميل.`
            );
            return;
          }
          let thumb: string | null = null;
          try {
            thumb = await getVideoThumbnail(asset.uri);
          } catch {}
          const mediaObj = { uri: asset.uri, type: 'video' as const, duration: dur || undefined, thumbnailUri: thumb || undefined };
          setSelectedMedia(mediaObj);
          setPendingMedia(mediaObj);
        }
      };

      // 3. التقاط صورة
      const openCamera = async () => {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن الكاميرا مطلوب', 'يرجى السماح بالوصول للكاميرا لالتقاط صورة.');
          return;
        }
        const res = await ImagePicker.launchCameraAsync({
          mediaTypes: ['images'],
          allowsEditing: false,
          quality: 0.8,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          const mediaObj = { uri: res.assets[0].uri, type: 'image' as const };
          setSelectedMedia(mediaObj);
          setPendingMedia(mediaObj);
        }
      };

      // 4. اختيار صورة من المعرض
      const openGallery = async () => {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          CustomAlert.alert('إذن المعرض مطلوب', 'يرجى السماح بالوصول للصور.');
          return;
        }
        const res = await ImagePicker.launchImageLibraryAsync({
          mediaTypes: ['images'],
          allowsEditing: false,
          quality: 0.8,
        });
        if (!res.canceled && res.assets?.[0]?.uri) {
          const mediaObj = { uri: res.assets[0].uri, type: 'image' as const };
          setSelectedMedia(mediaObj);
          setPendingMedia(mediaObj);
        }
      };

      CustomAlert.alert(
        'إرفاق وسائط بالمحادثة',
        'اختر نوع الوسائط التي ترغب بمشاركتها (الفيديو بحد أقصى 30 ثانية):',
        [
          { text: 'إلغاء', style: 'cancel' },
          { text: '🎥 تسجيل فيديو (30ث)', onPress: () => void recordVideoCamera() },
          { text: '🎞️ فيديو من المعرض (30ث)', onPress: () => void pickVideoGallery() },
          { text: '📷 التقاط صورة', onPress: () => void openCamera() },
          { text: '🖼️ صورة من المعرض', onPress: () => void openGallery() },
        ]
      );
    } catch {
      CustomAlert.alert('تنبيه', 'تعذر فتح الوسائط.');
    }
  }, []);

  // ── إرسال الرسالة ─────────────────────────────────────────────
  const handleSend = useCallback(async () => {
    const text = inputText.trim();
    if (!text && !selectedMedia) return;

    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}

    if (editingMessage) {
      if (!text) return;
      setInputText('');
      const res = await editMessage(editingMessage.id, text);
      if (!res.success) {
        CustomAlert.alert('خطأ', res.message || 'تعذر تعديل الرسالة');
        setInputText(text);
        return;
      }
      setEditingMessage(null);
      setDraftBeforeEdit('');
      return;
    }

    const payload = {
      message: text || undefined,
      attachment_uri: selectedMedia?.uri || undefined,
      type: (selectedMedia ? selectedMedia.type : 'text') as 'text' | 'image' | 'video',
      duration: selectedMedia?.duration,
    };

    setInputText('');
    setSelectedMedia(null);

    const res = await sendMessage(payload);
    if (!res || !res.success) {
      CustomAlert.alert('خطأ', res?.message || 'فشل إرسال الرسالة');
      // لا نُعيد النص للحقل عند المنع الدائم (حظر/قفل/تقييد) حتى لا يوهم المستخدم بإمكانية الإرسال
      const isPermanentBlock = Boolean(res?.reason) && res.reason !== 'slow_mode';
      if (!isPermanentBlock) {
        if (text) setInputText(text);
        if (payload.attachment_uri && selectedMedia) setSelectedMedia(selectedMedia);
      }
    }
  }, [inputText, selectedMedia, sendMessage, editMessage, editingMessage]);

  // إرسال وسائط (صورة أو فيديو) مع الشرح والوصف بنمط واتساب
  const handleSendMediaWithCaption = useCallback(
    async (media: PendingMediaAttachment, captionText: string) => {
      if (!canSendAllowed || sending) return;

      const payload = {
        message: captionText || undefined,
        attachment_uri: media.uri,
        type: media.type,
        duration: media.duration,
      };

      setPendingMedia(null);
      setSelectedMedia(null);
      setInputText('');

      const res = await sendMessage(payload);
      if (!res || !res.success) {
        CustomAlert.alert('خطأ', res?.message || 'فشل إرسال الوسائط');
      }
    },
    [canSendAllowed, sending, sendMessage]
  );

  // إرسال رسالة صوتية (واتساب)
  const handleSendAudio = useCallback(
    async ({ uri, duration, waveform }: { uri: string; duration: number; waveform: number[] }) => {
      if (!canSendAllowed || sending) return;

      const payload: SendMessagePayload = {
        attachment_uri: uri,
        type: 'audio',
        duration,
        metadata: {
          duration,
          waveform,
        },
      };

      const res = await sendMessage(payload);
      if (!res?.success) {
        CustomAlert.alert('خطأ', res?.message || 'فشل إرسال الرسالة الصوتية');
      }
    },
    [canSendAllowed, sending, sendMessage]
  );

  const canSend = Boolean(inputText.trim() || selectedMedia) && !sending && canSendAllowed;

  return (
    <ThemedView style={styles.container}>
      <StatusBar
        barStyle={isDark ? 'light-content' : 'dark-content'}
        backgroundColor={colors.background}
        translucent={false}
      />

      {/* الرأس المستقل للمحادثة (Chat Header) */}
      <View
        onLayout={(e) => {
          const h = e.nativeEvent.layout.height;
          if (h > 0) setHeaderHeight(Math.round(h));
        }}
        style={[
          styles.header,
          {
            backgroundColor: isDark ? '#18201C' : '#FFFFFF',
            borderBottomColor: isDark ? '#273831' : silaTheme.border,
            paddingTop: Math.max(insets.top, 12),
          },
        ]}>
        {/* زر الرجوع */}
        <TouchableOpacity
          onPress={() => {
            Keyboard.dismiss();
            router.back();
          }}
          activeOpacity={0.7}
          style={styles.backBtn}>
          <Ionicons
            name={isRTL ? 'chevron-forward' : 'chevron-back'}
            size={24}
            color={colors.text}
          />
        </TouchableOpacity>

        {/* أيقونة ومعلومات الغرفة */}
        <View style={styles.headerInfo}>
          <View
            style={[
              styles.roomAvatar,
              { backgroundColor: isDark ? '#273C33' : '#DCF2DD' },
            ]}>
            <Ionicons name="chatbubbles" size={18} color={silaTheme.primary} />
          </View>
          <View style={styles.headerTextWrap}>
            <ThemedText style={styles.headerTitle} numberOfLines={1}>
              {displayTitle}
            </ThemedText>
            <View style={styles.headerSubRow}>
              <View style={styles.onlineDot} />
              <ThemedText style={[styles.headerSubtitle, { color: colors.icon }]}>
                {room?.messages_count
                  ? `${room.messages_count} رسالة • محادثة مباشرة`
                  : 'محادثة السؤال والاستطلاع'}
              </ThemedText>
            </View>
          </View>
        </View>

        {/* أزرار الإجراءات في الهيدر */}
        <View style={styles.headerActions}>
          {/* زر كتم / تفعيل الإشعارات السريع */}
          <TouchableOpacity
            activeOpacity={0.7}
            onPress={() => {
              try {
                void Haptics.selectionAsync();
              } catch {}
              toggleMute();
            }}
            style={[
              styles.actionBtn,
              {
                backgroundColor: isMuted
                  ? isDark
                    ? 'rgba(239, 68, 68, 0.2)'
                    : '#FEE2E2'
                  : isDark
                    ? '#24322B'
                    : '#E8F5EC',
                borderColor: isMuted
                  ? isDark
                    ? '#7F1D1D'
                    : '#FECACA'
                  : isDark
                    ? '#2F433A'
                    : '#CBE5D3',
              },
            ]}
            accessibilityRole="button"
            accessibilityLabel={isMuted ? 'إلغاء كتم التنبيهات' : 'كتم التنبيهات'}>
            <Ionicons
              name={isMuted ? 'notifications-off' : 'notifications-outline'}
              size={16}
              color={isMuted ? '#EF4444' : silaTheme.primary}
            />
          </TouchableOpacity>

          {/* زر قائمة الخيارات الثلاثية الذكية (More Options) */}
          <TouchableOpacity
            activeOpacity={0.7}
            onPress={handleOpenOptionsMenu}
            style={[
              styles.actionBtn,
              {
                backgroundColor: isDark ? '#24322B' : '#F1F5F9',
                borderColor: isDark ? '#2F433A' : '#E2E8F0',
              },
            ]}
            accessibilityRole="button"
            accessibilityLabel="خيارات المحادثة">
            <Ionicons name="ellipsis-vertical" size={17} color={colors.text} />
          </TouchableOpacity>
        </View>
      </View>

      {/* معالجة الكيبورد عبر KeyboardAvoidingView */}
      <KeyboardAvoidingView
        style={styles.keyboardAvoid}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={headerHeight}>
        <View style={styles.chatArea}>
          {/* شريط الرسالة المثبتة من الإشراف */}
          {room?.pinned_message && (
            <View
              style={[
                styles.pinnedBanner,
                {
                  backgroundColor: isDark ? '#18201C' : '#FFFFFF',
                  borderBottomColor: isDark ? '#273831' : silaTheme.border,
                },
              ]}>
              <Ionicons name="pin" size={14} color={silaTheme.primary} />
              <View style={styles.pinnedBody}>
                <ThemedText style={[styles.pinnedTitle, { color: silaTheme.primary }]}>
                  رسالة مثبتة من الإشراف
                </ThemedText>
                <ThemedText numberOfLines={1} style={[styles.pinnedText, { color: colors.icon }]}>
                  {room.pinned_message.message ||
                    (room.pinned_message.type === 'video' ? '🎥 مقطع فيديو' : '📷 صورة')}
                </ThemedText>
              </View>
            </View>
          )}

          {loading ? (
            <View style={styles.loadingContainer}>
              <ActivityIndicator size="large" color={silaTheme.primary} />
              <ThemedText style={styles.loadingText}>جارٍ تحميل المحادثة...</ThemedText>
            </View>
          ) : (
            <FlatList
              data={messages}
              keyExtractor={(item) => String(item.id)}
              inverted
              contentContainerStyle={styles.listContent}
              renderItem={({ item }) => (
                <ChatMessageBubble
                  message={item}
                  isDark={isDark}
                  onImagePress={(imgUrl) => setLightboxImageUrl(imgUrl)}
                  onLongPress={(msg) => setMessageOptionsTarget(msg)}
                  onVideoPress={(vidUrl) => {
                    if (vidUrl) {
                      try {
                        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                      } catch {}
                      void WebBrowser.openBrowserAsync(vidUrl).catch((err) => {
                        console.error('[QuizChat] Error playing video:', err);
                      });
                    }
                  }}
                />
              )}
              onEndReached={() => {
                if (!fetchingOlder) {
                  loadOlderMessages();
                }
              }}
              onEndReachedThreshold={0.3}
              ListFooterComponent={
                fetchingOlder ? (
                  <View style={styles.olderLoading}>
                    <ActivityIndicator size="small" color={silaTheme.primary} />
                  </View>
                ) : null
              }
              ListEmptyComponent={
                <View style={styles.emptyContainer}>
                  <View style={styles.emptyIconBox}>
                    <Ionicons name="chatbubbles-outline" size={40} color={silaTheme.primary} />
                  </View>
                  <ThemedText style={styles.emptyTitle}>لا توجد رسائل بعد</ThemedText>
                  <ThemedText style={styles.emptySubtitle}>
                    كن أول من يشارك في هذا النقاش وشارك رأيك أو صورك أو مقاطع الفيديو (حتى 30 ثانية).
                  </ThemedText>
                </View>
              }
            />
          )}

          {/* معاينة الوسائط المحددة قبل الإرسال */}
          {selectedMedia && (
            <View
              style={[
                styles.selectedMediaPreview,
                {
                  backgroundColor: isDark ? '#1C2721' : '#F0FDF4',
                  borderColor: isDark ? '#2D4336' : '#BBF7D0',
                },
              ]}>
              <View style={styles.mediaThumbWrap}>
                {selectedMedia.type === 'image' || selectedMedia.thumbnailUri ? (
                  <Image source={{ uri: selectedMedia.thumbnailUri || selectedMedia.uri }} style={styles.previewThumb} />
                ) : (
                  <View style={styles.previewVideoPlaceholder}>
                    <Ionicons name="videocam" size={20} color="#FFFFFF" />
                  </View>
                )}
                {selectedMedia.type === 'video' && (
                  <View style={styles.previewDurationBadge}>
                    <ThemedText style={styles.previewDurationText}>
                      {selectedMedia.duration
                        ? `${Math.round(selectedMedia.duration)}ث`
                        : 'فيديو'}
                    </ThemedText>
                  </View>
                )}
              </View>

              <View style={styles.mediaInfoWrap}>
                <ThemedText style={styles.mediaInfoTitle}>
                  {selectedMedia.type === 'video' ? 'مقطع فيديو محدد' : 'صورة محددة'}
                </ThemedText>
                <ThemedText style={styles.mediaInfoSub}>جاهز للإرسال مع رسالتك</ThemedText>
              </View>

              <TouchableOpacity
                onPress={() => setSelectedMedia(null)}
                style={styles.removeMediaBtn}
                hitSlop={8}>
                <Ionicons name="close-circle" size={22} color="#EF4444" />
              </TouchableOpacity>
            </View>
          )}

          {/* شريط التعديل + الرد + تنبيه الإشراف أعلى شريط الإدخال */}
          {(editingMessage || replyingTo || moderationNotice) && (
            <View
              style={[
                styles.composerNotices,
                {
                  backgroundColor: isDark ? '#18201C' : '#FFFFFF',
                  borderTopColor: isDark ? '#273831' : silaTheme.border,
                },
              ]}>
              {editingMessage && (
                <View
                  style={[
                    styles.replyBar,
                    {
                      borderRightColor: '#D97706',
                      backgroundColor: isDark ? 'rgba(217,119,6,0.14)' : '#FEF3C7',
                      borderColor: isDark ? 'rgba(217,119,6,0.35)' : '#FCD34D',
                    },
                  ]}>
                  <TouchableOpacity
                    onPress={handleCancelEdit}
                    style={styles.replyBarCancel}
                    activeOpacity={0.7}
                    accessibilityRole="button"
                    accessibilityLabel="إلغاء التعديل">
                    <Ionicons name="close" size={16} color={colors.icon} />
                  </TouchableOpacity>
                  <View style={styles.replyBarBody}>
                    <ThemedText style={[styles.replyBarSender, { color: isDark ? '#FCD34D' : '#B45309' }]}>
                      تعديل الرسالة
                    </ThemedText>
                    <ThemedText
                      numberOfLines={1}
                      style={[styles.replyBarPreview, { color: colors.icon }]}>
                      {editingMessage.message || ''}
                    </ThemedText>
                  </View>
                </View>
              )}

              {replyingTo && (
                <View
                  style={[
                    styles.replyBar,
                    {
                      borderRightColor: silaTheme.primary,
                      backgroundColor: isDark ? 'rgba(55,160,92,0.12)' : '#EEF7F0',
                      borderColor: isDark ? 'rgba(55,160,92,0.25)' : '#D5EBD7',
                    },
                  ]}>
                  <TouchableOpacity
                    onPress={cancelReply}
                    style={styles.replyBarCancel}
                    activeOpacity={0.7}
                    accessibilityRole="button"
                    accessibilityLabel="إلغاء الرد">
                    <Ionicons name="close" size={16} color={colors.icon} />
                  </TouchableOpacity>
                  <View style={styles.replyBarBody}>
                    <ThemedText style={[styles.replyBarSender, { color: silaTheme.primary }]}>
                      {replyingTo.sender?.name || 'عضو في التحدي'}
                    </ThemedText>
                    <ThemedText
                      numberOfLines={1}
                      style={[styles.replyBarPreview, { color: colors.icon }]}>
                      {replyingTo.message ||
                        (replyingTo.type === 'video' ? '🎥 مقطع فيديو' : '📷 صورة')}
                    </ThemedText>
                  </View>
                </View>
              )}

              {moderationNotice && (
                <View
                  style={[
                    styles.moderationBanner,
                    {
                      backgroundColor: isDark ? 'rgba(239,68,68,0.14)' : '#FEF2F2',
                      borderColor: isDark ? 'rgba(239,68,68,0.35)' : '#FECACA',
                    },
                  ]}>
                  <Ionicons name={moderationNotice.icon} size={16} color="#EF4444" />
                  <ThemedText
                    style={[styles.moderationText, { color: isDark ? '#FCA5A5' : '#B91C1C' }]}>
                    {moderationNotice.text}
                  </ThemedText>
                </View>
              )}
            </View>
          )}

          {/* شريط الإدخال السفلي المطور مع دعم الرسائل الصوتية (WhatsApp Voice Record & Composer) */}
          <View
            style={[
              styles.inputBar,
              {
                backgroundColor: isDark ? '#18201C' : '#FFFFFF',
                borderTopColor: isDark ? '#273831' : silaTheme.border,
                paddingBottom: Math.max(insets.bottom, 12),
              },
            ]}>
            <VoiceRecordComposer
              inputText={inputText}
              onChangeText={setInputText}
              onSendText={handleSend}
              onSendAudio={handleSendAudio}
              placeholder={editingMessage ? 'عدّل نص رسالتك...' : 'اكتب رسالة...'}
              isDark={isDark}
              disabled={!canSendAllowed}
              sending={sending}
              onPickImage={handlePickMedia}
              allowAttachments={allowAttachments}
              editingMessage={Boolean(editingMessage)}
              hasAttachment={Boolean(selectedMedia)}
            />
          </View>
        </View>
      </KeyboardAvoidingView>

      {/* شاشة معاينة الوسائط وإضافة الشرح والوصف بنمط واتساب */}
      <MediaCaptionModal
        visible={Boolean(pendingMedia)}
        media={pendingMedia}
        initialCaption={inputText}
        sending={sending}
        onClose={() => {
          setPendingMedia(null);
          setSelectedMedia(null);
        }}
        onSend={handleSendMediaWithCaption}
      />

      {/* عارض الصور المتقدم المعتمد مع دعم الزوم الكامل والسحب والحفظ */}
      <ImageViewerModal
        visible={Boolean(lightboxImageUrl)}
        images={lightboxImageUrl ? [{ uri: lightboxImageUrl }] : []}
        onClose={() => setLightboxImageUrl(null)}
      />

      {/* ورقة خيارات الغرفة المنبثقة من الأسفل بأيقونات متجهة وبدون إيموجي */}
      <ChatActionSheetModal
        visible={showRoomOptions}
        title={displayTitle}
        subtitle={
          room?.messages_count
            ? `${room.messages_count} رسالة في النقاش • خيارات المحادثة`
            : 'خيارات وإعدادات المحادثة'
        }
        actions={roomActions}
        onClose={() => setShowRoomOptions(false)}
      />

      {/* ورقة خيارات الرسالة المنبثقة من الأسفل (خارج القائمة المعكوسة لضمان ظهورها بشكل صحيح) */}
      <ChatActionSheetModal
        visible={Boolean(messageOptionsTarget)}
        title="خيارات الرسالة"
        subtitle={
          messageOptionsTarget?.sender?.name
            ? `مرسلة بواسطة: ${messageOptionsTarget.sender.name}`
            : undefined
        }
        actions={messageActionItems}
        onClose={() => setMessageOptionsTarget(null)}
      />

      {/* ورقة أسباب الإبلاغ عن الرسالة */}
      <ChatActionSheetModal
        visible={Boolean(reportTarget)}
        title="الإبلاغ عن الرسالة"
        subtitle={
          reportTarget?.sender?.name
            ? `مرسلة بواسطة: ${reportTarget.sender.name}`
            : 'اختر سبب البلاغ لفريق الإشراف'
        }
        actions={reportActionItems}
        onClose={() => setReportTarget(null)}
      />
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingBottom: 12,
    borderBottomWidth: 1,
    gap: 12,
  },
  backBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  roomAvatar: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTextWrap: {
    flex: 1,
  },
  headerTitle: {
    fontSize: 15,
    fontWeight: '700',
  },
  headerSubRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 2,
  },
  onlineDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: silaTheme.primary,
  },
  headerSubtitle: {
    fontSize: 11,
    fontWeight: '500',
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  actionBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  muteBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  keyboardAvoid: {
    flex: 1,
  },
  chatArea: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  loadingText: {
    fontSize: 13,
    color: '#64748B',
  },
  listContent: {
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  olderLoading: {
    paddingVertical: 10,
    alignItems: 'center',
  },
  emptyContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    paddingHorizontal: 32,
    transform: [{ scaleY: -1 }], // Inverted because list is inverted
  },
  emptyIconBox: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: '#DCF2DD',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '700',
    marginBottom: 6,
    textAlign: 'center',
  },
  emptySubtitle: {
    fontSize: 13,
    color: '#64748B',
    textAlign: 'center',
    lineHeight: 18,
  },
  selectedMediaPreview: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderTopWidth: 1,
    gap: 12,
  },
  mediaThumbWrap: {
    position: 'relative',
    width: 44,
    height: 44,
    borderRadius: 8,
    overflow: 'hidden',
  },
  previewThumb: {
    width: '100%',
    height: '100%',
  },
  previewVideoPlaceholder: {
    width: '100%',
    height: '100%',
    backgroundColor: silaTheme.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  previewDurationBadge: {
    position: 'absolute',
    bottom: 2,
    right: 2,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    borderRadius: 4,
    paddingHorizontal: 3,
    paddingVertical: 1,
  },
  previewDurationText: {
    color: '#FFFFFF',
    fontSize: 8,
    fontWeight: '700',
  },
  mediaInfoWrap: {
    flex: 1,
  },
  mediaInfoTitle: {
    fontSize: 12,
    fontWeight: '700',
  },
  mediaInfoSub: {
    fontSize: 11,
    color: '#64748B',
  },
  removeMediaBtn: {
    padding: 4,
  },
  composerNotices: {
    paddingHorizontal: 12,
    paddingTop: 8,
    borderTopWidth: 1,
  },
  replyBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    direction: 'rtl',
    borderRightWidth: 3,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 7,
    marginBottom: 8,
  },
  replyBarBody: {
    flex: 1,
  },
  replyBarSender: {
    fontSize: 12,
    fontWeight: '700',
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  replyBarPreview: {
    fontSize: 12,
    textAlign: 'right',
    writingDirection: 'rtl',
    marginTop: 1,
  },
  replyBarCancel: {
    width: 26,
    height: 26,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
  },
  moderationBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    direction: 'rtl',
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    marginBottom: 8,
  },
  moderationText: {
    flex: 1,
    fontSize: 12,
    textAlign: 'right',
    writingDirection: 'rtl',
    lineHeight: 18,
  },
  pinnedBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    direction: 'rtl',
    borderBottomWidth: StyleSheet.hairlineWidth,
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  pinnedBody: {
    flex: 1,
  },
  pinnedTitle: {
    fontSize: 11,
    fontWeight: '700',
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  pinnedText: {
    fontSize: 12,
    textAlign: 'right',
    writingDirection: 'rtl',
    marginTop: 1,
  },
  inputBar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    gap: 8,
  },
  attachBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  inputWrapper: {
    flex: 1,
    minHeight: 40,
    maxHeight: 100,
    borderRadius: 20,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 6,
    justifyContent: 'center',
  },
  input: {
    fontSize: 14,
    lineHeight: 18,
    padding: 0,
    margin: 0,
  },
  sendBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  lightboxOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.92)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  lightboxBackdrop: {
    ...StyleSheet.absoluteFill,
  },
  lightboxCloseBtn: {
    position: 'absolute',
    top: 50,
    left: 20,
    zIndex: 10,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  lightboxImage: {
    width: '92%',
    height: '80%',
  },
});
