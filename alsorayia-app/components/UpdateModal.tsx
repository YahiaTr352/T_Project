import { Colors, silaTheme } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { Ionicons, MaterialIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  Easing,
  Modal,
  Platform,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const CARD_WIDTH = Math.min(SCREEN_WIDTH * 0.88, 400);

interface UpdateModalProps {
  visible: boolean;
  onUpdate: () => Promise<void>;
  onDismiss?: () => void;
  isMandatory?: boolean;
  title?: string;
  description?: string;
  versionLabel?: string;
  storeUrl?: string;
}

export function UpdateModal({
  visible,
  onUpdate,
  onDismiss,
  isMandatory = true,
  title = 'تحديث جديد متاح لتطبيق العائلة',
  description = 'يتوفر إصدار جديد يحتوي على تحسينات أداء وإصلاحات وميزات جديدة استثنائية. يرجى التحديث الآن لمواصلة استخدام التطبيق بأفضل تجربة.',
  versionLabel = 'الإصدار الأحدث',
  storeUrl,
}: UpdateModalProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  const [isDownloading, setIsDownloading] = useState(false);
  const [downloadStepText, setDownloadStepText] = useState('جاري الاتصال بالسيرفر وتنزيل الملفات...');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Animations
  const [pulseAnim] = useState(() => new Animated.Value(1));
  const [rotateAnim] = useState(() => new Animated.Value(0));
  const [progressAnim] = useState(() => new Animated.Value(0));
  const [progressPercent, setProgressPercent] = useState(0);

  // Pulsing background glow for badge
  useEffect(() => {
    if (!visible) return;

    const pulse = Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, {
          toValue: 1.18,
          duration: 1200,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.timing(pulseAnim, {
          toValue: 1,
          duration: 1200,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
      ])
    );
    pulse.start();

    return () => pulse.stop();
  }, [visible]);

  // Rotation animation for sync icon during downloading
  useEffect(() => {
    if (!isDownloading) return;

    const rotate = Animated.loop(
      Animated.timing(rotateAnim, {
        toValue: 1,
        duration: 1800,
        easing: Easing.linear,
        useNativeDriver: true,
      })
    );
    rotate.start();

    return () => rotate.stop();
  }, [isDownloading]);

  // Track progress value for percentage display
  useEffect(() => {
    const listenerId = progressAnim.addListener(({ value }) => {
      setProgressPercent(Math.round(value * 100));
    });
    return () => {
      progressAnim.removeListener(listenerId);
    };
  }, [progressAnim]);

  const handleStartUpdate = async () => {
    if (storeUrl) {
      await onUpdate();
      return;
    }
    setErrorMessage(null);
    setIsDownloading(true);
    setDownloadStepText('جاري الاتصال بالسيرفر وتنزيل الميزات الجديدة...');

    // Simulate progress bar animation up to 85%
    Animated.timing(progressAnim, {
      toValue: 0.85,
      duration: 2500,
      easing: Easing.out(Easing.quad),
      useNativeDriver: false,
    }).start();

    try {
      // Step 2 message
      setTimeout(() => {
        setDownloadStepText('جاري تثبيت التحديث وتطبيق التغييرات...');
      }, 1500);

      // Perform actual OTA update download & reload
      await onUpdate();

      // Complete progress to 100%
      Animated.timing(progressAnim, {
        toValue: 1,
        duration: 400,
        useNativeDriver: false,
      }).start();

      setDownloadStepText('تم التحديث بنجاح! جاري إعادة تشغيل التطبيق...');
    } catch (err: any) {
      console.error('[UpdateModal] error downloading update:', err);
      setIsDownloading(false);
      progressAnim.setValue(0);
      setErrorMessage('تعذر تنزيل التحديث. يرجى التأكد من اتصال الإنترنت والمحاولة مجدداً.');
    }
  };

  const spin = rotateAnim.interpolate({
    inputRange: [0, 1],
    outputRange: ['0deg', '360deg'],
  });

  const progressWidth = progressAnim.interpolate({
    inputRange: [0, 1],
    outputRange: ['0%', '100%'],
  });

  if (!visible) return null;

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      hardwareAccelerated
      statusBarTranslucent
      onRequestClose={() => {
        // Prevent closing if mandatory
      }}>
      <View style={styles.overlay}>
        <View
          style={[
            styles.cardContainer,
            {
              backgroundColor: isDark ? '#0F172A' : '#FFFFFF',
              borderColor: isDark ? '#1E293B' : '#E2E8F0',
            },
          ]}>
          
          {/* Top Decorative Header */}
          <LinearGradient
            colors={isDark ? ['#065F46', '#047857'] : ['#059669', '#10B981']}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={styles.cardHeaderGradient}>
            
            <Animated.View
              style={[
                styles.iconBadgeGlow,
                {
                  transform: [{ scale: pulseAnim }],
                  backgroundColor: 'rgba(255, 255, 255, 0.25)',
                },
              ]}
            />

            <View style={styles.iconBadgeWrap}>
              {isDownloading ? (
                <Animated.View style={{ transform: [{ rotate: spin }] }}>
                  <Ionicons name="sync" size={38} color="#047857" />
                </Animated.View>
              ) : (
                <Ionicons name="cloud-download-outline" size={40} color="#047857" />
              )}
            </View>

            <View style={styles.headerVersionBadge}>
              <Ionicons name="sparkles" size={12} color="#A7F3D0" />
              <Text style={styles.headerVersionText}>{versionLabel}</Text>
            </View>
          </LinearGradient>

          {/* Card Body */}
          <View style={styles.cardBody}>
            <Text style={[styles.titleText, { color: colors.text }]}>{title}</Text>
            
            <Text style={[styles.descriptionText, { color: isDark ? '#94A3B8' : '#475569' }]}>
              {description}
            </Text>

            {/* Error Banner */}
            {errorMessage ? (
              <View style={[styles.errorBox, { backgroundColor: isDark ? '#7F1D1D' : '#FEF2F2' }]}>
                <Ionicons name="alert-circle" size={18} color="#EF4444" />
                <Text style={[styles.errorText, { color: isDark ? '#FCA5A5' : '#991B1B' }]}>
                  {errorMessage}
                </Text>
              </View>
            ) : null}

            {/* State 1: Normal Prompt */}
            {!isDownloading ? (
              <View style={styles.actionContainer}>
                <TouchableOpacity
                  activeOpacity={0.88}
                  style={styles.updateButtonTouch}
                  onPress={() => void handleStartUpdate()}>
                  <LinearGradient
                    colors={['#10B981', '#059669', '#047857']}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 0 }}
                    style={styles.updateBtnGradient}>
                    <Ionicons name="download" size={20} color="#FFFFFF" />
                    <Text style={styles.updateBtnText}>تحديث التطبيق الآن</Text>
                    <Ionicons name="arrow-back" size={18} color="#FFFFFF" />
                  </LinearGradient>
                </TouchableOpacity>

                {!isMandatory && onDismiss && (
                  <TouchableOpacity
                    activeOpacity={0.7}
                    style={{ marginTop: 12, paddingVertical: 8, paddingHorizontal: 16 }}
                    onPress={onDismiss}>
                    <Text style={{ fontFamily: 'Tajawal-Medium', fontSize: 14, color: isDark ? '#94A3B8' : '#64748B' }}>
                      التحديث لاحقاً
                    </Text>
                  </TouchableOpacity>
                )}

                <View style={styles.mandatoryNoticeRow}>
                  <MaterialIcons name="security" size={14} color={silaTheme.primary} />
                  <Text style={[styles.mandatoryNoticeText, { color: colors.icon }]}>
                    {isMandatory ? 'تحديث إجباري آمن من خوادم عائلة السريّع' : 'تحديث اختياري موصى به'}
                  </Text>
                </View>
              </View>
            ) : (
              /* State 2: Downloading & Progress Loader */
              <View style={styles.loadingContainer}>
                <View style={styles.loadingHeaderRow}>
                  <Text style={[styles.loadingStepText, { color: colors.text }]}>
                    {downloadStepText}
                  </Text>
                  <Text style={[styles.percentText, { color: silaTheme.primary }]}>
                    {progressPercent}%
                  </Text>
                </View>

                {/* Progress Bar Track */}
                <View
                  style={[
                    styles.progressTrack,
                    { backgroundColor: isDark ? '#1E293B' : '#E2E8F0' },
                  ]}>
                  <Animated.View style={{ height: '100%', width: progressWidth }}>
                    <LinearGradient
                      colors={['#34D399', '#10B981', '#059669']}
                      start={{ x: 0, y: 0 }}
                      end={{ x: 1, y: 0 }}
                      style={styles.progressGradientFill}
                    />
                  </Animated.View>
                </View>

                <View style={styles.spinnerRow}>
                  <ActivityIndicator size="small" color={silaTheme.primary} />
                  <Text style={[styles.spinnerText, { color: colors.icon }]}>
                    يرجى عدم إغلاق التطبيق أثناء التنزيل...
                  </Text>
                </View>
              </View>
            )}
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.78)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  cardContainer: {
    width: CARD_WIDTH,
    borderRadius: 28,
    borderWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 12 },
        shadowOpacity: 0.35,
        shadowRadius: 24,
      },
      android: {
        elevation: 16,
      },
    }),
  },
  cardHeaderGradient: {
    height: 140,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
  },
  iconBadgeGlow: {
    position: 'absolute',
    width: 86,
    height: 86,
    borderRadius: 43,
  },
  iconBadgeWrap: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 6,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
  },
  headerVersionBadge: {
    position: 'absolute',
    top: 14,
    right: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0, 0, 0, 0.25)',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  headerVersionText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  cardBody: {
    padding: 22,
    alignItems: 'center',
  },
  titleText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
    textAlign: 'center',
    lineHeight: 28,
  },
  descriptionText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    textAlign: 'center',
    marginTop: 8,
    lineHeight: 21,
  },
  errorBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 12,
    borderRadius: 12,
    marginTop: 14,
    width: '100%',
  },
  errorText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  actionContainer: {
    width: '100%',
    marginTop: 20,
    alignItems: 'center',
  },
  updateButtonTouch: {
    width: '100%',
    borderRadius: 16,
    overflow: 'hidden',
    elevation: 4,
    shadowColor: '#059669',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
  },
  updateBtnGradient: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingVertical: 15,
    paddingHorizontal: 16,
  },
  updateBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  mandatoryNoticeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 14,
  },
  mandatoryNoticeText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
  loadingContainer: {
    width: '100%',
    marginTop: 20,
  },
  loadingHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  loadingStepText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    flex: 1,
  },
  percentText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    marginRight: 6,
  },
  progressTrack: {
    height: 10,
    borderRadius: 5,
    overflow: 'hidden',
    width: '100%',
  },
  progressGradientFill: {
    height: '100%',
    borderRadius: 5,
  },
  spinnerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 14,
  },
  spinnerText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
});
