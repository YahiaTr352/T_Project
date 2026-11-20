import React, { useRef, useEffect, useState } from 'react';
import {
  Animated,
  PanResponder,
  StyleSheet,
  TouchableOpacity,
  View,
  Platform,
  type LayoutChangeEvent,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as Haptics from 'expo-haptics';
import * as WebBrowser from 'expo-web-browser';

import { ThemedText } from '@/components/themed-text';
import { IconSymbol, type IconSymbolName } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import type { InvitationResponse } from '@/lib/api/invitations';
import {
  resolveNotificationAction,
  type NotificationDetailItem,
  type ResolvedNotificationAction,
} from '@/components/notifications/notificationActions';

const NOTIFICATION_ICONS: Record<
  NotificationDetailItem['type'],
  IconSymbolName
> = {
  event: 'calendar',
  participant: 'person.2.fill',
  system: 'bell.fill',
  reminder: 'clock.fill',
  update: 'info.circle.fill',
};

const NOTIFICATION_COLORS: Record<NotificationDetailItem['type'], string> = {
  event: '#10B981',
  participant: '#3B82F6',
  system: '#F59E0B',
  reminder: '#8B5CF6',
  update: '#06B6D4',
};

const REVEAL_WIDTH = 80;
const NOTIFICATION_DATE_FORMAT = new Intl.DateTimeFormat('ar-SA', {
  calendar: 'gregory',
  timeZone: 'Asia/Riyadh',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
  hour12: true,
});
const INVITATION_ACTIONS = [
  { value: 'accepted', label: 'قبول', accessibilityLabel: 'قبول الدعوة' },
  { value: 'declined', label: 'رفض', accessibilityLabel: 'رفض الدعوة' },
] as const;

function formatNotificationDate(timestamp: string): string {
  const date = new Date(timestamp);
  return Number.isNaN(date.getTime()) ? timestamp : NOTIFICATION_DATE_FORMAT.format(date);
}

export interface SwipeableNotificationCardProps {
  item: NotificationDetailItem;
  colors: ThemeColors;
  isDark: boolean;
  isExpanded: boolean;
  isSelectionMode: boolean;
  isSelected: boolean;
  onToggleSelect: () => void;
  onToggleExpand: () => void;
  onExecuteAction: (action: ResolvedNotificationAction) => void;
  onToggleRead: () => void;
  onImagePress: (url: string) => void;
  onShare: () => void;
  onDelete: () => void;
  onInvitationResponse?: (response: InvitationResponse) => void;
  invitationResponsePending?: boolean;
}

export function SwipeableNotificationCard({
  item,
  colors,
  isDark,
  isExpanded,
  isSelectionMode,
  isSelected,
  onToggleSelect,
  onToggleExpand,
  onExecuteAction,
  onToggleRead,
  onImagePress,
  onShare,
  onDelete,
  onInvitationResponse,
  invitationResponsePending = false,
}: SwipeableNotificationCardProps) {
  const iconName = NOTIFICATION_ICONS[item.type] || 'bell.fill';
  const accentColor = NOTIFICATION_COLORS[item.type] || colors.primary;
  const action = resolveNotificationAction(item);
  const isInvitation = item.invitation != null;
  const showInvitationResponse = isInvitation && onInvitationResponse && !isSelectionMode;
  const savedResponse = item.invitation?.response;
  const declineColor = isDark ? '#FCA5A5' : '#B91C1C';
  const formattedTime = item.time && (isExpanded || !item.timeAgo) ? formatNotificationDate(item.time) : '';

  const hasImage = item.mediaType === 'image' && !!item.mediaUrl;
  const hasVideo = item.mediaType === 'video' && !!item.mediaUrl;
  const isLongText = (item.body && item.body.length > 75) || (item.body && item.body.includes('\n'));

  // الرسوم المتحركة
  const [translateX] = useState(() => new Animated.Value(0));
  const [itemHeight] = useState(() => new Animated.Value(1));
  const [itemOpacity] = useState(() => new Animated.Value(1));
  const currentOffset = useRef(0);
  const [cardWidth, setCardWidth] = useState(0);

  const handleWrapLayout = (event: LayoutChangeEvent) => {
    const measuredWidth = event.nativeEvent.layout.width;
    if (measuredWidth !== cardWidth) setCardWidth(measuredWidth);
  };

  // إغلاق السحب إذا تغير وضع التحديد
  useEffect(() => {
    if (isSelectionMode && currentOffset.current !== 0) {
      Animated.spring(translateX, {
        toValue: 0,
        bounciness: 4,
        useNativeDriver: true,
      }).start();
      currentOffset.current = 0;
    }
  }, [isSelectionMode, translateX]);

  const closeSwipe = () => {
    Animated.spring(translateX, {
      toValue: 0,
      bounciness: 5,
      useNativeDriver: true,
    }).start();
    currentOffset.current = 0;
  };

  const executeDelete = () => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
    const targetX = currentOffset.current < 0 ? -400 : 400;

    Animated.parallel([
      Animated.timing(itemOpacity, {
        toValue: 0,
        duration: 180,
        useNativeDriver: true,
      }),
      Animated.timing(translateX, {
        toValue: targetX,
        duration: 200,
        useNativeDriver: true,
      }),
      Animated.timing(itemHeight, {
        toValue: 0,
        duration: 220,
        useNativeDriver: true,
      }),
    ]).start(() => {
      onDelete();
    });
  };

  // eslint-disable-next-line react-hooks/refs -- callbacks تقرأ الـ refs فقط أثناء الإيماءات وليس أثناء الـ render
  const [panResponder] = useState(() =>
    PanResponder.create({
      onStartShouldSetPanResponder: () => false,
      onStartShouldSetPanResponderCapture: () => false,
      onMoveShouldSetPanResponderCapture: (_, gestureState) => {
        if (isSelectionMode) return false;
        // التقاط الحركة الأفقية الصريحة فقط دون مقاطعة التمرير الرأسي
        return Math.abs(gestureState.dx) > 10 && Math.abs(gestureState.dx) > Math.abs(gestureState.dy) * 1.5;
      },
      onMoveShouldSetPanResponder: (_, gestureState) => {
        if (isSelectionMode) return false;
        return Math.abs(gestureState.dx) > 10 && Math.abs(gestureState.dx) > Math.abs(gestureState.dy) * 1.5;
      },
      onPanResponderTerminationRequest: () => false,
      onPanResponderMove: (_, gestureState) => {
        if (isSelectionMode) return;
        const rawX = currentOffset.current + gestureState.dx;
        // تقييد أقصى مسافة سحب مع مقاومة فيزيائية سلسة
        const clampedX = Math.max(-REVEAL_WIDTH - 25, Math.min(REVEAL_WIDTH + 25, rawX));
        translateX.setValue(clampedX);
      },
      onPanResponderRelease: (_, gestureState) => {
        if (isSelectionMode) return;
        const dx = gestureState.dx;
        const vx = gestureState.vx;

        if (currentOffset.current === 0) {
          // البطاقة كانت مغلقة
          if (dx < -35 || vx < -0.4) {
            // سحب لليسار: فتح سلة الحذف في الجانب الأيمن المكشوف
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
            Animated.spring(translateX, {
              toValue: -REVEAL_WIDTH,
              bounciness: 4,
              useNativeDriver: true,
            }).start();
            currentOffset.current = -REVEAL_WIDTH;
          } else if (dx > 35 || vx > 0.4) {
            // سحب لليمين: فتح سلة الحذف في الجانب الأيسر المكشوف
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
            Animated.spring(translateX, {
              toValue: REVEAL_WIDTH,
              bounciness: 4,
              useNativeDriver: true,
            }).start();
            currentOffset.current = REVEAL_WIDTH;
          } else {
            // سحب خفيف: يعود لمكانه
            closeSwipe();
          }
        } else if (currentOffset.current === -REVEAL_WIDTH) {
          // كانت مفتوحة بالسحب لليسار، وسحب يميناً لإغلاقها
          if (dx > 25 || vx > 0.3) {
            closeSwipe();
          } else {
            Animated.spring(translateX, {
              toValue: -REVEAL_WIDTH,
              bounciness: 4,
              useNativeDriver: true,
            }).start();
          }
        } else if (currentOffset.current === REVEAL_WIDTH) {
          // كانت مفتوحة بالسحب لليمين، وسحب يساراً لإغلاقها
          if (dx < -25 || vx < -0.3) {
            closeSwipe();
          } else {
            Animated.spring(translateX, {
              toValue: REVEAL_WIDTH,
              bounciness: 4,
              useNativeDriver: true,
            }).start();
          }
        }
      },
    })
  );

  // Layer is invisible at rest so the card's rounded corners never show red underneath.
  const deleteLayerOpacity = translateX.interpolate({
    inputRange: [-REVEAL_WIDTH, -12, 0, 12, REVEAL_WIDTH],
    outputRange: [1, 0.35, 0, 0.35, 1],
    extrapolate: 'clamp',
  });

  // Why transforms instead of left/right: under forced RTL, Yoga swaps left/right while
  // transforms and gesture dx stay physical. Centering the icon inside the exposed gap via
  // translateX keeps it aligned regardless of I18nManager state.
  //   card moved left  (x < 0) → gap is [W + x, W]  → offset from center = W/2 + x/2
  //   card moved right (x > 0) → gap is [0, x]      → offset from center = x/2 - W/2
  const maxTravel = REVEAL_WIDTH + 25;
  const halfWidth = cardWidth / 2;
  const deleteIconTranslateX = translateX.interpolate({
    inputRange: [-maxTravel, -0.01, 0.01, maxTravel],
    outputRange: [
      halfWidth - maxTravel / 2,
      halfWidth,
      -halfWidth,
      maxTravel / 2 - halfWidth,
    ],
    extrapolate: 'clamp',
  });

  // ألوان مصمتة 100% نقية
  const cardBgColor = isSelected
    ? isDark
      ? '#12372F'
      : '#ECFDF5'
    : !item.read
      ? isDark
        ? '#132524'
        : '#F4FDF7'
      : isDark
        ? '#1A2232'
        : '#FFFFFF';

  const cardBorderColor = isSelected
    ? colors.primary
    : !item.read
      ? colors.primary + '55'
      : isDark
        ? '#2B384E'
        : '#E2E8F0';

  return (
    <Animated.View
      style={[
        styles.outerWrap,
        {
          opacity: itemOpacity,
          transform: [{ scaleY: itemHeight }],
        },
      ]}
      onLayout={handleWrapLayout}
    >
      {/* طبقة الحذف: بعرض البطاقة كاملاً، والبطاقة تغطيها إلا الفراغ الذي يكشفه السحب */}
      {!isSelectionMode && cardWidth > 0 && (
        <Animated.View style={[styles.deleteLayer, { opacity: deleteLayerOpacity }]}>
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={executeDelete}
            style={styles.deleteLayerTouchable}
            accessibilityLabel="حذف الإشعار"
          >
            <Animated.View
              style={[
                styles.deleteIconGroup,
                { transform: [{ translateX: deleteIconTranslateX }] },
              ]}
            >
              <Ionicons name="trash" size={24} color="#FFFFFF" />
              <ThemedText style={styles.deleteActionLabel}>حذف</ThemedText>
            </Animated.View>
          </TouchableOpacity>
        </Animated.View>
      )}

      {/* بطاقة الإشعار الرئيسية القابلة للسحب */}
      <Animated.View
        {...panResponder.panHandlers}
        style={[
          styles.cardContainer,
          {
            transform: [{ translateX }],
          },
        ]}
      >
        <TouchableOpacity
          accessible={!showInvitationResponse}
          activeOpacity={isSelectionMode ? 0.75 : 0.92}
          onPress={() => {
            // إذا كانت السلة مكشوفة، أي نقرة على البطاقة تعيدها لمكانها فوراً
            if (currentOffset.current !== 0) {
              closeSwipe();
              return;
            }

            if (isSelectionMode) {
              Haptics.selectionAsync().catch(() => {});
              onToggleSelect();
            } else {
              onToggleExpand();
            }
          }}
          onLongPress={() => {
            if (currentOffset.current !== 0) {
              closeSwipe();
              return;
            }
            if (!isSelectionMode) {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
              onToggleSelect();
            } else {
              onShare();
            }
          }}
          style={[
            styles.card,
            {
              backgroundColor: cardBgColor,
              borderColor: cardBorderColor,
            },
            !item.read && !isInvitation && styles.unreadCardBorder,
            isInvitation && styles.invitationCard,
            isSelected && styles.selectedCardBorder,
          ]}
        >
          {/* الرأس: صندوق التحديد + الأيقونة + العنوان + زر القراءة */}
          <View style={styles.cardHeader}>
            {isSelectionMode && (
              <View
                style={[
                  styles.checkboxWrap,
                  isSelected
                    ? { backgroundColor: colors.primary, borderColor: colors.primary }
                    : { borderColor: isDark ? '#4B5563' : '#CBD5E1', backgroundColor: 'transparent' },
                ]}
              >
                {isSelected && (
                  <Ionicons name="checkmark" size={13} color="#FFFFFF" />
                )}
              </View>
            )}

            <View style={[styles.iconWrap, { backgroundColor: accentColor + '18' }]}>
              <IconSymbol name={iconName} size={20} color={accentColor} />
            </View>

            <View style={styles.headerTextWrap}>
              <View style={styles.titleRow}>
                <ThemedText
                  style={[
                    styles.cardTitle,
                    item.read && styles.cardTitleRead,
                    isInvitation && styles.invitationText,
                    isInvitation && styles.invitationTitle,
                    { color: colors.text },
                  ]}
                  numberOfLines={isExpanded ? undefined : 1}
                >
                  {item.title}
                </ThemedText>
              </View>

              <View style={styles.timeRow}>
                <IconSymbol name="clock.fill" size={11} color={colors.icon} />
                <ThemedText style={[styles.timeAgo, { color: colors.icon }]}>
                  {item.timeAgo || formattedTime}
                </ThemedText>
              </View>
            </View>

            {/* مؤشر القراءة / غير مقروء */}
            {!isSelectionMode && (
              <TouchableOpacity
                activeOpacity={0.7}
                onPress={(e) => {
                  e.stopPropagation?.();
                  if (currentOffset.current !== 0) {
                    closeSwipe();
                    return;
                  }
                  onToggleRead();
                }}
                hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                style={[
                  styles.readToggleBtn,
                  !item.read
                    ? { backgroundColor: colors.primary + '18', borderColor: colors.primary + '40' }
                    : { backgroundColor: isDark ? 'rgba(255,255,255,0.06)' : '#F1F5F9', borderColor: 'transparent' },
                ]}
                accessibilityLabel={item.read ? 'تحديد كغير مقروء' : 'تحديد كمقروء'}
              >
                {!item.read ? (
                  <View style={[styles.unreadDotInner, { backgroundColor: colors.primary }]} />
                ) : (
                  <IconSymbol
                    name="checkmark.circle.fill"
                    size={15}
                    color={isDark ? '#64748B' : '#94A3B8'}
                  />
                )}
              </TouchableOpacity>
            )}
          </View>

          {/* نص الإشعار */}
          <ThemedText
            style={[
              styles.cardBody,
              { color: isDark ? '#CBD5E1' : '#334155' },
              item.read && styles.cardBodyRead,
              isInvitation && styles.invitationText,
            ]}
            numberOfLines={isExpanded ? undefined : 2}
            selectable={isExpanded && !isSelectionMode}
          >
            {item.body}
          </ThemedText>

          {showInvitationResponse && (
            savedResponse ? (
              <ThemedText
                style={[styles.invitationStatus, styles.invitationText, { color: savedResponse === 'accepted' ? colors.primary : declineColor }]}
                accessibilityLiveRegion="polite"
              >
                {savedResponse === 'accepted' ? 'تم القبول' : 'تم الرفض'}
              </ThemedText>
            ) : (
              <View style={styles.invitationSection}>
                <View style={styles.invitationActions}>
                  {INVITATION_ACTIONS.map(({ value, label, accessibilityLabel }) => {
                    const color = value === 'accepted' ? colors.primary : declineColor;
                    return (
                      <TouchableOpacity
                        key={value}
                        accessibilityRole="button"
                        accessibilityLabel={accessibilityLabel}
                        accessibilityState={{ disabled: invitationResponsePending, busy: invitationResponsePending }}
                        disabled={invitationResponsePending}
                        onPress={(event) => {
                          event.stopPropagation?.();
                          if (currentOffset.current !== 0) {
                            closeSwipe();
                            return;
                          }
                          onInvitationResponse?.(value);
                        }}
                        style={[
                          styles.invitationButton,
                          { borderColor: color, backgroundColor: color + '14' },
                          invitationResponsePending && styles.pendingInvitationButton,
                        ]}
                      >
                        <ThemedText style={[styles.invitationButtonLabel, { color }]}>{label}</ThemedText>
                      </TouchableOpacity>
                    );
                  })}
                </View>
                {invitationResponsePending && (
                  <ThemedText style={[styles.invitationStatus, styles.invitationText, { color: colors.icon }]} accessibilityLiveRegion="polite">
                    جارٍ حفظ الرد…
                  </ThemedText>
                )}
              </View>
            )
          )}

          {/* زر عرض تفاصيل الإشعار / إخفاء التفاصيل للنصوص الطويلة */}
          {isLongText && !isSelectionMode && (
            <TouchableOpacity
              activeOpacity={0.7}
              onPress={(e) => {
                e.stopPropagation?.();
                if (currentOffset.current !== 0) {
                  closeSwipe();
                  return;
                }
                onToggleExpand();
              }}
              style={styles.expandToggleBtn}
            >
              <ThemedText
                style={[
                  styles.expandToggleText,
                  { color: isExpanded ? colors.icon : colors.primary },
                ]}
              >
                {isExpanded ? 'إخفاء تفاصيل الإشعار ▴' : 'عرض تفاصيل الإشعار كاملة ▾'}
              </ThemedText>
            </TouchableOpacity>
          )}

          {/* استعراض الصورة إن وجدت */}
          {hasImage && !isExpanded && (
            <TouchableOpacity
              activeOpacity={0.9}
              disabled={isSelectionMode}
              onPress={(e) => {
                e.stopPropagation?.();
                if (currentOffset.current !== 0) {
                  closeSwipe();
                  return;
                }
                onImagePress(item.mediaUrl!);
              }}
              style={styles.compactImageWrap}
            >
              <Image
                source={{ uri: item.mediaUrl! }}
                style={styles.compactImage}
                contentFit="cover"
                transition={200}
              />
              <View style={styles.imageZoomOverlay}>
                <IconSymbol name="magnifyingglass" size={11} color="#FFFFFF" />
                <ThemedText style={styles.imageZoomText}>تكبير الصورة</ThemedText>
              </View>
            </TouchableOpacity>
          )}

          {hasImage && isExpanded && (
            <TouchableOpacity
              activeOpacity={0.92}
              disabled={isSelectionMode}
              onPress={(e) => {
                e.stopPropagation?.();
                if (currentOffset.current !== 0) {
                  closeSwipe();
                  return;
                }
                onImagePress(item.mediaUrl!);
              }}
              style={styles.expandedImageWrap}
            >
              <Image
                source={{ uri: item.mediaUrl! }}
                style={styles.expandedImage}
                contentFit="cover"
                transition={200}
              />
              <View style={styles.expandedImageBadge}>
                <IconSymbol name="magnifyingglass" size={13} color="#FFFFFF" />
                <ThemedText style={styles.expandedImageBadgeText}>
                  اضغط لعرض الصورة بحجم كامل
                </ThemedText>
              </View>
            </TouchableOpacity>
          )}

          {/* مقطع فيديو مرفق */}
          {hasVideo && (
            <TouchableOpacity
              activeOpacity={0.85}
              disabled={isSelectionMode}
              onPress={(e) => {
                e.stopPropagation?.();
                if (currentOffset.current !== 0) {
                  closeSwipe();
                  return;
                }
                if (item.mediaUrl) WebBrowser.openBrowserAsync(item.mediaUrl);
              }}
              style={[styles.mediaPill, { backgroundColor: '#06B6D415', borderColor: '#06B6D433' }]}
            >
              <IconSymbol name="play.circle.fill" size={15} color="#06B6D4" />
              <ThemedText style={[styles.mediaPillText, { color: '#06B6D4' }]}>
                مقطع فيديو مرفق (اضغط للمشاهدة)
              </ThemedText>
              <IconSymbol name="arrow.up.right.square.fill" size={13} color="#06B6D4" />
            </TouchableOpacity>
          )}

          {/* زر الإجراء المباشر التفاعلي إن وُجد */}
          {action && (
            <TouchableOpacity
              activeOpacity={0.8}
              disabled={isSelectionMode}
              onPress={(e) => {
                e.stopPropagation?.();
                if (currentOffset.current !== 0) {
                  closeSwipe();
                  return;
                }
                onExecuteAction(action);
              }}
              style={[
                styles.directActionPill,
                {
                  backgroundColor: colors.primary + '14',
                  borderColor: colors.primary + '38',
                },
              ]}
            >
              <View style={styles.directActionContent}>
                <IconSymbol name={action.icon} size={16} color={colors.primary} />
                <ThemedText style={[styles.directActionLabel, { color: colors.primary }]}>
                  {action.label}
                </ThemedText>
              </View>
              <View style={[styles.directActionArrow, { backgroundColor: colors.primary }]}>
                <IconSymbol name="chevron.left" size={11} color="#FFFFFF" />
              </View>
            </TouchableOpacity>
          )}

          {/* تفاصيل التوسيع: التاريخ والمشاركة */}
          {isExpanded && (
            <View style={styles.expandedFooter}>
              {formattedTime ? (
                <View style={styles.fullDateRow}>
                  <IconSymbol name="calendar" size={12} color={colors.icon} />
                  <ThemedText style={[styles.fullDateText, { color: colors.icon }]}>
                    {formattedTime}
                  </ThemedText>
                </View>
              ) : null}

              <View style={styles.expandedActionsRow}>
                <TouchableOpacity
                  activeOpacity={0.7}
                  onPress={(e) => {
                    e.stopPropagation?.();
                    onShare();
                  }}
                  style={[
                    styles.footerActionBtn,
                    { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F1F5F9' },
                  ]}
                >
                  <IconSymbol name="square.and.arrow.up" size={13} color={colors.text} />
                  <ThemedText style={[styles.footerActionText, { color: colors.text }]}>
                    مشاركة الإشعار
                  </ThemedText>
                </TouchableOpacity>

                <TouchableOpacity
                  activeOpacity={0.7}
                  onPress={(e) => {
                    e.stopPropagation?.();
                    onToggleExpand();
                  }}
                  style={[
                    styles.footerActionBtn,
                    { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : '#F1F5F9' },
                  ]}
                >
                  <IconSymbol name="chevron.up" size={13} color={colors.icon} />
                  <ThemedText numberOfLines={1} style={[styles.footerActionText, { color: colors.icon }]}>
                    طي الإشعار
                  </ThemedText>
                </TouchableOpacity>
              </View>
            </View>
          )}
        </TouchableOpacity>
      </Animated.View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  outerWrap: {
    marginBottom: 11,
    position: 'relative',
    overflow: 'hidden',
  },
  deleteLayer: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: '#EF4444',
    borderRadius: 18,
    overflow: 'hidden',
  },
  deleteLayerTouchable: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  deleteIconGroup: {
    alignItems: 'center',
    gap: 3,
  },
  deleteActionLabel: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  cardContainer: {
    width: '100%',
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    padding: 15,
    position: 'relative',
    ...Platform.select({
      ios: { shadowColor: '#000', shadowOffset: { width: 0, height: 1.5 }, shadowOpacity: 0.05, shadowRadius: 5 },
      android: { elevation: 1.5 },
    }),
  },
  unreadCardBorder: {
    borderRightWidth: 4,
  },
  invitationCard: {
    direction: 'rtl',
    overflow: 'hidden',
  },
  invitationText: {
    // Keep Android from interpreting right alignment relative to the RTL card layout.
    ...(Platform.OS === 'android' ? { direction: 'ltr' } : {}),
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  invitationTitle: {
    flex: 1,
  },
  invitationSection: {
    marginTop: 10,
  },
  invitationActions: {
    flexDirection: 'row',
    gap: 8,
  },
  invitationButton: {
    minHeight: 44,
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    borderWidth: 1,
    paddingHorizontal: 8,
  },
  pendingInvitationButton: {
    opacity: 0.55,
  },
  invitationButtonLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  invitationStatus: {
    marginTop: 8,
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  selectedCardBorder: {
    borderWidth: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
    gap: 11,
  },
  checkboxWrap: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconWrap: {
    width: 42,
    height: 42,
    borderRadius: 21,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTextWrap: {
    flex: 1,
    justifyContent: 'center',
    gap: 2,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  cardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15.5,
    textAlign: 'right',
  },
  cardTitleRead: {
    fontFamily: 'Tajawal-Medium',
    opacity: 0.88,
  },
  timeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  timeAgo: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Regular',
  },
  readToggleBtn: {
    width: 34,
    height: 34,
    borderRadius: 17,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
  },
  unreadDotInner: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  cardBody: {
    fontSize: 13.5,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 21,
    marginBottom: 10,
    textAlign: 'right',
  },
  cardBodyRead: {
    opacity: 0.85,
  },
  expandToggleBtn: {
    alignSelf: 'flex-start',
    paddingVertical: 4,
    paddingHorizontal: 2,
    marginBottom: 8,
  },
  expandToggleText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
  },
  compactImageWrap: {
    height: 110,
    borderRadius: 14,
    overflow: 'hidden',
    marginTop: 6,
    marginBottom: 4,
    position: 'relative',
    backgroundColor: 'rgba(0,0,0,0.04)',
  },
  compactImage: {
    width: '100%',
    height: '100%',
  },
  imageZoomOverlay: {
    position: 'absolute',
    bottom: 8,
    left: 8,
    backgroundColor: 'rgba(0,0,0,0.65)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 9,
    paddingVertical: 4,
    borderRadius: 10,
  },
  imageZoomText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  expandedImageWrap: {
    height: 190,
    borderRadius: 14,
    overflow: 'hidden',
    marginTop: 8,
    marginBottom: 6,
    position: 'relative',
    backgroundColor: 'rgba(0,0,0,0.04)',
  },
  expandedImage: {
    width: '100%',
    height: '100%',
  },
  expandedImageBadge: {
    position: 'absolute',
    bottom: 8,
    left: 8,
    backgroundColor: 'rgba(0,0,0,0.7)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 10,
  },
  expandedImageBadgeText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  mediaPill: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    marginTop: 4,
  },
  mediaPillText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  directActionPill: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1,
    marginTop: 2,
    marginBottom: 4,
  },
  directActionContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  directActionLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  directActionArrow: {
    width: 20,
    height: 20,
    borderRadius: 10,
    justifyContent: 'center',
    alignItems: 'center',
  },
  expandedFooter: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(150, 150, 150, 0.2)',
    gap: 8,
  },
  fullDateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  fullDateText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  expandedActionsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 8,
    marginTop: 4,
  },
  footerActionBtn: {
    flexDirection: 'row',
    flexShrink: 0,
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  footerActionText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
});
