import { Colors, silaTheme } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from 'react';
import {
  Alert as NativeAlert,
  Animated,
  Dimensions,
  Easing,
  Modal,
  Platform,
  StyleSheet,
  Text,
  TouchableOpacity,
  TouchableWithoutFeedback,
  View,
} from 'react-native';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const CARD_WIDTH = Math.min(SCREEN_WIDTH * 0.88, 380);

export type AlertType = 'success' | 'error' | 'warning' | 'info';

export interface AlertButton {
  text?: string;
  onPress?: () => void | Promise<void>;
  style?: 'default' | 'cancel' | 'destructive';
  icon?: keyof typeof Ionicons.glyphMap;
}

export interface AlertOptions {
  cancelable?: boolean;
  onDismiss?: () => void;
  type?: AlertType;
  icon?: keyof typeof Ionicons.glyphMap;
}

export interface ShowAlertConfig {
  title?: string;
  message?: string;
  type?: AlertType;
  icon?: keyof typeof Ionicons.glyphMap;
  buttons?: AlertButton[];
  cancelable?: boolean;
}

export interface AlertModalProps {
  visible: boolean;
  title?: string;
  message?: string;
  type?: AlertType;
  icon?: keyof typeof Ionicons.glyphMap;
  buttons?: AlertButton[];
  onClose: () => void;
  cancelable?: boolean;
}

/**
 * دالة ذكية لاستنتاج نوع التنبيه تلقائياً بناءً على العنوان والمحتوى وأزرار الإجراء
 */
export function inferAlertType(
  title?: string,
  message?: string,
  buttons?: AlertButton[]
): AlertType {
  const textToScan = `${title || ''} ${message || ''}`.toLowerCase();

  // فحص إذا كان هناك زر تدميري (حذف/استبعاد)
  const hasDestructive = buttons?.some((b) => b.style === 'destructive');
  if (
    hasDestructive ||
    textToScan.includes('حذف') ||
    textToScan.includes('استبعاد') ||
    textToScan.includes('إزالة') ||
    textToScan.includes('مغادرة') ||
    textToScan.includes('تسجيل الخروج') ||
    textToScan.includes('تأكيد')
  ) {
    return 'warning';
  }

  // نجاح واحتفال
  if (
    textToScan.includes('مبروك') ||
    textToScan.includes('نجاح') ||
    textToScan.includes('تمت') ||
    textToScan.includes('تم بنجاح') ||
    textToScan.includes('تم التحديث') ||
    textToScan.includes('تم التنشيط') ||
    textToScan.includes('🎉') ||
    textToScan.includes('⚡')
  ) {
    return 'success';
  }

  // أخطاء وفشل
  if (
    textToScan.includes('خطأ') ||
    textToScan.includes('فشل') ||
    textToScan.includes('تعذر') ||
    textToScan.includes('تعذّر') ||
    textToScan.includes('مرفوضة') ||
    textToScan.includes('مطلوبة') ||
    textToScan.includes('صلاحية') ||
    textToScan.includes('مطلوب')
  ) {
    return 'error';
  }

  return 'info';
}

// ألوان وأيقونات الأنواع المختلفة
const ALERT_CONFIG: Record<
  AlertType,
  {
    icon: keyof typeof Ionicons.glyphMap;
    gradient: [string, string, string];
    iconColor: string;
    iconBg: string;
    glowColor: string;
    confirmBtnGradient: [string, string];
  }
> = {
  success: {
    icon: 'checkmark-circle',
    gradient: ['#059669', '#10B981', '#34D399'],
    iconColor: '#059669',
    iconBg: '#ECFDF5',
    glowColor: 'rgba(16, 185, 129, 0.25)',
    confirmBtnGradient: ['#10B981', '#059669'],
  },
  error: {
    icon: 'close-circle',
    gradient: ['#DC2626', '#EF4444', '#F87171'],
    iconColor: '#DC2626',
    iconBg: '#FEF2F2',
    glowColor: 'rgba(239, 68, 68, 0.25)',
    confirmBtnGradient: ['#EF4444', '#DC2626'],
  },
  warning: {
    icon: 'warning',
    gradient: ['#D97706', '#F59E0B', '#FBBF24'],
    iconColor: '#D97706',
    iconBg: '#FFFBEB',
    glowColor: 'rgba(245, 158, 11, 0.25)',
    confirmBtnGradient: ['#F59E0B', '#D97706'],
  },
  info: {
    icon: 'information-circle',
    gradient: ['#145147', '#37a05c', '#4ade80'],
    iconColor: '#145147',
    iconBg: '#DCF2DD',
    glowColor: 'rgba(55, 160, 92, 0.25)',
    confirmBtnGradient: ['#37a05c', '#145147'],
  },
};

export function AlertModal({
  visible,
  title,
  message,
  type,
  icon,
  buttons,
  onClose,
  cancelable = false,
}: AlertModalProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  // استنتاج النوع عند عدم التمرير الصريح
  const resolvedType = type || inferAlertType(title, message, buttons);
  const config = ALERT_CONFIG[resolvedType];
  const activeIcon = icon || config.icon;

  // حركات الدخول والخروج والنبض
  const [scaleAnim] = useState(() => new Animated.Value(0.85));
  const [opacityAnim] = useState(() => new Animated.Value(0));
  const [pulseAnim] = useState(() => new Animated.Value(1));

  useEffect(() => {
    if (visible) {
      // ردود فعل لمسية لطيفة عند الظهور
      try {
        if (resolvedType === 'success') {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } else if (resolvedType === 'error') {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
        } else if (resolvedType === 'warning') {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
        } else {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        }
      } catch {
        // تجاهل أي بيئة لا تدعم الـ haptics
      }

      // تحريك نافذة المودال
      Animated.parallel([
        Animated.spring(scaleAnim, {
          toValue: 1,
          damping: 18,
          stiffness: 220,
          useNativeDriver: true,
        }),
        Animated.timing(opacityAnim, {
          toValue: 1,
          duration: 200,
          easing: Easing.out(Easing.ease),
          useNativeDriver: true,
        }),
      ]).start();

      // نبض خلف الأيقونة
      const pulseLoop = Animated.loop(
        Animated.sequence([
          Animated.timing(pulseAnim, {
            toValue: 1.15,
            duration: 1000,
            easing: Easing.inOut(Easing.ease),
            useNativeDriver: true,
          }),
          Animated.timing(pulseAnim, {
            toValue: 1,
            duration: 1000,
            easing: Easing.inOut(Easing.ease),
            useNativeDriver: true,
          }),
        ])
      );
      pulseLoop.start();

      return () => pulseLoop.stop();
    } else {
      scaleAnim.setValue(0.85);
      opacityAnim.setValue(0);
    }
  }, [visible, resolvedType]);

  const handleButtonPress = async (btn?: AlertButton) => {
    try {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}

    onClose();
    if (btn?.onPress) {
      if (Platform.OS === 'ios') {
        setTimeout(() => {
          Promise.resolve(btn.onPress?.()).catch((e) => {
            console.warn('[CustomAlert] onPress error:', e);
          });
        }, 120);
      } else {
        await btn.onPress();
      }
    }
  };

  const handleBackdropPress = () => {
    if (cancelable) {
      onClose();
    }
  };

  // معالجة قائمة الأزرار: إن لم تُمرر أزرار، يتم عرض زر "حسناً" الافتراضي
  const actionButtons: AlertButton[] =
    buttons && buttons.length > 0
      ? buttons
      : [{ text: 'حسناً', style: 'default' }];

  // ترتيب الأزرار: الزر التدميري أولاً (الأبرز بصرياً) ثم بقية الأزرار بترتيبها الأصلي
  const orderedButtons: AlertButton[] = (() => {
    const destructive = actionButtons.filter((b) => b.style === 'destructive');
    if (destructive.length === 0) return actionButtons;
    return [
      ...destructive,
      ...actionButtons.filter((b) => b.style !== 'destructive'),
    ];
  })();

  if (!visible) return null;

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      statusBarTranslucent
      onRequestClose={() => {
        if (cancelable) onClose();
      }}>
      <TouchableWithoutFeedback onPress={handleBackdropPress}>
        <View style={styles.overlay}>
          <TouchableWithoutFeedback>
            <Animated.View
              style={[
                styles.card,
                {
                  backgroundColor: isDark ? '#141E1B' : '#FFFFFF',
                  borderColor: isDark ? '#233831' : '#E2EBE7',
                  transform: [{ scale: scaleAnim }],
                  opacity: opacityAnim,
                },
              ]}>
              {/* الرأس البصري الفاخر مع التدرج والأيقونة */}
              <View style={styles.cardHeader}>
                <LinearGradient
                  colors={config.gradient}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 1 }}
                  style={styles.cardHeaderGradient}>
                  {/* تأثير الوهج النابض */}
                  <Animated.View
                    style={[
                      styles.iconGlow,
                      {
                        backgroundColor: config.glowColor,
                        transform: [{ scale: pulseAnim }],
                      },
                    ]}
                  />

                  {/* حاوية الأيقونة الدائرية */}
                  <View
                    style={[
                      styles.iconContainer,
                      {
                        backgroundColor: isDark ? '#0F172A' : '#FFFFFF',
                        shadowColor: config.iconColor,
                      },
                    ]}>
                    <Ionicons name={activeIcon} size={38} color={config.iconColor} />
                  </View>
                </LinearGradient>
              </View>

              {/* جسم البطاقة: النصوص والأزرار */}
              <View style={styles.body}>
                {title ? (
                  <Text style={[styles.title, { color: colors.text }]}>
                    {title}
                  </Text>
                ) : null}

                {message ? (
                  <Text
                    style={[
                      styles.message,
                      { color: isDark ? '#94A3B8' : '#475569' },
                    ]}>
                    {message}
                  </Text>
                ) : null}

                {/* شريط الإجراءات والأزرار - كلها بعرض كامل فوق بعضها لتفادي ازدحام النصوص */}
                <View style={styles.buttonsContainer}>
                  {orderedButtons.map((btn, index) => {
                    const isCancel = btn.style === 'cancel';
                    const isDestructive = btn.style === 'destructive';

                    // في حال كان الزر تدميرياً (حذف)، نعطيه تدرج أحمر جذاب
                    const btnGradient = isDestructive
                      ? (['#EF4444', '#DC2626'] as [string, string])
                      : (config.confirmBtnGradient as [string, string]);

                    if (isCancel) {
                      return (
                        <TouchableOpacity
                          key={btn.text || index}
                          activeOpacity={0.75}
                          style={[
                            styles.btnBase,
                            styles.cancelBtn,
                            {
                              backgroundColor: isDark ? '#1C2B27' : '#F1F5F4',
                              borderColor: isDark ? '#2E433D' : '#E2EBE7',
                            },
                          ]}
                          onPress={() => void handleButtonPress(btn)}>
                          <View style={styles.btnRow}>
                            {btn.icon ? (
                              <Ionicons
                                name={btn.icon}
                                size={18}
                                color={isDark ? '#94A3B8' : '#64748B'}
                                style={styles.btnIcon}
                              />
                            ) : null}
                            <Text
                              numberOfLines={2}
                              style={[
                                styles.cancelBtnText,
                                { color: isDark ? '#94A3B8' : '#64748B' },
                              ]}>
                              {btn.text || 'إلغاء'}
                            </Text>
                          </View>
                        </TouchableOpacity>
                      );
                    }

                    return (
                      <TouchableOpacity
                        key={btn.text || index}
                        activeOpacity={0.88}
                        style={styles.btnBase}
                        onPress={() => void handleButtonPress(btn)}>
                        <LinearGradient
                          colors={btnGradient}
                          start={{ x: 0, y: 0 }}
                          end={{ x: 1, y: 0 }}
                          style={styles.primaryGradient}>
                          <View style={styles.btnRow}>
                            {btn.icon ? (
                              <Ionicons
                                name={btn.icon}
                                size={18}
                                color="#FFFFFF"
                                style={styles.btnIcon}
                              />
                            ) : null}
                            <Text numberOfLines={2} style={styles.primaryBtnText}>
                              {btn.text || 'موافق'}
                            </Text>
                          </View>
                        </LinearGradient>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>
            </Animated.View>
          </TouchableWithoutFeedback>
        </View>
      </TouchableWithoutFeedback>
    </Modal>
  );
}

// ==================== سياق واستدعاء التنبيهات العام ====================

interface AlertContextValue {
  showAlert: (config: ShowAlertConfig) => void;
  hideAlert: () => void;
}

const AlertContext = createContext<AlertContextValue | undefined>(undefined);

type AlertListener = (config: ShowAlertConfig) => void;
type DismissListener = () => void;

let globalShowAlertListener: AlertListener | null = null;
let globalHideAlertListener: DismissListener | null = null;

/**
 * تحويل أزرار CustomAlert إلى صيغة التنبيه الأصلي (Alert.alert) في iOS
 */
function toNativeButtons(buttons?: AlertButton[]) {
  const list: AlertButton[] =
    buttons && buttons.length > 0 ? buttons : [{ text: 'حسناً', style: 'default' }];

  return list.map((b) => ({
    text: b.text || 'حسناً',
    style:
      b.style === 'destructive'
        ? ('destructive' as const)
        : b.style === 'cancel'
          ? ('cancel' as const)
          : ('default' as const),
    onPress: b.onPress,
  }));
}

/**
 * بديل احترافي ومطابق لواجهة React Native الافتراضية Alert.alert
 * يمكن استدعاؤه في أي مكان في التطبيق بنفس الطريقة تماماً:
 * CustomAlert.alert('العنوان', 'الرسالة', [ ...أزرار ]);
 */
export const CustomAlert = {
  alert: (
    title: string,
    message?: string,
    buttons?: AlertButton[],
    options?: AlertOptions
  ) => {
    // على iOS نستخدم التنبيه الأصلي (UIAlertController) لأنه يُعرض داخل نافذة
    // مستقلة تمامًا، فلا يتعارض مع أي Modal مفتوح.
    // الـ Modal المتداخل (تنبيه + ورقة خيارات) كان يجعل iOS يعلّق التطبيق.
    if (Platform.OS === 'ios') {
      NativeAlert.alert(title || '', message || '', toNativeButtons(buttons), {
        cancelable: options?.cancelable ?? false,
      });
      return;
    }

    if (globalShowAlertListener) {
      globalShowAlertListener({
        title,
        message,
        buttons,
        cancelable: options?.cancelable ?? false,
        type: options?.type,
        icon: options?.icon,
      });
      return;
    }

    console.warn('CustomAlert: AlertProvider is not mounted yet.');
  },

  show: (config: ShowAlertConfig) => {
    // على iOS نستخدم التنبيه الأصلي لأنه يُعرض داخل نافذة مستقلة،
    // فلا يتعارض مع أي Modal مفتوح (الـ Modals المتداخلة كانت تعلّق التطبيق).
    if (Platform.OS === 'ios') {
      NativeAlert.alert(config.title || '', config.message || '', toNativeButtons(config.buttons), {
        cancelable: config.cancelable ?? false,
      });
      return;
    }

    if (globalShowAlertListener) {
      globalShowAlertListener(config);
      return;
    }

    console.warn('CustomAlert: AlertProvider is not mounted yet.');
  },

  hide: () => {
    if (globalHideAlertListener) {
      globalHideAlertListener();
    }
  },
};

export const showAlert = CustomAlert.show;
export const hideAlert = CustomAlert.hide;
export const AlertComponent = AlertModal;

export function AlertProvider({ children }: { children: React.ReactNode }) {
  const [alertState, setAlertState] = useState<ShowAlertConfig & { visible: boolean }>({
    visible: false,
  });

  const handleShowAlert = useCallback((config: ShowAlertConfig) => {
    setAlertState({
      ...config,
      visible: true,
    });
  }, []);

  const handleHideAlert = useCallback(() => {
    setAlertState((prev) => ({
      ...prev,
      visible: false,
    }));
  }, []);

  useEffect(() => {
    globalShowAlertListener = handleShowAlert;
    globalHideAlertListener = handleHideAlert;

    return () => {
      globalShowAlertListener = null;
      globalHideAlertListener = null;
    };
  }, [handleShowAlert, handleHideAlert]);

  return (
    <AlertContext.Provider
      value={{ showAlert: handleShowAlert, hideAlert: handleHideAlert }}>
      {children}
      <AlertModal
        visible={alertState.visible}
        title={alertState.title}
        message={alertState.message}
        type={alertState.type}
        icon={alertState.icon}
        buttons={alertState.buttons}
        cancelable={alertState.cancelable}
        onClose={handleHideAlert}
      />
    </AlertContext.Provider>
  );
}

/**
 * خطاف useAlert لاستخدام التنبيهات داخل أي مكون React
 */
export function useAlert(): AlertContextValue {
  const context = useContext(AlertContext);
  if (!context) {
    return {
      showAlert: CustomAlert.show,
      hideAlert: CustomAlert.hide,
    };
  }
  return context;
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
    zIndex: 99999,
  },
  card: {
    width: CARD_WIDTH,
    borderRadius: 24,
    borderWidth: 1,
    // بدون overflow مخفي على الكارت حتى لا يقص ظله على أندرويد
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 12 },
        shadowOpacity: 0.25,
        shadowRadius: 20,
      },
      android: {
        elevation: 16,
      },
    }),
  },
  // غلاف رأس الكارت المسؤول عن قص التدرج على الزوايا العلوية فقط
  cardHeader: {
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    overflow: 'hidden',
  },
  cardHeaderGradient: {
    height: 100,
    justifyContent: 'center',
    alignItems: 'center',
    position: 'relative',
  },
  iconGlow: {
    position: 'absolute',
    width: 84,
    height: 84,
    borderRadius: 42,
  },
  iconContainer: {
    width: 68,
    height: 68,
    borderRadius: 34,
    justifyContent: 'center',
    alignItems: 'center',
    // الظل في iOS فقط، لأن elevation على أندرويد يُقص داخل رأس الكارت
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.2,
    shadowRadius: 10,
  },
  body: {
    paddingHorizontal: 22,
    paddingTop: 18,
    paddingBottom: 22,
    alignItems: 'center',
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    textAlign: 'center',
    marginBottom: 8,
    lineHeight: 26,
  },
  message: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 20,
  },
  buttonsContainer: {
    width: '100%',
    gap: 10,
  },
  btnBase: {
    width: '100%',
    borderRadius: 14,
    overflow: 'hidden',
  },
  cancelBtn: {
    paddingVertical: 14,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  cancelBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    textAlign: 'center',
    flexShrink: 1,
  },
  primaryGradient: {
    paddingVertical: 14,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    textAlign: 'center',
    flexShrink: 1,
  },
  btnRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  btnIcon: {
    marginHorizontal: 2,
  },
});
