import { Colors, silaTheme } from '@/constants/theme';
import { ChildPersonItem } from '@/lib/api/authApi';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import * as Haptics from 'expo-haptics';
import React, { useEffect, useMemo, useRef } from 'react';
import {
  Animated,
  Image,
  Modal,
  PanResponder,
  Platform,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  TouchableWithoutFeedback,
  View,
  useColorScheme,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ThemedText } from '../themed-text';

export interface ChildSelectorModalProps {
  visible: boolean;
  parentName?: string;
  childrenList: ChildPersonItem[];
  onSelectChild: (child: ChildPersonItem) => void;
  onCancel: () => void;
}

export function ChildSelectorModal({
  visible,
  parentName,
  childrenList,
  onSelectChild,
  onCancel,
}: ChildSelectorModalProps) {
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const { height } = useWindowDimensions();

  // حساب نقاط ارتكاز الـ Bottom Sheet (Snap Points)
  const initialHeight = Math.min(Math.max(380, height * 0.60), 540);
  const expandedHeight = Math.min(height * 0.88, 720);
  const initialOffset = expandedHeight - initialHeight;

  const translateY = useRef(new Animated.Value(expandedHeight + 150)).current;
  const backdropOpacity = useRef(new Animated.Value(0)).current;
  const currentOffset = useRef(initialOffset);
  const isExpanded = useRef(false);

  useEffect(() => {
    if (visible) {
      isExpanded.current = false;
      currentOffset.current = initialOffset;
      translateY.setValue(expandedHeight + 150);

      Animated.parallel([
        Animated.timing(backdropOpacity, {
          toValue: 1,
          duration: 250,
          useNativeDriver: true,
        }),
        Animated.spring(translateY, {
          toValue: initialOffset,
          tension: 70,
          friction: 11,
          useNativeDriver: true,
        }),
      ]).start();
    }
  }, [visible, initialOffset, expandedHeight, backdropOpacity, translateY]);

  const animateClose = (callback?: () => void) => {
    Animated.parallel([
      Animated.timing(backdropOpacity, {
        toValue: 0,
        duration: 200,
        useNativeDriver: true,
      }),
      Animated.timing(translateY, {
        toValue: expandedHeight + 150,
        duration: 200,
        useNativeDriver: true,
      }),
    ]).start(() => {
      if (callback) {
        callback();
      } else {
        onCancel();
      }
    });
  };

  const snapTo = (toOffset: number, expandedState: boolean) => {
    currentOffset.current = toOffset;
    isExpanded.current = expandedState;
    Animated.spring(translateY, {
      toValue: toOffset,
      tension: 70,
      friction: 11,
      useNativeDriver: true,
    }).start();
  };

  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onMoveShouldSetPanResponder: (_, gestureState) => Math.abs(gestureState.dy) > 5,
        onPanResponderGrant: () => {
          translateY.stopAnimation((val) => {
            currentOffset.current = val;
          });
        },
        onPanResponderMove: (_, gestureState) => {
          let nextValue = currentOffset.current + gestureState.dy;
          // مقاومة فيزيائية عند سحب الشيت لأعلى متجاوزاً نقطة التمدد القصوى
          if (nextValue < 0) {
            nextValue = nextValue * 0.25;
          }
          translateY.setValue(nextValue);
        },
        onPanResponderRelease: (_, gestureState) => {
          const projectedY = currentOffset.current + gestureState.dy;
          const { dy, vy } = gestureState;

          // السحب لأسفل بقوة أو لمسافة كافية -> إغلاق النافذة
          if (vy > 0.7 || dy > 120 || projectedY > initialOffset + 90) {
            try {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            } catch {}
            animateClose();
            return;
          }

          // السحب لأعلى لتكبير النافذة لمستوى الشاشة الكامل
          if (vy < -0.35 || projectedY < initialOffset * 0.45) {
            try {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            } catch {}
            snapTo(0, true);
          } else {
            // الاستقرار في الارتفاع المبدئي
            try {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            } catch {}
            snapTo(initialOffset, false);
          }
        },
      }),
    [initialOffset, expandedHeight, translateY]
  );

  const handleSelect = async (child: ChildPersonItem) => {
    try {
      await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    onSelectChild(child);
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="none"
      onRequestClose={() => animateClose()}
      statusBarTranslucent
    >
      <View style={styles.modalContainer}>
        {/* خلفية معتمة قابلة للنقر للإغلاق */}
        <TouchableWithoutFeedback onPress={() => animateClose()}>
          <Animated.View style={[styles.backdrop, { opacity: backdropOpacity }]} />
        </TouchableWithoutFeedback>

        {/* جسم الـ Bottom Sheet التفاعلي */}
        <Animated.View
          style={[
            styles.sheetContainer,
            {
              height: expandedHeight,
              transform: [{ translateY }],
              backgroundColor: isDark ? '#111827' : '#FFFFFF',
              borderColor: isDark ? '#374151' : '#E5E7EB',
              paddingBottom: Math.max(insets.bottom, 16),
            },
          ]}
        >
          {/* مقبض السحب العلوي (Drag Handle) لدعم الإيماءات */}
          <View style={styles.panArea} {...panResponder.panHandlers}>
            <View
              style={[
                styles.dragHandle,
                { backgroundColor: isDark ? '#4B5563' : '#D1D5DB' },
              ]}
            />
          </View>

          {/* رأس الـ Bottom Sheet */}
          <View style={styles.header}>
            <View style={styles.headerRight}>
              <View style={styles.headerIconWrapper}>
                <MaterialIcons name="face" size={24} color={silaTheme.primary} />
              </View>
              <View style={styles.headerTextWrap}>
                <ThemedText style={styles.title}>اختر الابن أو الابنة</ThemedText>
                {parentName ? (
                  <ThemedText style={styles.subtitle} numberOfLines={1}>
                    المسجلون تحت رعاية ({parentName})
                  </ThemedText>
                ) : (
                  <ThemedText style={styles.subtitle} numberOfLines={1}>
                    حدد الابن المراد تسجيل دخوله للمنصة
                  </ThemedText>
                )}
              </View>
            </View>

            <TouchableOpacity
              style={styles.closeBtn}
              onPress={() => animateClose()}
              activeOpacity={0.7}
              hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            >
              <MaterialIcons
                name="close"
                size={18}
                color={isDark ? '#9CA3AF' : '#6B7280'}
              />
            </TouchableOpacity>
          </View>

          {/* قائمة الأبناء بالاتجاه العربي الصحيح (الصورة يمين، البيانات وسط، السهم يسار) */}
          <ScrollView
            style={styles.scrollList}
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
          >
            {childrenList.map((child) => (
              <TouchableOpacity
                key={child.id}
                style={[
                  styles.childCard,
                  {
                    backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                    borderColor: isDark ? '#374151' : '#E5E7EB',
                  },
                ]}
                activeOpacity={0.7}
                onPress={() => handleSelect(child)}
              >
                {/* 1. صورة الابن/الابنة على اليمين */}
                <View style={styles.avatarWrapper}>
                  {child.avatar ? (
                    <Image source={{ uri: child.avatar }} style={styles.avatar} />
                  ) : (
                    <View
                      style={[
                        styles.avatarPlaceholder,
                        {
                          backgroundColor:
                            child.gender === 'female'
                              ? isDark ? 'rgba(236, 72, 153, 0.15)' : '#FCE7F3'
                              : isDark ? 'rgba(59, 130, 246, 0.15)' : '#EFF6FF',
                        },
                      ]}
                    >
                      <MaterialIcons
                        name={child.gender === 'female' ? 'girl' : 'boy'}
                        size={28}
                        color={child.gender === 'female' ? '#EC4899' : '#3B82F6'}
                      />
                    </View>
                  )}
                </View>

                {/* 2. بيانات الابن في المنتصف بمحاذاة يمنى طبيعية */}
                <View style={styles.childInfo}>
                  <View style={styles.childNameRow}>
                    <ThemedText style={styles.childName} numberOfLines={1}>
                      {child.first_name || child.full_name}
                    </ThemedText>
                    {child.age !== null && (
                      <View
                        style={[
                          styles.ageBadge,
                          {
                            backgroundColor: isDark ? 'rgba(16, 185, 129, 0.15)' : '#ECFDF5',
                            borderColor: isDark ? '#065F46' : '#A7F3D0',
                          },
                        ]}
                      >
                        <ThemedText style={styles.ageBadgeText}>
                          {child.age} {child.age <= 10 && child.age >= 3 ? 'سنوات' : 'سنة'}
                        </ThemedText>
                      </View>
                    )}
                  </View>

                  <ThemedText style={styles.childFullName} numberOfLines={1}>
                    {child.full_name}
                  </ThemedText>
                </View>

                {/* 3. سهم الانتقال / الاختيار على اليسار */}
                <View style={styles.arrowIcon}>
                  <MaterialIcons
                    name="chevron-left"
                    size={24}
                    color={isDark ? '#9CA3AF' : '#6B7280'}
                  />
                </View>
              </TouchableOpacity>
            ))}
          </ScrollView>
        </Animated.View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  modalContainer: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  backdrop: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
  },
  sheetContainer: {
    width: '100%',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderTopWidth: 1.5,
    borderLeftWidth: 1,
    borderRightWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: -4 },
        shadowOpacity: 0.25,
        shadowRadius: 16,
      },
      android: {
        elevation: 16,
      },
    }),
  },
  panArea: {
    paddingTop: 10,
    paddingBottom: 6,
    alignItems: 'center',
    width: '100%',
  },
  dragHandle: {
    width: 44,
    height: 5,
    borderRadius: 3,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(156, 163, 175, 0.2)',
  },
  headerRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  headerIconWrapper: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(217, 119, 6, 0.12)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTextWrap: {
    flex: 1,
    justifyContent: 'center',
  },
  title: {
    fontSize: 16.5,
    fontFamily: 'Tajawal-Bold',
    writingDirection: 'rtl',
  },
  subtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#94A3B8',
    marginTop: 2,
    writingDirection: 'rtl',
  },
  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: 'rgba(156, 163, 175, 0.12)',
    justifyContent: 'center',
    alignItems: 'center',
    marginLeft: 10,
  },
  scrollList: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 18,
    paddingTop: 14,
    paddingBottom: 24,
    gap: 10,
  },
  childCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 18,
    borderWidth: 1.5,
    gap: 12,
  },
  avatarWrapper: {
    width: 50,
    height: 50,
    borderRadius: 25,
    overflow: 'hidden',
  },
  avatar: {
    width: '100%',
    height: '100%',
  },
  avatarPlaceholder: {
    width: '100%',
    height: '100%',
    justifyContent: 'center',
    alignItems: 'center',
  },
  childInfo: {
    flex: 1,
    justifyContent: 'center',
  },
  childNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 3,
  },
  childName: {
    fontSize: 15.5,
    fontFamily: 'Tajawal-Bold',
    writingDirection: 'rtl',
  },
  childFullName: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#94A3B8',
    writingDirection: 'rtl',
  },
  ageBadge: {
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 8,
    borderWidth: 1,
  },
  ageBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#059669',
  },
  arrowIcon: {
    paddingHorizontal: 4,
  },
});
