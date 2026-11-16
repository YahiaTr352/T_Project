import React, { useState } from 'react';
import {
  Modal,
  StyleSheet,
  View,
  TouchableOpacity,
  ScrollView,
  Share,
  Dimensions,
  ActivityIndicator,
  Linking,
  Platform,
} from 'react-native';
import { Image } from 'expo-image';
import * as Haptics from 'expo-haptics';
import * as WebBrowser from 'expo-web-browser';
import { useRouter } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol, type IconSymbolName } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import MediaLightbox from '@/components/quiz/MediaLightbox';
import {
  type NotificationDetailItem,
  type ResolvedNotificationAction,
  extractUrls,
  resolveNotificationAction,
} from './notificationActions';

export type { NotificationDetailItem, ResolvedNotificationAction };
export { extractUrls, resolveNotificationAction };

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

interface NotificationDetailModalProps {
  visible: boolean;
  notification: NotificationDetailItem | null;
  onClose: () => void;
  colors: ThemeColors;
  isDark: boolean;
  onToggleRead?: (id: string) => void;
}

const TYPE_CONFIG: Record<
  NotificationDetailItem['type'],
  { label: string; icon: IconSymbolName; color: string }
> = {
  event: { label: 'فعالية عائلية', icon: 'calendar', color: '#10B981' },
  participant: { label: 'مشاركة عائلية', icon: 'person.2.fill', color: '#3B82F6' },
  system: { label: 'تنبيه نظام', icon: 'bell.fill', color: '#F59E0B' },
  reminder: { label: 'تذكير', icon: 'clock.fill', color: '#8B5CF6' },
  update: { label: 'تحديث جديد', icon: 'info.circle.fill', color: '#06B6D4' },
};

export function NotificationDetailModal({
  visible,
  notification,
  onClose,
  colors,
  isDark,
  onToggleRead,
}: NotificationDetailModalProps) {
  const router = useRouter();
  const [imageLoading, setImageLoading] = useState(true);
  const [lightboxVisible, setLightboxVisible] = useState(false);

  if (!notification) return null;

  const config = TYPE_CONFIG[notification.type] || TYPE_CONFIG.system;
  const embeddedUrls = extractUrls(notification.body);
  const primaryLink = notification.linkUrl || notification.actionUrl || (embeddedUrls.length > 0 ? embeddedUrls[0] : null);
  const action = resolveNotificationAction(notification);

  const handleExecuteAction = async (act: ResolvedNotificationAction) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
      onClose();
      if (act.route) {
        setTimeout(() => {
          router.push(act.route as any);
        }, 120);
      } else if (act.url) {
        await handleOpenLink(act.url);
      }
    } catch (e) {
      console.warn('[NotificationDetailModal] Action execution error:', e);
    }
  };

  const handleClose = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
    onClose();
  };

  const handleShare = async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
      let message = `${notification.title}\n\n${notification.body}`;
      if (primaryLink) {
        message += `\n\n🔗 الرابط: ${primaryLink}`;
      } else if (notification.mediaUrl) {
        message += `\n\n📎 الوسائط: ${notification.mediaUrl}`;
      }
      await Share.share({
        title: notification.title,
        message,
      });
    } catch (e) {
      console.warn('[NotificationDetailModal] Share error:', e);
    }
  };

  const handleOpenLink = async (url: string) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
      if (url.startsWith('http://') || url.startsWith('https://')) {
        await WebBrowser.openBrowserAsync(url);
      } else {
        await Linking.openURL(url);
      }
    } catch (e) {
      console.warn('[NotificationDetailModal] Open URL error:', e);
    }
  };

  const handleOpenMedia = () => {
    if (!notification.mediaUrl) return;
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
    if (notification.mediaType === 'image') {
      setLightboxVisible(true);
    } else if (notification.mediaType === 'video' || notification.mediaType === 'audio') {
      handleOpenLink(notification.mediaUrl);
    }
  };

  return (
    <>
      <Modal
        visible={visible}
        transparent
        animationType="slide"
        onRequestClose={handleClose}
        statusBarTranslucent
      >
        <View style={styles.overlay}>
          <TouchableOpacity
            style={styles.backdrop}
            activeOpacity={1}
            onPress={handleClose}
          />

          <View
            style={[
              styles.modalCard,
              {
                backgroundColor: isDark ? colors.card : '#FFFFFF',
                borderColor: isDark ? colors.border : '#E2E8F0',
              },
            ]}
          >
            {/* مقبض السحب بالأعلى */}
            <View style={styles.dragHandle} />

            {/* الهيدر العلوي */}
            <View style={styles.header}>
              <TouchableOpacity
                style={[
                  styles.closeButton,
                  {
                    backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F1F5F9',
                  },
                ]}
                onPress={handleClose}
                activeOpacity={0.7}
              >
                <IconSymbol name="xmark" size={18} color={colors.text} />
              </TouchableOpacity>

              <View style={styles.headerMeta}>
                <View
                  style={[
                    styles.typeChip,
                    { backgroundColor: config.color + '18' },
                  ]}
                >
                  <IconSymbol name={config.icon} size={14} color={config.color} />
                  <ThemedText style={[styles.typeText, { color: config.color }]}>
                    {config.label}
                  </ThemedText>
                </View>

                {notification.timeAgo ? (
                  <ThemedText style={[styles.timeText, { color: colors.icon }]}>
                    {notification.timeAgo}
                  </ThemedText>
                ) : null}
              </View>
            </View>

            {/* المحتوى القابل للتمرير */}
            <ScrollView
              style={styles.scrollBody}
              contentContainerStyle={styles.scrollContent}
              showsVerticalScrollIndicator={false}
            >
              {/* قسم الوسائط إن وُجدت (صورة أو فيديو أو صوت) */}
              {notification.mediaUrl && notification.mediaType === 'image' && (
                <View style={styles.mediaContainer}>
                  <TouchableOpacity
                    activeOpacity={0.92}
                    onPress={() => setLightboxVisible(true)}
                    style={[
                      styles.imageWrapper,
                      {
                        backgroundColor: isDark ? '#1E293B' : '#F8FAFC',
                        borderColor: isDark ? '#334155' : '#E2E8F0',
                      },
                    ]}
                  >
                    <Image
                      source={{ uri: notification.mediaUrl }}
                      style={styles.mainImage}
                      contentFit="cover"
                      transition={300}
                      onLoadStart={() => setImageLoading(true)}
                      onLoadEnd={() => setImageLoading(false)}
                    />

                    {imageLoading && (
                      <View style={styles.imageLoader}>
                        <ActivityIndicator size="small" color={colors.primary} />
                      </View>
                    )}

                    {/* زر تكبير الصورة فوق المعاينة */}
                    <View style={styles.expandBadge}>
                      <IconSymbol name="magnifyingglass" size={13} color="#FFFFFF" />
                      <ThemedText style={styles.expandText}>اضغط للتكبير</ThemedText>
                    </View>
                  </TouchableOpacity>
                </View>
              )}

              {notification.mediaUrl && notification.mediaType === 'video' && (
                <TouchableOpacity
                  activeOpacity={0.9}
                  onPress={() => handleOpenLink(notification.mediaUrl!)}
                  style={[
                    styles.videoCard,
                    {
                      backgroundColor: isDark ? '#1E293B' : '#0F172A',
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <View style={styles.videoPlayCircle}>
                    <IconSymbol name="play.fill" size={28} color="#FFFFFF" />
                  </View>
                  <View style={styles.videoMeta}>
                    <ThemedText style={styles.videoTitle}>مقطع فيديو مرفق</ThemedText>
                    <ThemedText style={styles.videoSubtitle}>اضغط لتشغيل الفيديو واستعراضه</ThemedText>
                  </View>
                  <IconSymbol name="arrow.up.right.square.fill" size={20} color="#10B981" />
                </TouchableOpacity>
              )}

              {notification.mediaUrl && notification.mediaType === 'audio' && (
                <TouchableOpacity
                  activeOpacity={0.9}
                  onPress={() => handleOpenLink(notification.mediaUrl!)}
                  style={[
                    styles.audioCard,
                    {
                      backgroundColor: isDark ? '#1E293B' : '#F1F5F9',
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <View style={[styles.audioIconCircle, { backgroundColor: colors.primary }]}>
                    <IconSymbol name="speaker.wave.2.fill" size={20} color="#FFFFFF" />
                  </View>
                  <View style={styles.audioMeta}>
                    <ThemedText style={[styles.audioTitle, { color: colors.text }]}>تسجيل صوتي مرفق</ThemedText>
                    <ThemedText style={[styles.audioSubtitle, { color: colors.icon }]}>اضغط للاستماع للتسجيل</ThemedText>
                  </View>
                  <IconSymbol name="arrow.up.right.square.fill" size={18} color={colors.primary} />
                </TouchableOpacity>
              )}

              {/* عنوان الإشعار */}
              <ThemedText style={[styles.title, { color: colors.text }]} selectable>
                {notification.title}
              </ThemedText>

              {/* وقت الإشعار بالتاريخ الكامل */}
              {notification.time ? (
                <View style={styles.dateRow}>
                  <IconSymbol name="calendar" size={14} color={colors.icon} />
                  <ThemedText style={[styles.dateText, { color: colors.icon }]}>
                    {notification.time}
                  </ThemedText>
                </View>
              ) : null}

              {/* نص الإشعار الكامل */}
              <ThemedText
                style={[
                  styles.body,
                  { color: isDark ? '#CBD5E1' : '#334155' },
                ]}
                selectable
              >
                {notification.body}
              </ThemedText>

              {/* الروابط المستخرجة أو المرفقة */}
              {primaryLink && (
                <View style={styles.linkSection}>
                  <ThemedText style={[styles.sectionHeading, { color: colors.icon }]}>
                    الروابط المرفقة
                  </ThemedText>
                  <TouchableOpacity
                    activeOpacity={0.8}
                    style={[
                      styles.linkButton,
                      {
                        backgroundColor: colors.primary + '14',
                        borderColor: colors.primary + '33',
                      },
                    ]}
                    onPress={() => handleOpenLink(primaryLink)}
                  >
                    <IconSymbol name="link.circle.fill" size={20} color={colors.primary} />
                    <ThemedText
                      style={[styles.linkButtonText, { color: colors.primary }]}
                      numberOfLines={1}
                    >
                      {primaryLink}
                    </ThemedText>
                    <IconSymbol name="arrow.up.right.square.fill" size={16} color={colors.primary} />
                  </TouchableOpacity>
                </View>
              )}
            </ScrollView>

            {/* شريط الإجراءات السفلي */}
            <View
              style={[
                styles.footer,
                {
                  borderTopColor: isDark ? 'rgba(255,255,255,0.08)' : '#F1F5F9',
                },
              ]}
            >
              {action && (
                <TouchableOpacity
                  style={styles.actionHeroButton}
                  onPress={() => handleExecuteAction(action)}
                  activeOpacity={0.86}
                >
                  <LinearGradient
                    colors={['#059669', '#10B981']}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 0 }}
                    style={styles.actionGradient}
                  >
                    <IconSymbol name={action.icon} size={20} color="#FFFFFF" />
                    <ThemedText style={styles.actionHeroText}>
                      {action.label}
                    </ThemedText>
                    <IconSymbol name="chevron.left" size={16} color="#FFFFFF" />
                  </LinearGradient>
                </TouchableOpacity>
              )}

              <View style={styles.secondaryActionsRow}>
                <TouchableOpacity
                  style={[
                    styles.actionButton,
                    styles.secondaryActionButton,
                    {
                      backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F8FAFC',
                      borderColor: isDark ? colors.border : '#E2E8F0',
                    },
                  ]}
                  onPress={handleShare}
                  activeOpacity={0.8}
                >
                  <IconSymbol name="square.and.arrow.up" size={17} color={colors.text} />
                  <ThemedText style={[styles.secondaryActionText, { color: colors.text }]}>
                    مشاركة الإشعار
                  </ThemedText>
                </TouchableOpacity>

                {notification.mediaUrl && notification.mediaType === 'image' && (
                  <TouchableOpacity
                    style={[
                      styles.actionButton,
                      styles.secondaryActionButton,
                      {
                        backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F8FAFC',
                        borderColor: isDark ? colors.border : '#E2E8F0',
                      },
                    ]}
                    onPress={handleOpenMedia}
                    activeOpacity={0.8}
                  >
                    <IconSymbol name="photo.fill" size={17} color={colors.text} />
                    <ThemedText style={[styles.secondaryActionText, { color: colors.text }]}>
                      عرض الصورة
                    </ThemedText>
                  </TouchableOpacity>
                )}
              </View>
            </View>
          </View>
        </View>
      </Modal>

      {/* عارض الصور بملء الشاشة مع التكبير */}
      {notification.mediaUrl && notification.mediaType === 'image' && (
        <MediaLightbox
          visible={lightboxVisible}
          mediaUrl={notification.mediaUrl}
          title={notification.title}
          onClose={() => setLightboxVisible(false)}
        />
      )}
    </>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'flex-end',
  },
  backdrop: {
    ...StyleSheet.absoluteFill,
  },
  modalCard: {
    width: '100%',
    maxHeight: SCREEN_HEIGHT * 0.88,
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderWidth: 1,
    borderBottomWidth: 0,
    paddingTop: 10,
    paddingHorizontal: 20,
    paddingBottom: Platform.OS === 'ios' ? 34 : 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.15,
    shadowRadius: 16,
    elevation: 20,
  },
  dragHandle: {
    width: 42,
    height: 5,
    borderRadius: 3,
    backgroundColor: 'rgba(150, 150, 150, 0.4)',
    alignSelf: 'center',
    marginBottom: 12,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingBottom: 14,
  },
  closeButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  typeChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 10,
  },
  typeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  timeText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  scrollBody: {
    flexGrow: 0,
  },
  scrollContent: {
    paddingVertical: 8,
  },
  mediaContainer: {
    marginBottom: 16,
  },
  imageWrapper: {
    width: '100%',
    height: 220,
    borderRadius: 18,
    overflow: 'hidden',
    borderWidth: 1,
    position: 'relative',
    justifyContent: 'center',
    alignItems: 'center',
  },
  mainImage: {
    width: '100%',
    height: '100%',
  },
  imageLoader: {
    ...StyleSheet.absoluteFill,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.1)',
  },
  expandBadge: {
    position: 'absolute',
    bottom: 10,
    right: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
  },
  expandText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
    color: '#FFFFFF',
  },
  videoCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 16,
    gap: 12,
  },
  videoPlayCircle: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#10B981',
    justifyContent: 'center',
    alignItems: 'center',
  },
  videoMeta: {
    flex: 1,
  },
  videoTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    color: '#FFFFFF',
    marginBottom: 2,
    textAlign: 'left',
  },
  videoSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    color: '#94A3B8',
    textAlign: 'left',
  },
  audioCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 16,
    gap: 12,
  },
  audioIconCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  audioMeta: {
    flex: 1,
  },
  audioTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    marginBottom: 2,
    textAlign: 'left',
  },
  audioSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    textAlign: 'left',
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    lineHeight: 28,
    marginBottom: 8,
    textAlign: 'left',
  },
  dateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 14,
  },
  dateText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  body: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 15,
    lineHeight: 26,
    marginBottom: 16,
    textAlign: 'left',
  },
  linkSection: {
    marginTop: 4,
    marginBottom: 12,
  },
  sectionHeading: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    marginBottom: 8,
    textAlign: 'left',
  },
  linkButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: 1,
    gap: 8,
  },
  linkButtonText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    flex: 1,
    textAlign: 'left',
  },
  footer: {
    paddingTop: 14,
    borderTopWidth: 1,
    gap: 10,
  },
  actionHeroButton: {
    borderRadius: 14,
    overflow: 'hidden',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 8,
    elevation: 4,
  },
  actionGradient: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    paddingHorizontal: 16,
    gap: 10,
  },
  actionHeroText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    color: '#FFFFFF',
  },
  secondaryActionsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  actionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 13,
    borderRadius: 14,
    gap: 8,
  },
  primaryActionButton: {},
  primaryActionText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    color: '#FFFFFF',
  },
  secondaryActionButton: {
    borderWidth: 1,
  },
  secondaryActionText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
});
