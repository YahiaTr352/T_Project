import React, { useState, useRef, useCallback, useMemo } from 'react';
import {
  StyleSheet,
  View,
  Text,
  TouchableOpacity,
  ScrollView,
  useColorScheme,
  Animated,
  PanResponder,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { silaTheme } from '@/constants/theme';
import { formatEnglishNumber, formatSteps } from '@/lib/utils/numberFormat';
import type {
  TrekCurrentSegment,
  WalkingTrekInfo,
  GroupTrekProgress,
  TrekViewMode,
} from '@/lib/api/walkingTrekApi';

/** إحصائيات النطاق المعروض حالياً (الكل) */
export interface TrekScopeStats {
  totalKm: number;
  totalSteps: number;
  progressPercentage: number;
  label: string;
}

interface Props {
  trek: WalkingTrekInfo;
  /** المرحلة الحالية للنطاق المعروض */
  currentSegment: TrekCurrentSegment | null;
  scopeStats: TrekScopeStats;
  viewMode?: TrekViewMode;
  onToggleViewMode?: (mode: TrekViewMode) => void;
  groups?: GroupTrekProgress[];
  selectedGroupId?: number | null;
  onSelectGroup?: (groupId: number) => void;
  onOpenWaypoints: () => void;
  onOpenContributors: () => void;
}

const SCOPE_COLORS: Record<TrekViewMode, string> = {
  community: '#10b981',
  group: '#8b5cf6',
  personal: '#3b82f6',
};

export function SaudiTrekHUD({
  trek,
  currentSegment,
  scopeStats,
  viewMode = 'community',
  onOpenWaypoints,
  onOpenContributors,
}: Props) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const insets = useSafeAreaInsets();

  const hudBottomPadding = Math.max(insets.bottom, 16) + 12;

  const isPersonal = viewMode === 'personal';
  const accent = SCOPE_COLORS[viewMode];
  const activeSegment = currentSegment;
  const mutedColor = isDark ? '#9CA3AF' : '#6B7280';
  const strongColor = isDark ? '#FFFFFF' : '#111827';

  // الرسوم المتحركة وإيماءات السحب (Bottom Sheet Gesture Engine)
  const translateY = useRef(new Animated.Value(0)).current;
  const currentOffset = useRef(0);
  const isCollapsedRef = useRef(false);
  const [sheetHeight, setSheetHeight] = useState(420);

  // الارتفاع الظاهر في وضع الطي (المقبض + العنوان والمسافة)
  const VISIBLE_PEEK = 66 + Math.max(insets.bottom, 12);
  const maxTranslateY = Math.max(120, sheetHeight - VISIBLE_PEEK);

  const snapTo = useCallback(
    (targetValue: number) => {
      isCollapsedRef.current = targetValue > 0;
      currentOffset.current = targetValue;
      try {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      } catch {}
      Animated.spring(translateY, {
        toValue: targetValue,
        tension: 70,
        friction: 11,
        useNativeDriver: true,
      }).start();
    },
    [translateY]
  );

  const handleToggle = useCallback(() => {
    if (isCollapsedRef.current) {
      snapTo(0);
    } else {
      snapTo(maxTranslateY);
    }
  }, [maxTranslateY, snapTo]);

  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => false,
        onMoveShouldSetPanResponder: (_, gestureState) => Math.abs(gestureState.dy) > 4,
        onPanResponderGrant: () => {
          translateY.stopAnimation((val) => {
            currentOffset.current = val;
          });
        },
        onPanResponderMove: (_, gestureState) => {
          let nextValue = currentOffset.current + gestureState.dy;
          // مقاومة فيزيائية عند السحب لأعلى متجاوزاً نقطة التمدد القصوى
          if (nextValue < 0) {
            nextValue = nextValue * 0.2;
          } else if (nextValue > maxTranslateY) {
            nextValue = maxTranslateY + (nextValue - maxTranslateY) * 0.2;
          }
          translateY.setValue(nextValue);
        },
        onPanResponderRelease: (_, gestureState) => {
          const { dy, vy } = gestureState;
          const projectedY = currentOffset.current + dy;

          // سحب لأسفل بقوة أو لمسافة كافية -> طي وإخفاء اللوحة
          if (vy > 0.4 || dy > 50 || projectedY > maxTranslateY * 0.45) {
            snapTo(maxTranslateY);
            return;
          }

          // سحب لأعلى بقوة أو مسافة كافية -> فتح وتكبير اللوحة
          if (vy < -0.4 || dy < -50 || projectedY < maxTranslateY * 0.45) {
            snapTo(0);
            return;
          }

          // الاستقرار في الوضع الأقرب
          if (projectedY > maxTranslateY * 0.5) {
            snapTo(maxTranslateY);
          } else {
            snapTo(0);
          }
        },
      }),
    [maxTranslateY, snapTo, translateY]
  );

  const chevronRotation = translateY.interpolate({
    inputRange: [0, Math.max(1, maxTranslateY)],
    outputRange: ['180deg', '0deg'],
    extrapolate: 'clamp',
  });

  return (
    <Animated.View
      style={[
        styles.container,
        isDark && styles.containerDark,
        { paddingBottom: hudBottomPadding },
        { transform: [{ translateY }] },
      ]}
      onLayout={(e) => {
        const h = e.nativeEvent.layout.height;
        if (h > 120 && Math.abs(h - sheetHeight) > 10) {
          setSheetHeight(h);
        }
      }}
    >
      {/* 1. منطقة المقبض والعنوان التفاعلية القابلة للسحب والإيماءات */}
      <View style={styles.dragHeader} {...panResponder.panHandlers}>
        <View style={styles.handleContainer}>
          <View style={[styles.handleBar, isDark && styles.handleBarDark]} />
        </View>

        <TouchableOpacity
          activeOpacity={0.8}
          onPress={handleToggle}
          style={styles.headerInfoRow}
        >
          <View style={styles.headerTitleBlock}>
            <View style={styles.headerTitleRow}>
              <MaterialCommunityIcons name="routes" size={17} color={accent} />
              <Text style={[styles.hudHeaderTitle, { color: strongColor }]} numberOfLines={1}>
                {trek.title}
              </Text>
            </View>
            <Text style={[styles.hudHeaderSubtitle, { color: mutedColor }]} numberOfLines={1}>
              المسافة المقطوعة: {formatEnglishNumber(Number(scopeStats.totalKm) || 0, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} كم • {formatEnglishNumber(scopeStats.progressPercentage, { maximumFractionDigits: 0 })}%
            </Text>
          </View>

          <Animated.View
            style={[
              styles.expandBadge,
              isDark && styles.expandBadgeDark,
              { transform: [{ rotate: chevronRotation }] },
            ]}
          >
            <Ionicons name="chevron-up" size={18} color={isDark ? '#6EE7B7' : '#059669'} />
          </Animated.View>
        </TouchableOpacity>
      </View>

      {/* بيانات المرحلة والمسافة */}
      {activeSegment ? (
        <View style={styles.stageSection}>
          <View style={styles.stageHeaderRow}>
            <View style={[styles.stageBadge, isPersonal && styles.stageBadgePersonal, isDark && styles.stageBadgeDark]}>
              <Text style={[styles.stageBadgeText, { color: isDark ? '#FFFFFF' : '#111827' }]}>
                {isPersonal ? 'مرحلتي الشخصية' : `المرحلة ${formatEnglishNumber(activeSegment.segment_index)} من ${formatEnglishNumber(activeSegment.total_segments)}`}
              </Text>
            </View>
            <Text style={[styles.stageDistanceText, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
              متبقي للوصول:{' '}
              <Text style={isPersonal ? styles.highlightBlue : styles.highlightGreen}>
                {formatEnglishNumber(activeSegment.segment_remaining_km, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} كم ({formatSteps(Math.round(activeSegment.segment_remaining_km / 0.00075))} خطوة)
              </Text>
            </Text>
          </View>

          <View style={styles.citiesRow}>
            <View style={styles.cityCol}>
              <Text style={[styles.cityLabel, { color: isDark ? '#FFFFFF' : '#111827' }]}>
                {activeSegment.from_city}
              </Text>
              <Text style={[styles.locationSubtitle, { color: isDark ? '#9CA3AF' : '#6B7280' }]} numberOfLines={1}>
                {activeSegment.from_location}
              </Text>
            </View>

            <View style={styles.arrowCol}>
              <Ionicons
                name={isPersonal ? 'walk' : 'arrow-back'}
                size={16}
                color={isPersonal ? '#3b82f6' : '#0284c7'}
              />
              <Text
                style={[
                  styles.segWalkedText,
                  { color: isPersonal ? '#3b82f6' : '#0284c7' },
                ]}
              >
                {formatEnglishNumber(activeSegment.segment_walked_km, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} كم
              </Text>
            </View>

            <View style={[styles.cityCol, styles.cityColEnd]}>
              <Text
                style={[
                  styles.cityLabel,
                  styles.cityLabelTarget,
                ]}
              >
                {activeSegment.to_city}
              </Text>
              <Text style={[styles.locationSubtitle, { color: isDark ? '#9CA3AF' : '#6B7280' }]} numberOfLines={1}>
                {activeSegment.to_location}
              </Text>
            </View>
          </View>

          {/* Segment Progress Bar */}
          <View style={styles.progressBarWrapper}>
            <View style={[styles.progressBarBackground, isDark && styles.progressBarBackgroundDark]}>
              <View
                style={[
                  styles.progressBarFill,
                  { backgroundColor: isPersonal ? '#3b82f6' : '#0284c7' },
                  {
                    width: `${Math.min(
                      100,
                      Math.max(0, activeSegment.segment_progress_percentage)
                    )}%`,
                  },
                ]}
              />
            </View>
            <Text
              style={[
                styles.progressPercentageText,
                isPersonal && { color: '#3b82f6' },
              ]}
            >
              {formatEnglishNumber(activeSegment.segment_progress_percentage, { maximumFractionDigits: 0 })}%
            </Text>
          </View>
        </View>
      ) : (
        <View style={styles.stageSection}>
          <View style={styles.initialLocationRow}>
            <Ionicons
              name={(trek.waypoints_count ?? 0) <= 1 ? 'location-sharp' : 'trophy'}
              size={22}
              color={silaTheme.gold}
            />
            <Text style={[styles.cityLabel, { color: isDark ? '#FFFFFF' : '#111827', marginHorizontal: 8 }]}>
              {(trek.waypoints_count ?? 0) <= 1
                ? 'نقطة الانطلاق جاهزة على الخريطة'
                : 'تم إتمام الطواف الكامل بحمد الله!'}
            </Text>
          </View>
          {(trek.waypoints_count ?? 0) <= 1 && (
            <Text style={[styles.locationSubtitle, { textAlign: 'center', marginTop: 4, color: isDark ? '#9CA3AF' : '#6B7280' }]}>
              {trek.title} • بانتظار إضافة محطات إضافية لرسم المسار المتصل بين المدن
            </Text>
          )}
        </View>
      )}

      {/* 3. شريط ملخص الأرقام: المسافة الفعلية | إجمالي الخطوات | نسبة الإنجاز للنطاق المعروض */}
      <View
        style={[
          styles.communityRibbon,
          isDark && styles.communityRibbonDark,
          { borderColor: `${accent}33` },
        ]}
      >
        <View style={styles.ribbonItem}>
          <Text style={[styles.ribbonValue, { color: accent }]}>
            {formatEnglishNumber(Number(scopeStats.totalKm) || 0, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} كم
          </Text>
          <Text style={[styles.ribbonLabel, { color: mutedColor }]}>
            المسافة المقطوعة
          </Text>
        </View>
        <View style={[styles.ribbonDivider, isDark && styles.ribbonDividerDark]} />
        <View style={styles.ribbonItem}>
          <Text style={[styles.ribbonValue, { color: strongColor }]}>
            {formatSteps(Number(scopeStats.totalSteps) || 0)}
          </Text>
          <Text style={[styles.ribbonLabel, { color: mutedColor }]}>إجمالي الخطوات</Text>
        </View>
        <View style={[styles.ribbonDivider, isDark && styles.ribbonDividerDark]} />
        <View style={styles.ribbonItem}>
          <Text style={[styles.ribbonValue, { color: silaTheme.gold }]}>
            {formatEnglishNumber(Number(scopeStats.progressPercentage) || 0, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%
          </Text>
          <Text style={[styles.ribbonLabel, { color: mutedColor }]}>نسبة إنجاز الرحلة</Text>
        </View>
      </View>
      {/* 4. أزرار الإجراءات السريعة (محطات المسار ولوحة الشرف) */}
      <View style={styles.actionsRow}>
        <TouchableOpacity
          style={[
            styles.actionBtn,
            styles.actionBtnSecondary,
            isDark && styles.actionBtnSecondaryDark,
          ]}
          onPress={onOpenWaypoints}
          activeOpacity={0.7}
        >
          <Ionicons
            name="trail-sign"
            size={16}
            color={isDark ? '#E5E7EB' : '#374151'}
            style={{ marginRight: 6 }}
          />
          <Text style={[styles.actionBtnText, { color: isDark ? '#E5E7EB' : '#374151' }]}>
            محطات المسار ({trek.waypoints_count})
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionBtn, styles.actionBtnPrimary]}
          onPress={onOpenContributors}
          activeOpacity={0.8}
        >
          <Ionicons
            name="trophy"
            size={16}
            color="#FFFFFF"
            style={{ marginRight: 6 }}
          />
          <Text style={styles.actionBtnTextPrimary}>ترتيب المتصدرين</Text>
        </TouchableOpacity>
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(255, 255, 255, 0.98)',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingTop: 6,
    paddingHorizontal: 16,
    paddingBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -6 },
    shadowOpacity: 0.12,
    shadowRadius: 12,
    elevation: 16,
  },
  containerDark: {
    backgroundColor: '#181E29',
    borderTopColor: '#2E384D',
    borderTopWidth: 1,
  },
  dragHeader: {
    paddingTop: 4,
    paddingBottom: 8,
  },
  handleContainer: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  handleBar: {
    width: 44,
    height: 4.5,
    backgroundColor: '#d1d5db',
    borderRadius: 2.5,
    alignSelf: 'center',
  },
  handleBarDark: {
    backgroundColor: '#4B5563',
  },
  headerInfoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 4,
    paddingHorizontal: 2,
  },
  headerTitleBlock: {
    flex: 1,
    marginRight: 10,
  },
  headerTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  hudHeaderTitle: {
    fontSize: 14.5,
    fontFamily: 'Tajawal-Bold',
  },
  hudHeaderSubtitle: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  expandBadge: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: 'rgba(5, 150, 105, 0.12)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  expandBadgeDark: {
    backgroundColor: 'rgba(5, 150, 105, 0.2)',
  },
  toggleContainer: {
    flexDirection: 'row',
    backgroundColor: '#f3f4f6',
    borderRadius: 14,
    padding: 3,
    marginBottom: 10,
  },
  toggleContainerDark: {
    backgroundColor: '#242A36',
  },
  toggleBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 8,
    borderRadius: 11,
  },
  toggleBtnActive: {
    backgroundColor: '#ffffff',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 3,
    elevation: 3,
  },
  toggleBtnActiveDark: {
    backgroundColor: '#2E384D',
  },
  toggleBtnText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
  },
  toggleBtnTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  groupChipsScroll: {
    marginBottom: 10,
    maxHeight: 40,
  },
  groupChipsContent: {
    gap: 8,
    paddingHorizontal: 2,
  },
  groupChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
    gap: 6,
  },
  groupChipDark: {
    backgroundColor: '#242A36',
    borderColor: '#374151',
  },
  groupChipText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    maxWidth: 120,
  },
  groupChipKm: {
    fontSize: 10.5,
    fontFamily: 'Tajawal-Medium',
  },
  stageSection: {
    marginBottom: 10,
  },
  stageHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  stageBadge: {
    backgroundColor: 'rgba(5, 150, 105, 0.12)',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 8,
  },
  stageBadgePersonal: {
    backgroundColor: 'rgba(59, 130, 246, 0.12)',
  },
  stageBadgeDark: {
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderWidth: 1,
  },
  stageBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  stageDistanceText: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Medium',
  },
  highlightGreen: {
    color: '#10b981',
    fontWeight: '700',
  },
  highlightBlue: {
    color: '#3b82f6',
    fontWeight: '700',
  },
  citiesRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  cityCol: {
    flex: 1,
  },
  cityColEnd: {
    alignItems: 'flex-end',
  },
  cityLabel: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
  },
  cityLabelTarget: {
    color: silaTheme.gold,
  },
  locationSubtitle: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginTop: 1,
  },
  arrowCol: {
    alignItems: 'center',
    paddingHorizontal: 8,
  },
  segWalkedText: {
    fontSize: 10.5,
    fontFamily: 'Tajawal-Medium',
    color: '#10b981',
    marginTop: 2,
  },
  progressBarWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  progressBarBackground: {
    flex: 1,
    height: 7,
    backgroundColor: '#e5e7eb',
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBarBackgroundDark: {
    backgroundColor: '#374151',
  },
  progressBarFill: {
    height: '100%',
    backgroundColor: '#10b981',
    borderRadius: 4,
  },
  progressPercentageText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#10b981',
    minWidth: 30,
    textAlign: 'left',
  },
  initialLocationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: 4,
  },
  communityRibbon: {
    flexDirection: 'row',
    backgroundColor: '#f9fafb',
    borderRadius: 14,
    paddingVertical: 8,
    paddingHorizontal: 12,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#f3f4f6',
  },
  communityRibbonDark: {
    backgroundColor: '#242A36',
    borderColor: '#2E384D',
  },
  communityRibbonPersonal: {
    borderColor: 'rgba(59, 130, 246, 0.2)',
  },
  shareBadge: {
    flexDirection: 'row',
    backgroundColor: 'rgba(245, 158, 11, 0.12)',
    borderRadius: 10,
    paddingVertical: 5,
    paddingHorizontal: 10,
    marginBottom: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  shareBadgeDark: {
    backgroundColor: 'rgba(245, 158, 11, 0.18)',
  },
  shareBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#d97706',
  },
  ribbonItem: {
    flex: 1,
    alignItems: 'center',
  },
  ribbonValue: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  ribbonLabel: {
    fontSize: 9.5,
    fontFamily: 'Tajawal-Regular',
    marginTop: 1,
    textAlign: 'center',
  },
  ribbonDivider: {
    width: 1,
    height: '70%',
    backgroundColor: '#e5e7eb',
    alignSelf: 'center',
  },
  ribbonDividerDark: {
    backgroundColor: '#374151',
  },
  actionsRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 10,
  },
  actionBtn: {
    flex: 1,
    height: 48,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 3,
  },
  actionBtnPrimary: {
    backgroundColor: silaTheme.primary,
  },
  actionBtnSecondary: {
    backgroundColor: '#f3f4f6',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  actionBtnSecondaryDark: {
    backgroundColor: '#242A36',
    borderColor: '#374151',
  },
  actionBtnTextPrimary: {
    color: '#ffffff',
    fontSize: 12.5,
    fontFamily: 'Tajawal-Bold',
  },
  actionBtnText: {
    fontSize: 12.5,
    fontFamily: 'Tajawal-Bold',
  },
});
