import React, { useCallback, useMemo } from 'react';
import { View, StyleSheet, TouchableOpacity, ActivityIndicator, Platform } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as WebBrowser from 'expo-web-browser';
import * as Haptics from 'expo-haptics';
import { silaTheme } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import type { ChatMessageItem } from '@/lib/api/chatApi';
import { useVideoThumbnail } from '@/lib/utils/videoThumbnails';
import { VoiceMessagePlayer } from '@/components/chat/VoiceMessagePlayer';

interface ChatMessageBubbleProps {
  message: ChatMessageItem;
  isDark: boolean;
  onImagePress?: (url: string) => void;
  onVideoPress?: (url: string, duration?: number, senderName?: string) => void;
  /**
   * الضغط المطوّل يُبلَّغ للصفحة الأم (خارج القائمة المعكوسة)
   * لأن تركيب الـ Modal داخل القائمة المنعكسة يقلب الورقة رأساً على عقب
   */
  onLongPress?: (message: ChatMessageItem) => void;
}

/**
 * مكون فرعي ذكي لمعاينة الفيديو يدعم توليد الصور المصغرة على iOS وAndroid
 * مع تصميم بديل فخم في حال لم تكن الصورة جاهزة بعد
 */
function VideoPreviewItem({
  videoUrl,
  serverThumbnail,
  formattedDuration,
  onPress,
}: {
  videoUrl: string;
  serverThumbnail?: string | null;
  formattedDuration?: string | null;
  onPress: () => void;
}) {
  const { thumbnailUri, loading } = useVideoThumbnail(videoUrl, serverThumbnail);

  return (
    <TouchableOpacity
      activeOpacity={0.9}
      onPress={onPress}
      style={styles.videoPreviewWrapper}
      accessibilityRole="button"
      accessibilityLabel="تشغيل مقطع الفيديو">
      {thumbnailUri ? (
        <Image
          source={{ uri: thumbnailUri }}
          style={styles.videoThumbnail}
          contentFit="cover"
          transition={220}
        />
      ) : (
        <LinearGradient
          colors={['#1E293B', '#0F172A']}
          style={styles.videoPlaceholderGradient}>
          {loading ? (
            <ActivityIndicator size="small" color="#10B981" />
          ) : (
            <Ionicons name="videocam-outline" size={28} color="rgba(255,255,255,0.45)" />
          )}
        </LinearGradient>
      )}

      {/* طبقة التعتيم الخفيفة لإبراز زر التشغيل */}
      <View style={styles.videoDarkOverlay} />

      {/* زر التشغيل المركزي */}
      <View style={styles.videoCenterPlayBtn}>
        <Ionicons name="play" size={22} color="#FFFFFF" style={{ marginLeft: 2 }} />
      </View>

      {/* شارة مدة الفيديو */}
      {formattedDuration ? (
        <View style={styles.videoDurationBadge}>
          <Ionicons name="play" size={9} color="#FFFFFF" />
          <ThemedText style={styles.videoDurationBadgeText}>
            {formattedDuration}
          </ThemedText>
        </View>
      ) : (
        <View style={styles.videoDurationBadge}>
          <Ionicons name="videocam" size={10} color="#FFFFFF" />
          <ThemedText style={styles.videoDurationBadgeText}>فيديو</ThemedText>
        </View>
      )}
    </TouchableOpacity>
  );
}

export const ChatMessageBubble = React.memo(function ChatMessageBubble({
  message,
  isDark,
  onImagePress,
  onVideoPress,
  onLongPress,
}: ChatMessageBubbleProps) {
  const { user } = useAuth();
  const currentUserId = user?.id;

  // التحقق القاطع من هوية صاحب الرسالة
  const isMe = Boolean(
    message.is_me ||
    (currentUserId && Number(message.sender?.id) === Number(currentUserId))
  );

  // حساب وتنسيق مدة الفيديو بنمط احترافي ومختصر (مثال: 0:12)
  const formattedDuration = useMemo(() => {
    const dur = message.metadata?.duration;
    if (!dur || dur <= 0) return null;
    const totalSecs = Math.floor(dur);
    const mins = Math.floor(totalSecs / 60);
    const secs = totalSecs % 60;
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }, [message.metadata?.duration]);

  // تشغيل الفيديو بشكل مباشر وفوري
  const handleVideoPress = useCallback(async () => {
    if (!message.attachment_url) return;
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}

    if (onVideoPress) {
      onVideoPress(message.attachment_url, message.metadata?.duration, message.sender?.name);
    } else {
      try {
        await WebBrowser.openBrowserAsync(message.attachment_url);
      } catch (err) {
        console.error('[ChatMessageBubble] Error opening video directly:', err);
      }
    }
  }, [onVideoPress, message.attachment_url, message.metadata?.duration, message.sender?.name]);

  // الضغط المطوّل الذكي لعرض ورقة الخيارات (تُعرض من خارج القائمة المعكوسة)
  const handleLongPress = useCallback(() => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    onLongPress?.(message);
  }, [onLongPress, message]);


  const isSystem = message.type === 'system';

  if (isSystem) {
    return (
      <View style={styles.systemContainer}>
        <View
          style={[
            styles.systemPill,
            {
              backgroundColor: isDark ? 'rgba(55, 160, 92, 0.15)' : '#EDF7EE',
              borderColor: isDark ? 'rgba(55, 160, 92, 0.3)' : '#D5EBD7',
            },
          ]}>
          <ThemedText
            style={[
              styles.systemText,
              { color: isDark ? '#A7F3D0' : '#2D6A4F' },
            ]}>
            {message.message}
          </ThemedText>
        </View>
      </View>
    );
  }

  const senderInitial =
    message.sender?.first_name?.charAt(0) ||
    message.sender?.name?.charAt(0) ||
    '؟';

  return (
    <View style={[styles.row, isMe ? styles.rowMe : styles.rowOther]}>
      {/* صورة العضو تظهر فقط لرسائل الآخرين */}
      {!isMe && (
        <View style={styles.avatarWrapper}>
          {message.sender?.avatar_url ? (
            <Image
              source={{ uri: message.sender.avatar_url }}
              style={styles.avatar}
              contentFit="cover"
              transition={200}
            />
          ) : (
            <View
              style={[
                styles.avatarPlaceholder,
                {
                  backgroundColor: isDark ? '#1E2D27' : '#DCF2DD',
                  borderColor: isDark ? '#2D4A3E' : '#C1E8C5',
                },
              ]}>
              <ThemedText
                style={[
                  styles.avatarInitial,
                  { color: isDark ? '#4ADE80' : '#166534' },
                ]}>
                {senderInitial}
              </ThemedText>
            </View>
          )}
        </View>
      )}

      {/* فقاعة الرسالة تفاعلية بالضغط المطول */}
      <TouchableOpacity
        activeOpacity={0.92}
        onLongPress={handleLongPress}
        delayLongPress={320}
        style={[
          styles.bubble,
          isMe
            ? [
              styles.bubbleMe,
              {
                backgroundColor: silaTheme.primary,
              },
            ]
            : [
              styles.bubbleOther,
              {
                backgroundColor: isDark ? '#1E2623' : '#FFFFFF',
                borderColor: isDark ? '#2D3A34' : '#E2E8F0',
              },
            ],
        ]}>
        {/* اسم العضو في رسائل الآخرين */}
        {!isMe && (
          <ThemedText
            style={[
              styles.senderName,
              { color: isDark ? '#4ADE80' : silaTheme.primary },
            ]}>
            {message.sender?.name || 'عضو في التحدي'}
          </ThemedText>
        )}

        {/* اقتباس الرسالة التي تم الرد عليها */}
        {!!message.reply_to && (
          <View
            style={[
              styles.replyQuote,
              {
                borderRightColor: isMe
                  ? 'rgba(255,255,255,0.85)'
                  : isDark
                    ? '#4ADE80'
                    : silaTheme.primary,
                backgroundColor: isMe
                  ? 'rgba(255,255,255,0.16)'
                  : isDark
                    ? 'rgba(74,222,128,0.08)'
                    : '#F1F8F2',
              },
            ]}>
            <ThemedText
              numberOfLines={1}
              style={[
                styles.replySender,
                {
                  color: isMe
                    ? '#FFFFFF'
                    : isDark
                      ? '#4ADE80'
                      : silaTheme.primary,
                },
              ]}>
              {message.reply_to.sender || 'عضو في التحدي'}
            </ThemedText>
            <ThemedText
              numberOfLines={2}
              style={[
                styles.replyPreview,
                {
                  color: isMe
                    ? 'rgba(255,255,255,0.85)'
                    : isDark
                      ? '#CBD5E1'
                      : '#475569',
                },
              ]}>
              {message.reply_to.preview}
            </ThemedText>
          </View>
        )}

        {/* المرفق: معاينة الفيديو تدعم آيفون وأندرويد بالكامل */}
        {message.type === 'video' && !!message.attachment_url && (
          <VideoPreviewItem
            videoUrl={message.attachment_url}
            serverThumbnail={message.metadata?.thumbnail_url}
            formattedDuration={formattedDuration}
            onPress={handleVideoPress}
          />
        )}

        {/* المرفق: رسالة صوتية بنمط واتساب وتصميم فخم */}
        {message.type === 'audio' && !!message.attachment_url && (
          <VoiceMessagePlayer
            id={message.id}
            audioUrl={message.attachment_url}
            duration={message.metadata?.duration}
            waveform={message.metadata?.waveform}
            isMe={isMe}
            isDark={isDark}
          />
        )}

        {/* المرفق: صورة مصغرة بحجم مريح ومتناسق مع شارة التكبير السلس */}
        {message.type !== 'video' && message.type !== 'audio' && !!message.attachment_url && (
          <TouchableOpacity
            activeOpacity={0.88}
            onPress={() => {
              try {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              } catch {}
              onImagePress?.(message.attachment_url!);
            }}
            style={[
              styles.attachmentWrapper,
              {
                borderColor: isMe
                  ? 'rgba(255,255,255,0.2)'
                  : isDark
                    ? 'rgba(255,255,255,0.1)'
                    : 'rgba(0,0,0,0.08)',
              },
            ]}
            accessibilityRole="button"
            accessibilityLabel="عرض الصورة وتكبيرها">
            <Image
              source={{ uri: message.attachment_url }}
              style={styles.attachmentImage}
              contentFit="cover"
              transition={200}
            />
            {/* شارة التكبير التوضيحية */}
            <View style={styles.zoomBadge}>
              <Ionicons name="expand" size={11} color="#FFFFFF" />
              <ThemedText style={styles.zoomBadgeText}>تكبير</ThemedText>
            </View>
          </TouchableOpacity>
        )}

        {/* نص الرسالة */}
        {!!message.message && (
          <ThemedText
            style={[
              styles.messageText,
              { color: isMe ? '#FFFFFF' : isDark ? '#F3F4F6' : '#0F172A' },
            ]}>
            {message.message}
          </ThemedText>
        )}

        {/* الوقت ومؤشر الإرسال */}
        <View
          style={[
            styles.footerRow,
            { alignSelf: 'flex-end' },
          ]}>
          {message.is_edited && (
            <ThemedText
              style={[
                styles.editedText,
                {
                  color: isMe
                    ? 'rgba(255,255,255,0.82)'
                    : isDark
                      ? '#94A3B8'
                      : '#64748B',
                },
              ]}>
              تم التعديل
            </ThemedText>
          )}
          <ThemedText
            style={[
              styles.timeText,
              {
                color: isMe
                  ? 'rgba(255,255,255,0.82)'
                  : isDark
                    ? '#94A3B8'
                    : '#64748B',
              },
            ]}>
            {message.time_formatted || message.created_at_human || ''}
          </ThemedText>
          {isMe && (
            <Ionicons
              name="checkmark"
              size={13}
              color="rgba(255,255,255,0.95)"
            />
          )}
        </View>
      </TouchableOpacity>
    </View>
  );
});

const styles = StyleSheet.create({
  row: {
    width: '100%',
    direction: 'ltr',
    flexDirection: 'row',
    alignItems: 'flex-end',
    marginVertical: 4,
    paddingHorizontal: 12,
    gap: 8,
  },
  rowMe: {
    justifyContent: 'flex-end',
  },
  rowOther: {
    justifyContent: 'flex-start',
  },
  avatarWrapper: {
    marginBottom: 2,
  },
  avatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
  },
  avatarPlaceholder: {
    width: 34,
    height: 34,
    borderRadius: 17,
    borderWidth: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarInitial: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
  },
  bubble: {
    maxWidth: '78%',
    borderRadius: 18,
    paddingHorizontal: 12,
    paddingVertical: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.06,
    shadowRadius: 3,
    elevation: 2,
  },
  bubbleMe: {
    borderBottomRightRadius: 4,
    borderBottomLeftRadius: 18,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
  },
  bubbleOther: {
    borderBottomLeftRadius: 4,
    borderBottomRightRadius: 18,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    borderWidth: 1,
  },
  senderName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    marginBottom: 4,
    textAlign: 'right',
  },
  replyQuote: {
    borderRightWidth: 3,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 5,
    marginBottom: 6,
  },
  replySender: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    textAlign: 'right',
    writingDirection: 'rtl',
    marginBottom: 1,
  },
  replyPreview: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11.5,
    lineHeight: 17,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  messageText: {
    fontFamily: Platform.select({ ios: 'System', android: 'sans-serif' }),
    fontWeight: '700',
    fontSize: 15,
    lineHeight: 23,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  attachmentWrapper: {
    borderRadius: 14,
    overflow: 'hidden',
    marginBottom: 6,
    borderWidth: 1,
    position: 'relative',
    alignSelf: 'flex-start',
  },
  attachmentImage: {
    width: 220,
    maxWidth: '100%',
    height: 160,
    borderRadius: 13,
  },
  zoomBadge: {
    position: 'absolute',
    bottom: 5,
    left: 5,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: 'rgba(0, 0, 0, 0.62)',
    paddingHorizontal: 6,
    paddingVertical: 2.5,
    borderRadius: 7,
  },
  zoomBadgeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9.5,
    color: '#FFFFFF',
  },
  videoPreviewWrapper: {
    width: 220,
    maxWidth: '100%',
    height: 155,
    borderRadius: 14,
    overflow: 'hidden',
    position: 'relative',
    marginBottom: 6,
    backgroundColor: '#0F172A',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: 'rgba(0,0,0,0.15)',
  },
  videoThumbnail: {
    width: '100%',
    height: '100%',
    borderRadius: 13,
  },
  videoPlaceholderGradient: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  videoDarkOverlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0, 0, 0, 0.25)',
  },
  videoCenterPlayBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    borderWidth: 1.5,
    borderColor: 'rgba(255, 255, 255, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    position: 'absolute',
  },
  videoDurationBadge: {
    position: 'absolute',
    bottom: 6,
    left: 6,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  videoDurationBadgeText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 10.5,
    color: '#FFFFFF',
  },
  footerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginTop: 3,
  },
  timeText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 10.5,
  },
  editedText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 10.5,
  },
  systemContainer: {
    alignItems: 'center',
    marginVertical: 8,
  },
  systemPill: {
    paddingHorizontal: 14,
    paddingVertical: 5,
    borderRadius: 14,
    borderWidth: 1,
  },
  systemText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
  },
});
