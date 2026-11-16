import { Colors, silaTheme } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { Ionicons, MaterialIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  BackHandler,
  Dimensions,
  Easing,
  Linking,
  Modal,
  Platform,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const CARD_WIDTH = Math.min(SCREEN_WIDTH * 0.88, 400);

export interface MaintenanceModalProps {
  visible: boolean;
  title?: string;
  message?: string;
  onRetry: () => Promise<'still_maintenance' | 'resolved' | 'error'>;
  isRetrying?: boolean;
  supportWhatsapp?: string;
}

export function MaintenanceModal({
  visible,
  title = 'التطبيق تحت الصيانة',
  message = 'نعمل حالياً على إجراء أعمال صيانة وترقية دورية لتحسين الخدمات وتقديم تجربة أفضل. سنعود للعمل في أقرب وقت.',
  onRetry,
  isRetrying = false,
  supportWhatsapp,
}: MaintenanceModalProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  const [feedbackStatus, setFeedbackStatus] = useState<'idle' | 'still_maintenance' | 'error'>('idle');
  const [internalLoading, setInternalLoading] = useState(false);

  // Animations
  const [pulseAnim] = useState(() => new Animated.Value(1));
  const [rotateAnim] = useState(() => new Animated.Value(0));

  // Pulse animation for the glowing badge
  useEffect(() => {
    if (!visible) return;

    const pulse = Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, {
          toValue: 1.15,
          duration: 1400,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.timing(pulseAnim, {
          toValue: 1,
          duration: 1400,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
      ])
    );
    pulse.start();

    return () => pulse.stop();
  }, [visible]);

  // Rotation animation during retry/check
  const isLoading = isRetrying || internalLoading;
  useEffect(() => {
    if (!isLoading) {
      rotateAnim.setValue(0);
      return;
    }

    const rotate = Animated.loop(
      Animated.timing(rotateAnim, {
        toValue: 1,
        duration: 1200,
        easing: Easing.linear,
        useNativeDriver: true,
      })
    );
    rotate.start();

    return () => rotate.stop();
  }, [isLoading]);

  // Block Android hardware back button unconditionally during maintenance
  useEffect(() => {
    if (!visible) return;
    const backAction = () => true;
    const backHandler = BackHandler.addEventListener('hardwareBackPress', backAction);
    return () => backHandler.remove();
  }, [visible]);

  const handlePressRetry = async () => {
    if (isLoading) return;
    setInternalLoading(true);
    setFeedbackStatus('idle');

    try {
      const result = await onRetry();
      if (result === 'still_maintenance') {
        setFeedbackStatus('still_maintenance');
      } else if (result === 'error') {
        setFeedbackStatus('error');
      } else {
        setFeedbackStatus('idle');
      }
    } catch {
      setFeedbackStatus('error');
    } finally {
      setInternalLoading(false);
    }
  };

  const handleOpenWhatsapp = async () => {
    if (!supportWhatsapp) return;
    const cleanNumber = supportWhatsapp.replace(/\D/g, '');
    const url = `https://wa.me/${cleanNumber}`;
    try {
      const canOpen = await Linking.canOpenURL(url);
      if (canOpen) {
        await Linking.openURL(url);
        return;
      }
    } catch (e) {
      console.warn('[MaintenanceModal] Could not open WhatsApp:', e);
    }
    void Linking.openURL(url);
  };

  const spin = rotateAnim.interpolate({
    inputRange: [0, 1],
    outputRange: ['0deg', '360deg'],
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
        // Prevent dismissal on hardware back press
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
          
          {/* Header Gradient (Amber / Warm Bronze for Maintenance Status) */}
          <LinearGradient
            colors={isDark ? ['#78350F', '#92400E', '#B45309'] : ['#B45309', '#D97706', '#F59E0B']}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={styles.cardHeaderGradient}>
            
            {/* Glowing Halo */}
            <Animated.View
              style={[
                styles.iconBadgeGlow,
                {
                  transform: [{ scale: pulseAnim }],
                  backgroundColor: 'rgba(255, 255, 255, 0.22)',
                },
              ]}
            />

            {/* Icon Badge */}
            <View style={styles.iconBadgeWrap}>
              <Ionicons name="construct" size={38} color="#D97706" />
            </View>

            {/* Top Right Status Badge */}
            <View style={styles.headerStatusBadge}>
              <Ionicons name="time" size={13} color="#FEF3C7" />
              <Text style={styles.headerStatusText}>صيانة مجدولة</Text>
            </View>
          </LinearGradient>

          {/* Body Content */}
          <View style={styles.cardBody}>
            <Text style={[styles.titleText, { color: colors.text }]}>{title}</Text>
            
            <Text style={[styles.descriptionText, { color: isDark ? '#94A3B8' : '#475569' }]}>
              {message}
            </Text>

            {/* Reassurance Notice Box */}
            <View
              style={[
                styles.noticeBox,
                {
                  backgroundColor: isDark ? 'rgba(30, 41, 59, 0.8)' : '#F8FAFC',
                  borderColor: isDark ? '#334155' : '#E2E8F0',
                },
              ]}>
              <Ionicons name="shield-checkmark" size={18} color="#10B981" />
              <Text
                style={[
                  styles.noticeText,
                  { color: isDark ? '#CBD5E1' : '#475569' },
                ]}>
                بياناتكم وحساباتكم في أمان تام. لا يلزم تحديث التطبيق من المتجر، وستعود الخدمة تلقائياً فور انتهاء الصيانة.
              </Text>
            </View>

            {/* Dynamic Status Feedback (After Retry) */}
            {feedbackStatus === 'still_maintenance' ? (
              <View style={[styles.feedbackBanner, { backgroundColor: isDark ? '#451A03' : '#FFFBEB' }]}>
                <Ionicons name="alert-circle" size={18} color="#D97706" />
                <Text style={[styles.feedbackText, { color: isDark ? '#FCD34D' : '#92400E' }]}>
                  أعمال الصيانة ما زالت جارية، نشكر تفهمكم وصبركم وسنعود للعمل قريباً.
                </Text>
              </View>
            ) : feedbackStatus === 'error' ? (
              <View style={[styles.feedbackBanner, { backgroundColor: isDark ? '#7F1D1D' : '#FEF2F2' }]}>
                <Ionicons name="cloud-offline" size={18} color="#EF4444" />
                <Text style={[styles.feedbackText, { color: isDark ? '#FCA5A5' : '#991B1B' }]}>
                  تعذر الاتصال بالخادم، يرجى التأكد من اتصال الإنترنت والمحاولة مجدداً.
                </Text>
              </View>
            ) : null}

            {/* Primary Action: Re-check Status / Retry */}
            <View style={styles.actionContainer}>
              <TouchableOpacity
                activeOpacity={0.88}
                disabled={isLoading}
                style={[styles.retryButtonTouch, isLoading && { opacity: 0.8 }]}
                onPress={() => void handlePressRetry()}>
                <LinearGradient
                  colors={['#D97706', '#B45309', '#92400E']}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 0 }}
                  style={styles.retryBtnGradient}>
                  {isLoading ? (
                    <ActivityIndicator size="small" color="#FFFFFF" />
                  ) : (
                    <Animated.View style={{ transform: [{ rotate: spin }] }}>
                      <Ionicons name="refresh" size={20} color="#FFFFFF" />
                    </Animated.View>
                  )}
                  <Text style={styles.retryBtnText}>
                    {isLoading ? 'جاري التحقق من الحالة...' : 'التحقق من حالة النظام'}
                  </Text>
                </LinearGradient>
              </TouchableOpacity>

              {/* Optional WhatsApp Support Action */}
              {supportWhatsapp ? (
                <TouchableOpacity
                  activeOpacity={0.75}
                  style={styles.supportButtonTouch}
                  onPress={() => void handleOpenWhatsapp()}>
                  <Ionicons name="logo-whatsapp" size={18} color="#10B981" />
                  <Text style={styles.supportBtnText}>تواصل مع الدعم الفني</Text>
                </TouchableOpacity>
              ) : null}

              <View style={styles.securityNoticeRow}>
                <MaterialIcons name="lock-outline" size={13} color={silaTheme.gold} />
                <Text style={[styles.securityNoticeText, { color: colors.icon }]}>
                  خوادم عائلة السريّع تخضع للصيانة المعتمدة
                </Text>
              </View>
            </View>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.82)',
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
    shadowOpacity: 0.18,
    shadowRadius: 8,
  },
  headerStatusBadge: {
    position: 'absolute',
    top: 14,
    right: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: 'rgba(0, 0, 0, 0.28)',
    paddingHorizontal: 11,
    paddingVertical: 4.5,
    borderRadius: 12,
  },
  headerStatusText: {
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
  noticeBox: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
    marginTop: 14,
    width: '100%',
  },
  noticeText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    lineHeight: 18,
  },
  feedbackBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 12,
    borderRadius: 12,
    marginTop: 14,
    width: '100%',
  },
  feedbackText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    lineHeight: 18,
  },
  actionContainer: {
    width: '100%',
    marginTop: 20,
    alignItems: 'center',
  },
  retryButtonTouch: {
    width: '100%',
    borderRadius: 16,
    overflow: 'hidden',
    elevation: 4,
    shadowColor: '#D97706',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
  },
  retryBtnGradient: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingVertical: 14,
    paddingHorizontal: 16,
  },
  retryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  supportButtonTouch: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 12,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  supportBtnText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    color: '#10B981',
  },
  securityNoticeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 14,
  },
  securityNoticeText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
});
