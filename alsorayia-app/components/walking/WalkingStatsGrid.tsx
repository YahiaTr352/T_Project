import { ThemedText } from '@/components/themed-text';
import { Colors } from '@/constants/theme';
import { formatDateAr, formatSteps } from '@/lib/walking/formatters';
import React from 'react';
import { Platform, StyleSheet, View, useColorScheme } from 'react-native';

type Props = {
  todaySteps?: number;
  dailyGoal?: number;
  familyRankToday?: number | null;
  familyTotalToday?: number;
  bestDaySteps: number;
  bestDayDate?: string | null;
  streak10k?: number;
  activeDaysCount?: number;
  avgDaily30d?: number;
  // Fallbacks
  currentStreak?: number;
  longestStreak?: number;
  avgDailySteps?: number;
  totalDaysActive?: number;
};

export function WalkingStatsGrid({
  todaySteps = 0,
  dailyGoal = 10000,
  familyRankToday,
  familyTotalToday = 0,
  bestDaySteps,
  bestDayDate,
  streak10k,
  activeDaysCount,
  avgDaily30d,
  currentStreak = 0,
  avgDailySteps = 0,
  totalDaysActive = 1,
}: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  // Resolved values
  const effectiveStreak = streak10k ?? currentStreak;
  const effectiveActiveDays = activeDaysCount ?? totalDaysActive;
  const effectiveAvg30d = avgDaily30d ?? Math.round(avgDailySteps || todaySteps);

  // Goal calculations: Silver (5,000) & Gold (10,000)
  const silverTarget = 5000;
  const goldTarget = Math.max(dailyGoal, 10000);

  let goalRemainingValue = '';
  let goalTierSub = '';
  let goalBadge = '🥈';

  if (todaySteps < silverTarget) {
    const diff = silverTarget - todaySteps;
    goalRemainingValue = `باقي ${formatSteps(diff)}`;
    goalTierSub = `للهدف الفضي (5 آلاف)`;
    goalBadge = '🥈';
  } else if (todaySteps < goldTarget) {
    const diff = goldTarget - todaySteps;
    goalRemainingValue = `باقي ${formatSteps(diff)}`;
    goalTierSub = `حُقّق الفضي | للذهبي 🥇`;
    goalBadge = '🥇';
  } else {
    const extra = todaySteps - goldTarget;
    goalRemainingValue = `حُقّق بنجاح! 🎉`;
    goalTierSub = extra > 0 ? `+${formatSteps(extra)} خطوة إضافية` : 'تم إنجاز 100% بنجاح 🌟';
    goalBadge = '👑';
  }

  // Family Rank Tag and Colors
  let rankIcon = '🏅';
  let rankMainValue = 'غير مصنف';
  let rankSubText = familyTotalToday > 0 ? `${familyTotalToday} مشارك في انتظارك` : 'سجّل خطواتك للمنافسة';
  let rankTextColor = isDark ? '#60A5FA' : '#2563EB';

  if (familyRankToday === 1) {
    rankIcon = '👑';
    rankMainValue = 'المركز الأول';
    rankSubText = familyTotalToday > 0 ? `من أصل ${familyTotalToday} مشارك` : 'متصدر تحدي اليوم 👑';
    rankTextColor = isDark ? '#FBBF24' : '#D97706';
  } else if (familyRankToday && familyRankToday <= 3) {
    rankIcon = '🥈';
    rankMainValue = `المركز ${familyRankToday}`;
    rankSubText = familyTotalToday > 0 ? `من أصل ${familyTotalToday} مشارك` : 'على منصة التتويج';
    rankTextColor = isDark ? '#34D399' : '#059669';
  } else if (familyRankToday && familyRankToday > 3) {
    rankIcon = '🏅';
    rankMainValue = `المركز ${familyRankToday}`;
    rankSubText = familyTotalToday > 0 ? `من أصل ${familyTotalToday} مشارك` : 'منافس نشط اليوم';
    rankTextColor = isDark ? '#38BDF8' : '#0284C7';
  }

  // Common card style generator for consistent height, padding, and layout
  return (
    <View style={styles.container}>
      {/* ─────────────────────────────────────────────────────────── */}
      {/* الصف 1: ترتيبي بالعائلة + المتبقي للهدف                    */}
      {/* ─────────────────────────────────────────────────────────── */}
      <View style={styles.statsGridRow}>
        {/* 1. ترتيبي بالعائلة */}
        <View
          style={[
            styles.subGridCard,
            {
              backgroundColor: isDark ? '#142338' : '#EFF6FF',
              borderColor: isDark ? '#1E40AF' : '#BFDBFE',
            },
          ]}>
          <View style={styles.cardHeader}>
            <ThemedText style={styles.cardIcon}>{rankIcon}</ThemedText>
            <ThemedText style={[styles.cardTitle, { color: colors.icon }]}>
              ترتيبي بالعائلة
            </ThemedText>
          </View>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            style={[styles.cardValue, { color: rankTextColor }]}>
            {rankMainValue}
          </ThemedText>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.8}
            style={[styles.cardSubText, { color: colors.icon }]}>
            {rankSubText}
          </ThemedText>
        </View>

        {/* 2. المتبقي للهدف */}
        <View
          style={[
            styles.subGridCard,
            {
              backgroundColor: isDark ? '#122B22' : '#F0FDF4',
              borderColor: isDark ? '#166534' : '#BBF7D0',
            },
          ]}>
          <View style={styles.cardHeader}>
            <ThemedText style={styles.cardIcon}>{goalBadge}</ThemedText>
            <ThemedText style={[styles.cardTitle, { color: colors.icon }]}>
              المتبقي للهدف
            </ThemedText>
          </View>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            style={[styles.cardValue, { color: isDark ? '#34D399' : '#16A34A' }]}>
            {goalRemainingValue}
          </ThemedText>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.8}
            style={[styles.cardSubText, { color: colors.icon }]}>
            {goalTierSub}
          </ThemedText>
        </View>
      </View>

      {/* ─────────────────────────────────────────────────────────── */}
      {/* الصف 2: أفضل يوم + سلسلة الاستمرارية                       */}
      {/* ─────────────────────────────────────────────────────────── */}
      <View style={styles.statsGridRow}>
        {/* 3. أفضل يوم */}
        <View
          style={[
            styles.subGridCard,
            {
              backgroundColor: isDark ? '#241C10' : '#FFFBEB',
              borderColor: isDark ? '#78350F' : '#FDE68A',
            },
          ]}>
          <View style={styles.cardHeader}>
            <ThemedText style={styles.cardIcon}>🏆</ThemedText>
            <ThemedText style={[styles.cardTitle, { color: colors.icon }]}>
              أفضل يوم
            </ThemedText>
          </View>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            style={[styles.cardValue, { color: isDark ? '#FBBF24' : '#D97706' }]}>
            {formatSteps(bestDaySteps)}
          </ThemedText>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.8}
            style={[styles.cardSubText, { color: colors.icon }]}>
            {bestDayDate ? formatDateAr(bestDayDate) : 'أعلى إنجاز يومي'}
          </ThemedText>
        </View>

        {/* 4. سلسلة الاستمرارية */}
        <View
          style={[
            styles.subGridCard,
            {
              backgroundColor: isDark ? '#261612' : '#FFF7ED',
              borderColor: isDark ? '#9A3412' : '#FED7AA',
            },
          ]}>
          <View style={styles.cardHeader}>
            <ThemedText style={styles.cardIcon}>🔥</ThemedText>
            <ThemedText style={[styles.cardTitle, { color: colors.icon }]}>
              سلسلة الاستمرارية
            </ThemedText>
          </View>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            style={[styles.cardValue, { color: isDark ? '#FB923C' : '#EA580C' }]}>
            {effectiveStreak} {effectiveStreak === 1 ? 'يوم' : 'أيام'}
          </ThemedText>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.8}
            style={[styles.cardSubText, { color: colors.icon }]}>
            أيام متتالية (+10 آلاف خطوة)
          </ThemedText>
        </View>
      </View>

      {/* ─────────────────────────────────────────────────────────── */}
      {/* الصف 3: الأيام النشطة + المتوسط اليومي                    */}
      {/* ─────────────────────────────────────────────────────────── */}
      <View style={styles.statsGridRow}>
        {/* 5. الأيام النشطة */}
        <View
          style={[
            styles.subGridCard,
            {
              backgroundColor: isDark ? '#221630' : '#FAF5FF',
              borderColor: isDark ? '#581C87' : '#E9D5FF',
            },
          ]}>
          <View style={styles.cardHeader}>
            <ThemedText style={styles.cardIcon}>⚡</ThemedText>
            <ThemedText style={[styles.cardTitle, { color: colors.icon }]}>
              الأيام النشطة
            </ThemedText>
          </View>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            style={[styles.cardValue, { color: isDark ? '#C084FC' : '#9333EA' }]}>
            {formatSteps(effectiveActiveDays)} يوماً
          </ThemedText>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.8}
            style={[styles.cardSubText, { color: colors.icon }]}>
            تجاوزت فيها 5,000 خطوة
          </ThemedText>
        </View>

        {/* 6. المتوسط اليومي */}
        <View
          style={[
            styles.subGridCard,
            {
              backgroundColor: isDark ? '#122738' : '#F0F9FF',
              borderColor: isDark ? '#0369A1' : '#BAE6FD',
            },
          ]}>
          <View style={styles.cardHeader}>
            <ThemedText style={styles.cardIcon}>📊</ThemedText>
            <ThemedText style={[styles.cardTitle, { color: colors.icon }]}>
              المتوسط اليومي
            </ThemedText>
          </View>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            style={[styles.cardValue, { color: isDark ? '#38BDF8' : '#0284C7' }]}>
            {formatSteps(effectiveAvg30d)}
          </ThemedText>
          <ThemedText
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.8}
            style={[styles.cardSubText, { color: colors.icon }]}>
            خطوة / يوم (آخر 30 يوماً)
          </ThemedText>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 10,
    marginTop: 6,
  },
  statsGridRow: {
    flexDirection: 'row',
    gap: 10,
  },
  subGridCard: {
    flex: 1,
    height: 102, // ارتفاع متطابق تماماً لكل الكروت الستة
    borderRadius: 16,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
    justifyContent: 'space-between',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.04,
        shadowRadius: 4,
      },
      android: { elevation: 1.5 },
    }),
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  cardIcon: {
    fontSize: 14,
  },
  cardTitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
  },
  cardValue: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    fontVariant: ['tabular-nums'],
    marginVertical: 1,
  },
  cardSubText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 10.5,
  },
});
