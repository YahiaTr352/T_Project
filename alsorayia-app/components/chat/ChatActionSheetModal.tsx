import { Colors, silaTheme } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useEffect, useState } from 'react';
import {
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
import { SafeAreaView } from 'react-native-safe-area-context';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

export interface ChatActionItem {
  id: string;
  label: string;
  sublabel?: string;
  icon: keyof typeof Ionicons.glyphMap;
  iconColor?: string;
  variant?: 'default' | 'destructive' | 'disabled';
  badge?: string;
  onPress: () => void | Promise<void>;
}

export interface ChatActionSheetModalProps {
  visible: boolean;
  title?: string;
  subtitle?: string;
  actions: ChatActionItem[];
  onClose: () => void;
}

export function ChatActionSheetModal({
  visible,
  title,
  subtitle,
  actions,
  onClose,
}: ChatActionSheetModalProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  const [translateY] = useState(() => new Animated.Value(SCREEN_HEIGHT));
  const [opacity] = useState(() => new Animated.Value(0));

  useEffect(() => {
    if (visible) {
      try {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      } catch { }

      Animated.parallel([
        Animated.timing(opacity, {
          toValue: 1,
          duration: 180,
          useNativeDriver: true,
        }),
        Animated.spring(translateY, {
          toValue: 0,
          damping: 24,
          stiffness: 260,
          useNativeDriver: true,
        }),
      ]).start();
    } else {
      translateY.setValue(SCREEN_HEIGHT);
      opacity.setValue(0);
    }
  }, [visible]);

  /**
   * إغلاق الورقة مع الانتظار حتى يستقر إغلاق الـ Modal فعلياً قبل عرض أي نافذة أخرى.
   * فتح نافذة أثناء إغلاق الـ Modal يجعل iOS يعلّق التطبيق.
   */
  const dismissSheet = (afterClose?: () => void) => {
    Animated.parallel([
      Animated.timing(opacity, {
        toValue: 0,
        duration: 150,
        easing: Easing.in(Easing.ease),
        useNativeDriver: true,
      }),
      Animated.timing(translateY, {
        toValue: 350,
        duration: 180,
        easing: Easing.in(Easing.ease),
        useNativeDriver: true,
      }),
    ]).start(() => {
      onClose();

      if (afterClose) {
        // مهلة قصيرة تضمن اكتمال الإغلاق على مستوى النظام قبل فتح نافذة جديدة
        setTimeout(afterClose, 150);
      }
    });
  };

  const handleDismiss = () => {
    dismissSheet();
  };

  const handleActionPress = async (action: ChatActionItem) => {
    if (action.variant === 'disabled') return;

    try {
      if (action.variant === 'destructive') {
        void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      } else {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      }
    } catch { }

    // إغلاق الورقة أولاً ثم تشغيل الإجراء بعد استقرار الإغلاق
    dismissSheet(() => {
      void Promise.resolve(action.onPress()).catch((err) => {
        console.warn('[ChatActionSheetModal] Action execution failed:', err);
      });
    });
  };

  if (!visible) return null;

  return (
    <Modal
      visible={visible}
      transparent
      animationType="none"
      statusBarTranslucent
      onRequestClose={handleDismiss}>
      <View style={styles.overlay}>
        {/* خلفية التعتيم التفاعلية */}
        <TouchableWithoutFeedback onPress={handleDismiss}>
          <Animated.View style={[styles.backdrop, { opacity }]} />
        </TouchableWithoutFeedback>

        {/* ورقة الخيارات المنبثقة من الأسفل */}
        <Animated.View
          style={[
            styles.sheetContainer,
            {
              backgroundColor: isDark ? '#16221E' : '#FFFFFF',
              borderColor: isDark ? '#233831' : '#E2EBE7',
              transform: [{ translateY }],
            },
          ]}>
          {/* مقبض السحب البصري العلوي */}
          <View style={styles.handleBarWrapper}>
            <View
              style={[
                styles.handleBar,
                { backgroundColor: isDark ? '#2E433D' : '#CBD5E1' },
              ]}
            />
          </View>

          {/* الترويسة الأنيقة */}
          {(title || subtitle) && (
            <View style={styles.header}>
              {title ? (
                <Text style={[styles.title, { color: colors.text }]}>{title}</Text>
              ) : null}
              {subtitle ? (
                <Text
                  style={[
                    styles.subtitle,
                    { color: isDark ? '#94A3B8' : '#64748B' },
                  ]}>
                  {subtitle}
                </Text>
              ) : null}
            </View>
          )}

          {/* قائمة الخيارات المعززة بأيقونات Expo Vector Icons */}
          <View style={styles.actionsList}>
            {actions.map((action, index) => {
              const isDestructive = action.variant === 'destructive';
              const isDisabled = action.variant === 'disabled';

              // ألوان الأيقونة والخلفية الدائرية
              const iconColor = isDisabled
                ? isDark
                  ? '#475569'
                  : '#94A3B8'
                : isDestructive
                  ? '#EF4444'
                  : action.iconColor || silaTheme.primary;

              const iconBg = isDisabled
                ? isDark
                  ? '#1E293B'
                  : '#F1F5F9'
                : isDestructive
                  ? isDark
                    ? 'rgba(239, 68, 68, 0.15)'
                    : '#FEF2F2'
                  : isDark
                    ? 'rgba(16, 185, 129, 0.14)'
                    : '#ECFDF5';

              const textColor = isDisabled
                ? isDark
                  ? '#64748B'
                  : '#94A3B8'
                : isDestructive
                  ? '#EF4444'
                  : colors.text;

              return (
                <TouchableOpacity
                  key={action.id || index}
                  activeOpacity={isDisabled ? 1 : 0.7}
                  onPress={() => void handleActionPress(action)}
                  style={[
                    styles.actionItem,
                    {
                      backgroundColor: isDark ? '#1C2B26' : '#F8FAFC',
                      borderColor: isDark ? '#283B34' : '#E2E8F0',
                      opacity: isDisabled ? 0.65 : 1,
                    },
                  ]}>
                  {/* حاوية الأيقونة المتجهة */}
                  <View style={[styles.iconCircle, { backgroundColor: iconBg }]}>
                    <Ionicons name={action.icon} size={21} color={iconColor} />
                  </View>

                  {/* نصوص الخيار والتسمية الفرعية */}
                  <View style={styles.actionTextCol}>
                    <Text style={[styles.actionLabel, { color: textColor }]}>
                      {action.label}
                    </Text>
                    {action.sublabel ? (
                      <Text
                        style={[
                          styles.actionSublabel,
                          { color: isDark ? '#94A3B8' : '#64748B' },
                        ]}>
                        {action.sublabel}
                      </Text>
                    ) : null}
                  </View>

                  {/* شارة إضافية إن وجدت (مثل وقت العد التنازلي) */}
                  {action.badge ? (
                    <View
                      style={[
                        styles.badge,
                        {
                          backgroundColor: isDestructive
                            ? isDark
                              ? 'rgba(239, 68, 68, 0.18)'
                              : '#FEE2E2'
                            : isDark
                              ? '#24342E'
                              : '#E2E8F0',
                        },
                      ]}>
                      <Ionicons
                        name="time-outline"
                        size={12}
                        color={isDestructive ? '#EF4444' : isDark ? '#94A3B8' : '#64748B'}
                        style={{ marginLeft: 3 }}
                      />
                      <Text
                        style={[
                          styles.badgeText,
                          {
                            color: isDestructive
                              ? '#EF4444'
                              : isDark
                                ? '#94A3B8'
                                : '#64748B',
                          },
                        ]}>
                        {action.badge}
                      </Text>
                    </View>
                  ) : (
                    <Ionicons
                      name="chevron-back"
                      size={18}
                      color={isDark ? '#475569' : '#CBD5E1'}
                    />
                  )}
                </TouchableOpacity>
              );
            })}
          </View>

          {/* زر إلغاء السفلي المستقل */}
          <SafeAreaView edges={['bottom']} style={styles.cancelArea}>
            <TouchableOpacity
              activeOpacity={0.7}
              onPress={handleDismiss}
              style={[
                styles.cancelButton,
                {
                  backgroundColor: isDark ? '#1C2B26' : '#F1F5F9',
                  borderColor: isDark ? '#2E433D' : '#E2E8F0',
                },
              ]}>
              <Ionicons
                name="close-circle-outline"
                size={20}
                color={isDark ? '#94A3B8' : '#64748B'}
                style={{ marginHorizontal: 6 }}
              />
              <Text
                style={[
                  styles.cancelText,
                  { color: isDark ? '#CBD5E1' : '#475569' },
                ]}>
                إلغاء
              </Text>
            </TouchableOpacity>
          </SafeAreaView>
        </Animated.View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  backdrop: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0, 0, 0, 0.55)',
  },
  sheetContainer: {
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    borderTopWidth: 1,
    borderLeftWidth: 1,
    borderRightWidth: 1,
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 8,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: -6 },
        shadowOpacity: 0.15,
        shadowRadius: 16,
      },
      android: {
        elevation: 16,
      },
    }),
  },
  handleBarWrapper: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  handleBar: {
    width: 44,
    height: 4.5,
    borderRadius: 3,
  },
  header: {
    alignItems: 'center',
    paddingVertical: 10,
    marginBottom: 6,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    textAlign: 'center',
    marginBottom: 4,
  },
  subtitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    textAlign: 'center',
    lineHeight: 18,
  },
  actionsList: {
    gap: 9,
    marginVertical: 6,
  },
  actionItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 13,
    paddingHorizontal: 15,
    borderRadius: 16,
    borderWidth: 1,
  },
  iconCircle: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginHorizontal: 12,
  },
  actionTextCol: {
    flex: 1,
    alignItems: 'flex-start',
  },
  actionLabel: {
    fontFamily: 'Tajawal-Medium',
    fontWeight: '600',
    fontSize: 15,
    textAlign: 'left',
  },
  actionSublabel: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    marginTop: 2,
    textAlign: 'left',
  },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 10,
  },
  badgeText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
  cancelArea: {
    marginTop: 10,
  },
  cancelButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 13,
    borderRadius: 16,
    borderWidth: 1,
  },
  cancelText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
});
