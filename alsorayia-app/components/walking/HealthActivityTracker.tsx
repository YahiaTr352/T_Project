import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, silaTheme } from '@/constants/theme';
import type { WalkingRecord } from '@/lib/api/walkingApi';
import {
  computeDaySummary,
  computeMonthSummary,
  computeWeekSummary,
  computeYearSummary,
  navigatePeriod,
  canNavigateForward,
  isCurrentPeriod,
  PERIOD_TABS,
  ARABIC_MONTHS,
  type ActivityPeriod,
} from '@/lib/walking/activityAnalytics';
import { formatSteps } from '@/lib/walking/formatters';
import type { WalkingLedger } from '@/lib/walking/ledger';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Platform,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

export interface HealthActivityTrackerProps {
  ledger: WalkingLedger;
  historyRecords?: WalkingRecord[];
  todaySteps: number;
  dailyGoal?: number;
  onLoadHourlyForDate?: (date: Date) => Promise<void> | void;
}

export function HealthActivityTracker({
  ledger,
  historyRecords = [],
  todaySteps,
  dailyGoal = 10000,
  onLoadHourlyForDate,
}: HealthActivityTrackerProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  // 1. الفترة المحددة (يوم | أسبوع | شهر | سنة)
  const [selectedPeriod, setSelectedPeriod] = useState<ActivityPeriod>('day');

  // 2. التاريخ المحدد للتنقل التاريخي
  const [selectedDate, setSelectedDate] = useState<Date>(() => new Date());

  // 3. النقطة المحددة حالياً للتفاصيل (Inspector)
  const [selectedBarIndex, setSelectedBarIndex] = useState<number | null>(null);

  // إعادة ضبط النقطة المحددة عند تغيير الفترة أو التاريخ
  const handlePeriodChange = useCallback((period: ActivityPeriod) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    setSelectedPeriod(period);
    setSelectedBarIndex(null);
  }, []);

  const handleNavigate = useCallback(
    (direction: -1 | 1) => {
      try {
        Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      } catch {}
      setSelectedDate((prev) => navigatePeriod(prev, selectedPeriod, direction));
      setSelectedBarIndex(null);
    },
    [selectedPeriod]
  );

  const handleResetToToday = useCallback(() => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    setSelectedDate(new Date());
    setSelectedBarIndex(null);
  }, []);

  // حساب ملخصات الفترات بدقة وسرعة عبر useMemo
  const daySummary = useMemo(
    () => computeDaySummary(selectedDate, ledger, historyRecords, todaySteps),
    [selectedDate, ledger, historyRecords, todaySteps]
  );

  const weekSummary = useMemo(
    () => computeWeekSummary(selectedDate, ledger, historyRecords, todaySteps, dailyGoal),
    [selectedDate, ledger, historyRecords, todaySteps, dailyGoal]
  );

  const monthSummary = useMemo(
    () => computeMonthSummary(selectedDate, ledger, historyRecords, todaySteps, dailyGoal),
    [selectedDate, ledger, historyRecords, todaySteps, dailyGoal]
  );

  const yearSummary = useMemo(
    () => computeYearSummary(selectedDate, ledger, historyRecords, todaySteps),
    [selectedDate, ledger, historyRecords, todaySteps]
  );

  const canGoForward = useMemo(
    () => canNavigateForward(selectedDate, selectedPeriod),
    [selectedDate, selectedPeriod]
  );

  const isCurrent = useMemo(
    () => isCurrentPeriod(selectedDate, selectedPeriod),
    [selectedDate, selectedPeriod]
  );

  // استدعاء جلب تفاصيل الساعات إذا تغير التاريخ في نمط اليوم
  useEffect(() => {
    if (selectedPeriod === 'day' && onLoadHourlyForDate) {
      void onLoadHourlyForDate(selectedDate);
    }
  }, [selectedPeriod, selectedDate, onLoadHourlyForDate]);

  // ==========================================
  // كارت المعاينة المصغر (Compact Inspector Badge)
  // يوضع بجانب إجمالي الخطوات الرئيسي لضغط المساحة الرأسية وتفادي وجود شريط عريض فارغ
  // ==========================================
  const renderCompactInspectorCard = () => {
    let title = '';
    let stepsVal = 0;
    let subtitle = '';
    let isSelected = false;

    if (selectedBarIndex != null) {
      if (selectedPeriod === 'day') {
        const p = daySummary.points[selectedBarIndex];
        if (p) {
          isSelected = true;
          title = p.timeRange;
          stepsVal = p.steps;
          subtitle = p.steps > 0 ? `${p.distanceKm} كم • ${p.calories} ك.س` : 'لا توجد خطوات';
        }
      } else if (selectedPeriod === 'week') {
        const p = weekSummary.points[selectedBarIndex];
        if (p) {
          isSelected = true;
          title = `${p.dayName}، ${p.dayNumber}`;
          stepsVal = p.steps;
          subtitle = `${p.distanceKm} كم • ${p.calories} ك.س`;
        }
      } else if (selectedPeriod === 'month') {
        const p = monthSummary.points[selectedBarIndex];
        if (p) {
          isSelected = true;
          title = p.isToday ? `اليوم، ${p.dayNumber} ${ARABIC_MONTHS[p.date.getMonth()]}` : `${p.dayNumber} ${ARABIC_MONTHS[p.date.getMonth()]}`;
          stepsVal = p.steps;
          subtitle = p.steps > 0 ? `${p.distanceKm} كم • ${p.calories} ك.س` : 'لا توجد خطوات';
        }
      } else if (selectedPeriod === 'year') {
        const p = yearSummary.points[selectedBarIndex];
        if (p) {
          isSelected = true;
          title = `شهر ${p.monthName}`;
          stepsVal = p.steps;
          subtitle = `م/يوم: ${formatSteps(p.dailyAverage)}`;
        }
      }
    }

    if (!isSelected) {
      if (selectedPeriod === 'day') {
        title = daySummary.peakHour ? 'أعلى ساعة نشاط' : 'نشاط اليوم';
        stepsVal = daySummary.peakHour?.steps ?? 0;
        subtitle = daySummary.peakHour ? daySummary.peakHour.timeRange : 'المس عموداً للمعاينة';
      } else if (selectedPeriod === 'week') {
        title = weekSummary.bestDay ? 'أفضل يوم نشاط' : 'نشاط الأسبوع';
        stepsVal = weekSummary.bestDay?.steps ?? 0;
        subtitle = weekSummary.bestDay ? weekSummary.bestDay.dayName : 'المس عموداً للمعاينة';
      } else if (selectedPeriod === 'month') {
        title = 'أعلى يوم بالشهر';
        stepsVal = monthSummary.bestDay?.steps ?? 0;
        subtitle = monthSummary.bestDay ? `يوم ${monthSummary.bestDay.dayNumber}` : 'المس عموداً للمعاينة';
      } else {
        title = 'أعلى الشهور';
        stepsVal = yearSummary.bestMonth?.steps ?? 0;
        subtitle = yearSummary.bestMonth ? yearSummary.bestMonth.monthName : 'المس عموداً للمعاينة';
      }
    }

    return (
      <View
        style={[
          styles.compactInspectorCard,
          {
            backgroundColor: isSelected
              ? isDark
                ? '#1A3326'
                : '#ECFDF5'
              : isDark
              ? '#27272A'
              : '#F8FAFC',
            borderColor: isSelected
              ? silaTheme.primary
              : isDark
              ? '#3F3F46'
              : '#E2E8F0',
          },
        ]}>
        <View style={styles.compactInspectorHeader}>
          <View style={styles.compactIndicatorWrap}>
            <View
              style={[
                styles.compactIndicatorDot,
                { backgroundColor: isSelected ? '#F59E0B' : silaTheme.primary },
              ]}
            />
            <ThemedText style={styles.compactInspectorTitle} numberOfLines={1}>
              {title}
            </ThemedText>
          </View>
          {isSelected && (
            <TouchableOpacity
              onPress={() => setSelectedBarIndex(null)}
              hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
              style={styles.compactCloseBtn}>
              <ThemedText style={styles.compactCloseText}>✕</ThemedText>
            </TouchableOpacity>
          )}
        </View>

        <View style={styles.compactValueRow}>
          <ThemedText style={styles.compactInspectorSteps} numberOfLines={1}>
            {stepsVal > 0 ? formatSteps(stepsVal) : '0'}
          </ThemedText>
          <ThemedText style={styles.compactInspectorStepsUnit}>خطوة</ThemedText>
        </View>

        <ThemedText
          numberOfLines={1}
          style={[
            styles.compactInspectorSub,
            { color: isSelected ? silaTheme.primary : colors.icon },
          ]}>
          {subtitle}
        </ThemedText>
      </View>
    );
  };

  // ==========================================
  // دوال الرسم البياني لكل فترة
  // ==========================================

  // أ) رسم بياني اليوم (24 ساعة)
  const renderDayChart = () => {
    const points = daySummary.points;
    const maxVal = Math.max(100, ...points.map((p) => p.steps));

    return (
      <View style={styles.chartInnerWrap}>
        <View style={styles.chartBarsContainer}>
          {points.map((p, idx) => {
            const isSelected = selectedBarIndex === idx;
            const barHeightPct = Math.min(100, Math.max(p.steps > 0 ? 8 : 3, (p.steps / maxVal) * 100));

            return (
              <TouchableOpacity
                key={`h_${p.hour}`}
                activeOpacity={0.7}
                onPress={() => {
                  try {
                    Haptics.selectionAsync();
                  } catch {}
                  setSelectedBarIndex(selectedBarIndex === idx ? null : idx);
                }}
                style={styles.dayBarTouchColumn}>
                <View style={styles.barTrack}>
                  <View
                    style={[
                      styles.barFillCapsule,
                      { height: `${barHeightPct}%` },
                      isSelected && styles.barSelectedOutline,
                    ]}>
                    <LinearGradient
                      colors={
                        p.isCurrentHour
                          ? ['#F59E0B', '#10B981']
                          : p.steps > 0
                          ? isDark
                            ? ['#10B981', '#059669']
                            : ['#34D399', '#059669']
                          : isDark
                          ? ['#374151', '#1F2937']
                          : ['#E2E8F0', '#CBD5E1']
                      }
                      start={{ x: 0, y: 0 }}
                      end={{ x: 0, y: 1 }}
                      style={styles.gradientFill}
                    />
                  </View>
                </View>
                {p.displayLabel ? (
                  <ThemedText style={[styles.dayAxisLabel, { color: colors.icon }]}>
                    {p.displayLabel}
                  </ThemedText>
                ) : (
                  <View style={styles.axisSpacer} />
                )}
              </TouchableOpacity>
            );
          })}
        </View>
      </View>
    );
  };

  // ب) رسم بياني الأسبوع (7 أيام)
  const renderWeekChart = () => {
    const points = weekSummary.points;
    const maxVal = Math.max(dailyGoal, ...points.map((p) => p.steps), 100);

    return (
      <View style={styles.chartInnerWrap}>
        <View style={styles.weekChartRow}>
          {points.map((p, idx) => {
            const isSelected = selectedBarIndex === idx;
            const barHeightPct = Math.min(100, Math.max(p.steps > 0 ? 10 : 3, (p.steps / maxVal) * 100));

            return (
              <TouchableOpacity
                key={p.dateKey}
                activeOpacity={0.75}
                onPress={() => {
                  try {
                    Haptics.selectionAsync();
                  } catch {}
                  setSelectedBarIndex(selectedBarIndex === idx ? null : idx);
                }}
                style={styles.weekBarColumn}>
                <ThemedText
                  numberOfLines={1}
                  adjustsFontSizeToFit
                  minimumFontScale={0.7}
                  style={[
                    styles.weekBarStepsLabel,
                    { color: p.isToday ? silaTheme.primary : colors.text },
                    p.isToday && styles.boldText,
                  ]}>
                  {p.steps > 0 ? formatSteps(p.steps) : '—'}
                </ThemedText>

                <View style={styles.barTrack}>
                  <View
                    style={[
                      styles.weekBarFill,
                      { height: `${barHeightPct}%` },
                      isSelected && styles.barSelectedOutline,
                    ]}>
                    <LinearGradient
                      colors={
                        p.isToday
                          ? ['#F59E0B', '#10B981']
                          : p.isGoalReached
                          ? isDark
                            ? ['#34D399', '#059669']
                            : ['#10B981', '#047857']
                          : p.steps > 0
                          ? isDark
                            ? ['#6B7280', '#4B5563']
                            : ['#94A3B8', '#64748B']
                          : isDark
                          ? ['#374151', '#1F2937']
                          : ['#E2E8F0', '#CBD5E1']
                      }
                      start={{ x: 0, y: 0 }}
                      end={{ x: 0, y: 1 }}
                      style={styles.gradientFill}
                    />
                  </View>
                </View>

                <ThemedText
                  style={[
                    styles.weekDayLabel,
                    { color: p.isToday ? silaTheme.primary : colors.icon },
                    p.isToday && styles.boldText,
                  ]}>
                  {p.isToday ? 'اليوم' : p.shortDay}
                </ThemedText>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>
    );
  };

  // ج) رسم بياني الشهر (30 / 31 يوماً)
  const renderMonthChart = () => {
    const points = monthSummary.points;
    const maxVal = Math.max(dailyGoal, ...points.map((p) => p.steps), 100);

    return (
      <View style={styles.chartInnerWrap}>
        <View style={styles.monthChartContainer}>
          {points.map((p, idx) => {
            const isSelected = selectedBarIndex === idx;
            const barHeightPct = Math.min(100, Math.max(p.steps > 0 ? 8 : 3, (p.steps / maxVal) * 100));

            return (
              <TouchableOpacity
                key={p.dateKey}
                activeOpacity={0.7}
                onPress={() => {
                  try {
                    Haptics.selectionAsync();
                  } catch {}
                  setSelectedBarIndex(selectedBarIndex === idx ? null : idx);
                }}
                style={styles.monthBarTouchColumn}>
                <View style={styles.barTrack}>
                  <View
                    style={[
                      styles.monthBarFill,
                      { height: `${barHeightPct}%` },
                      isSelected && styles.barSelectedOutline,
                    ]}>
                    <LinearGradient
                      colors={
                        p.isToday
                          ? ['#EDC967', '#009000']
                          : p.steps >= 15000
                          ? ['#EDC967', '#D4AF37']
                          : p.steps >= 10000
                          ? ['#BBBCC0', '#999A9E']
                          : p.steps >= 5000
                          ? ['#C87A38', '#A05A24']
                          : p.steps > 0
                          ? ['#009000', '#007000']
                          : isDark
                          ? ['#374151', '#1F2937']
                          : ['#E2E8F0', '#CBD5E1']
                      }
                      start={{ x: 0, y: 0 }}
                      end={{ x: 0, y: 1 }}
                      style={styles.gradientFill}
                    />
                  </View>
                </View>

                {p.showLabel ? (
                  <ThemedText style={[styles.monthAxisLabel, { color: colors.icon }]}>
                    {p.dayNumber}
                  </ThemedText>
                ) : (
                  <View style={styles.monthAxisSpacer} />
                )}
              </TouchableOpacity>
            );
          })}
        </View>
      </View>
    );
  };

  // د) رسم بياني السنة (12 شهراً)
  const renderYearChart = () => {
    const points = yearSummary.points;
    const maxVal = Math.max(10000, ...points.map((p) => p.steps));

    return (
      <View style={styles.chartInnerWrap}>
        <View style={styles.yearChartRow}>
          {points.map((p, idx) => {
            const isSelected = selectedBarIndex === idx;
            const barHeightPct = Math.min(100, Math.max(p.steps > 0 ? 10 : 3, (p.steps / maxVal) * 100));

            return (
              <TouchableOpacity
                key={`m_${p.monthIndex}`}
                activeOpacity={0.75}
                onPress={() => {
                  try {
                    Haptics.selectionAsync();
                  } catch {}
                  setSelectedBarIndex(selectedBarIndex === idx ? null : idx);
                }}
                style={styles.yearBarColumn}>
                <View style={styles.barTrack}>
                  <View
                    style={[
                      styles.yearBarFill,
                      { height: `${barHeightPct}%` },
                      isSelected && styles.barSelectedOutline,
                    ]}>
                    <LinearGradient
                      colors={
                        p.isCurrentMonth
                          ? ['#F59E0B', '#10B981']
                          : p.steps > 0
                          ? isDark
                            ? ['#10B981', '#059669']
                            : ['#34D399', '#059669']
                          : isDark
                          ? ['#374151', '#1F2937']
                          : ['#E2E8F0', '#CBD5E1']
                      }
                      start={{ x: 0, y: 0 }}
                      end={{ x: 0, y: 1 }}
                      style={styles.gradientFill}
                    />
                  </View>
                </View>

                <ThemedText
                  numberOfLines={1}
                  adjustsFontSizeToFit
                  minimumFontScale={0.75}
                  style={[
                    styles.yearMonthLabel,
                    { color: p.isCurrentMonth ? silaTheme.primary : colors.icon },
                    p.isCurrentMonth && styles.boldText,
                  ]}>
                  {p.shortMonth}
                </ThemedText>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>
    );
  };

  // نص الرؤية/المعلومة التحفيزية السفلية (Apple Health Insight)
  const currentInsight = useMemo(() => {
    if (selectedPeriod === 'day') {
      if (daySummary.activeHoursCount === 0) {
        return 'لم تسجل خطوات حتى الآن في هذا اليوم. ابدأ حركتك الآن لتحفيز نشاطك!';
      }
      return `مشيت خلال ${daySummary.activeHoursCount} ${
        daySummary.activeHoursCount === 1 ? 'ساعة' : 'ساعات'
      } اليوم${
        daySummary.peakHour
          ? ` • أعلى فترة نشاط كانت (${daySummary.peakHour.timeRange}) بـ ${formatSteps(
              daySummary.peakHour.steps
            )} خطوة`
          : ''
      }.`;
    }
    if (selectedPeriod === 'week') {
      return `متوسطك اليومي ${formatSteps(weekSummary.dailyAverage)} خطوة • حققت هدف الـ ${formatSteps(
        dailyGoal
      )} خطوة في ${weekSummary.goalReachedDaysCount} من 7 أيام.`;
    }
    if (selectedPeriod === 'month') {
      return `إجمالي خطواتك للشهر ${formatSteps(monthSummary.totalSteps)} خطوة عبر ${
        monthSummary.activeDaysCount
      } يوماً نشطاً • ما يعادل ${monthSummary.distanceKm} كم مشياً.`;
    }
    return `سجلت ${formatSteps(yearSummary.totalSteps)} خطوة خلال العام • بمتوسط شهري ${formatSteps(
      yearSummary.monthlyAverage
    )} خطوة${
      yearSummary.bestMonth ? ` • أعلى نشاط شهري: ${yearSummary.bestMonth.monthName} (${formatSteps(yearSummary.bestMonth.steps)})` : ''
    }.`;
  }, [selectedPeriod, daySummary, weekSummary, monthSummary, yearSummary, dailyGoal]);

  return (
    <View
      style={[
        styles.container,
        {
          backgroundColor: isDark ? '#18181B' : '#FFFFFF',
          borderColor: colors.border,
        },
      ]}>
      {/* 1️⃣ التبويبات العلوية (Segmented Control: يوم | أسبوع | شهر | سنة) */}
      <View
        style={[
          styles.tabsHeaderWrap,
          { backgroundColor: isDark ? '#27272A' : '#F1F5F9' },
        ]}>
        {PERIOD_TABS.map((tab) => {
          const isSelected = selectedPeriod === tab.key;
          return (
            <TouchableOpacity
              key={tab.key}
              activeOpacity={0.8}
              onPress={() => handlePeriodChange(tab.key)}
              style={[
                styles.tabPill,
                isSelected && [
                  styles.tabPillActive,
                  { backgroundColor: isDark ? '#3F3F46' : '#FFFFFF' },
                ],
              ]}>
              <ThemedText
                style={[
                  styles.tabPillText,
                  { color: isSelected ? silaTheme.primary : colors.icon },
                  isSelected && styles.tabPillTextActive,
                ]}>
                {tab.label}
              </ThemedText>
            </TouchableOpacity>
          );
        })}
      </View>

      {/* 2️⃣ شريط التنقل التاريخي (Date Navigation Bar) */}
      <View style={styles.navBar}>
        <TouchableOpacity
          activeOpacity={0.7}
          onPress={() => handleNavigate(-1)}
          style={[styles.navArrowBtn, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
          <IconSymbol name="chevron.right" size={16} color={colors.text} />
        </TouchableOpacity>

        <View style={styles.navTitleCenter}>
          <ThemedText style={styles.periodTitle}>
            {selectedPeriod === 'day'
              ? daySummary.title
              : selectedPeriod === 'week'
              ? weekSummary.title
              : selectedPeriod === 'month'
              ? monthSummary.title
              : yearSummary.title}
          </ThemedText>
          {!isCurrent && (
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={handleResetToToday}
              style={[
                styles.resetTodayBtn,
                { backgroundColor: isDark ? '#134E4A' : '#ECFDF5' },
              ]}>
              <ThemedText style={styles.resetTodayText}>اليوم</ThemedText>
            </TouchableOpacity>
          )}
        </View>

        <TouchableOpacity
          activeOpacity={0.7}
          disabled={!canGoForward}
          onPress={() => handleNavigate(1)}
          style={[
            styles.navArrowBtn,
            {
              backgroundColor: isDark ? '#27272A' : '#F8FAFC',
              opacity: canGoForward ? 1 : 0.25,
            },
          ]}>
          <IconSymbol name="chevron.left" size={16} color={colors.text} />
        </TouchableOpacity>
      </View>

      {/* 3️⃣ ملخص الأرقام العلوية (Hero Metrics Header) */}
      <View style={styles.metricsSummaryWrap}>
        <View style={styles.metricsTopRow}>
          <View style={styles.primaryMetricCol}>
            <ThemedText style={styles.primaryNumber}>
              {formatSteps(
                selectedPeriod === 'day'
                  ? daySummary.totalSteps
                  : selectedPeriod === 'week'
                  ? weekSummary.dailyAverage
                  : selectedPeriod === 'month'
                  ? monthSummary.totalSteps
                  : yearSummary.monthlyAverage
              )}
            </ThemedText>
            <ThemedText style={[styles.primaryLabel, { color: colors.icon }]}>
              {selectedPeriod === 'day'
                ? 'إجمالي خطوات اليوم'
                : selectedPeriod === 'month'
                ? 'إجمالي الخطوات'
                : selectedPeriod === 'year'
                ? 'المتوسط الشهري للخطوات'
                : 'المتوسط اليومي للخطوات'}
            </ThemedText>
          </View>

          {renderCompactInspectorCard()}
        </View>

        {/* شريط الإحصائيات الفرعية السريعة */}
        <View style={styles.subStatsRow}>
          {selectedPeriod === 'day' ? (
            <>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <IconSymbol name="clock.fill" size={11} color={silaTheme.primary} />
                <ThemedText style={styles.subStatText}>
                  {daySummary.activeHoursCount} ساعات نشطة
                </ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>📍</ThemedText>
                <ThemedText style={styles.subStatText}>{daySummary.distanceKm} كم</ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>🔥</ThemedText>
                <ThemedText style={styles.subStatText}>{daySummary.calories} ك.س</ThemedText>
              </View>
            </>
          ) : selectedPeriod === 'week' ? (
            <>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <IconSymbol name="figure.walk" size={11} color={silaTheme.primary} />
                <ThemedText style={styles.subStatText}>
                  {formatSteps(weekSummary.totalSteps)} إجمالي
                </ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>🏆</ThemedText>
                <ThemedText style={styles.subStatText}>
                  {weekSummary.bestDay ? weekSummary.bestDay.dayName : '—'}
                </ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>🎯</ThemedText>
                <ThemedText style={styles.subStatText}>
                  {weekSummary.goalReachedDaysCount} من 7 أيام
                </ThemedText>
              </View>
            </>
          ) : selectedPeriod === 'month' ? (
            <>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <IconSymbol name="calendar" size={11} color={silaTheme.primary} />
                <ThemedText style={styles.subStatText}>
                  {monthSummary.activeDaysCount} يوماً نشطاً
                </ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>📍</ThemedText>
                <ThemedText style={styles.subStatText}>{monthSummary.distanceKm} كم</ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>🎯</ThemedText>
                <ThemedText style={styles.subStatText}>
                  {monthSummary.goalReachedDaysCount} هدف مكتمل
                </ThemedText>
              </View>
            </>
          ) : (
            <>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <IconSymbol name="chart.bar.fill" size={11} color={silaTheme.primary} />
                <ThemedText style={styles.subStatText}>
                  {formatSteps(yearSummary.totalSteps)} إجمالي السنة
                </ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>🌟</ThemedText>
                <ThemedText style={styles.subStatText}>
                  أفضل: {yearSummary.bestMonth ? yearSummary.bestMonth.monthName : '—'}
                </ThemedText>
              </View>
              <View style={[styles.subStatBadge, { backgroundColor: isDark ? '#27272A' : '#F8FAFC' }]}>
                <ThemedText style={styles.subStatEmoji}>📍</ThemedText>
                <ThemedText style={styles.subStatText}>{yearSummary.distanceKm} كم</ThemedText>
              </View>
            </>
          )}
        </View>
      </View>

      {/* 4️⃣ منطقة الرسم البياني التفاعلي */}
      <View style={styles.chartContainer}>
        {selectedPeriod === 'day' && renderDayChart()}
        {selectedPeriod === 'week' && renderWeekChart()}
        {selectedPeriod === 'month' && renderMonthChart()}
        {selectedPeriod === 'year' && renderYearChart()}
      </View>

      {/* 5️⃣ الرؤية الذكية التوضيحية (Apple Health Insight Footnote) */}
      <View
        style={[
          styles.insightBox,
          {
            backgroundColor: isDark ? '#142E25' : '#F0FDF4',
            borderColor: isDark ? '#166534' : '#BBF7D0',
          },
        ]}>
        <ThemedText style={styles.insightEmoji}>💡</ThemedText>
        <ThemedText style={[styles.insightText, { color: isDark ? '#BBF7D0' : '#14532D' }]}>
          {currentInsight}
        </ThemedText>
      </View>
    </View>
  );
}

// ==========================================
// التنسيقات والجماليات (Styles)
// ==========================================

const styles = StyleSheet.create({
  container: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 14,
    marginBottom: 16,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.05,
        shadowRadius: 8,
      },
      android: { elevation: 2 },
    }),
  },
  tabsHeaderWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 3,
    borderRadius: 14,
    marginBottom: 12,
  },
  tabPill: {
    flex: 1,
    paddingVertical: 7,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 11,
  },
  tabPillActive: {
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1.5 },
        shadowOpacity: 0.08,
        shadowRadius: 3,
      },
      android: { elevation: 1 },
    }),
  },
  tabPillText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  tabPillTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  navBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
    paddingHorizontal: 2,
  },
  navArrowBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  navTitleCenter: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  periodTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
    textAlign: 'center',
  },
  resetTodayBtn: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
  resetTodayText: {
    color: '#059669',
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  metricsSummaryWrap: {
    marginBottom: 14,
    gap: 8,
  },
  primaryMetricCol: {
    alignItems: 'flex-start',
  },
  primaryNumber: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 26,
    lineHeight: 32,
    color: silaTheme.primary,
  },
  primaryLabel: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  subStatsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexWrap: 'wrap',
  },
  subStatBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  subStatEmoji: {
    fontSize: 11,
  },
  subStatText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
  metricsTopRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 10,
  },
  compactInspectorCard: {
    width: 158,
    minHeight: 62,
    paddingHorizontal: 9,
    paddingVertical: 6,
    borderRadius: 12,
    borderWidth: 1,
    justifyContent: 'center',
  },
  compactInspectorHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 2,
  },
  compactIndicatorWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    flex: 1,
  },
  compactIndicatorDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  compactInspectorTitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 10.5,
    flex: 1,
  },
  compactCloseBtn: {
    width: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: '#94A3B830',
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 4,
  },
  compactCloseText: {
    fontSize: 9,
    color: '#64748B',
    lineHeight: 11,
  },
  compactValueRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 4,
  },
  compactInspectorSteps: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    color: silaTheme.primary,
  },
  compactInspectorStepsUnit: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 10,
    color: silaTheme.primary,
  },
  compactInspectorSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 9.5,
    marginTop: 1,
  },
  chartContainer: {
    height: 145,
    marginBottom: 10,
    marginTop: 6,
  },
  chartInnerWrap: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  // Day Chart Styles
  chartBarsContainer: {
    flex: 1,
    flexDirection: 'row',
    direction: 'ltr',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    paddingTop: 6,
    gap: 2,
  },
  dayBarTouchColumn: {
    flex: 1,
    height: '100%',
    alignItems: 'center',
    justifyContent: 'flex-end',
  },
  barTrack: {
    flex: 1,
    width: '100%',
    alignItems: 'center',
    justifyContent: 'flex-end',
  },
  barFillCapsule: {
    width: '80%',
    borderRadius: 4,
    overflow: 'hidden',
  },
  gradientFill: {
    flex: 1,
    borderRadius: 4,
  },
  barSelectedOutline: {
    borderWidth: 1.5,
    borderColor: '#F59E0B',
  },
  dayAxisLabel: {
    fontSize: 9,
    fontFamily: 'Tajawal-Medium',
    marginTop: 4,
    textAlign: 'center',
  },
  axisSpacer: {
    height: 14,
    marginTop: 4,
  },
  // Week Chart Styles
  weekChartRow: {
    flex: 1,
    flexDirection: 'row',
    direction: 'ltr',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    paddingTop: 6,
    gap: 6,
  },
  weekBarColumn: {
    flex: 1,
    height: '100%',
    alignItems: 'center',
    justifyContent: 'flex-end',
  },
  weekBarStepsLabel: {
    fontSize: 8.5,
    fontFamily: 'Tajawal-Medium',
    marginBottom: 4,
    textAlign: 'center',
  },
  weekBarFill: {
    width: '85%',
    borderRadius: 7,
    overflow: 'hidden',
  },
  weekDayLabel: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
    marginTop: 4,
    textAlign: 'center',
  },
  // Month Chart Styles
  monthChartContainer: {
    flex: 1,
    flexDirection: 'row',
    direction: 'ltr',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    paddingTop: 6,
    gap: 1.5,
  },
  monthBarTouchColumn: {
    flex: 1,
    height: '100%',
    alignItems: 'center',
    justifyContent: 'flex-end',
  },
  monthBarFill: {
    width: '90%',
    borderRadius: 3,
    overflow: 'hidden',
  },
  monthAxisLabel: {
    fontSize: 8.5,
    fontFamily: 'Tajawal-Medium',
    marginTop: 4,
    textAlign: 'center',
  },
  monthAxisSpacer: {
    height: 13,
    marginTop: 4,
  },
  // Year Chart Styles
  yearChartRow: {
    flex: 1,
    flexDirection: 'row',
    direction: 'ltr',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    paddingTop: 6,
    gap: 4,
  },
  yearBarColumn: {
    flex: 1,
    height: '100%',
    alignItems: 'center',
    justifyContent: 'flex-end',
  },
  yearBarFill: {
    width: '80%',
    borderRadius: 6,
    overflow: 'hidden',
  },
  yearMonthLabel: {
    fontSize: 9.5,
    fontFamily: 'Tajawal-Medium',
    marginTop: 4,
    textAlign: 'center',
  },
  boldText: {
    fontFamily: 'Tajawal-Bold',
  },
  insightBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 10,
    borderRadius: 12,
    borderWidth: 1,
    marginTop: 2,
  },
  insightEmoji: {
    fontSize: 14,
  },
  insightText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
    lineHeight: 17,
  },
});
