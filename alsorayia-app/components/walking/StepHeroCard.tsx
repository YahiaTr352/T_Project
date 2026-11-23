import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { formatSteps } from '@/lib/walking/formatters';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect, useMemo, useRef } from 'react';
import {
  Animated,
  Easing,
  Platform,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

type Props = {
  todaySteps: number | null;
  dailyGoal?: number;
  progress?: number;
  distanceKm: number | null;
  calories: number | null;
  activeMinutes: number | null;
  currentStreak?: number;
  isSyncing?: boolean;
  onSyncPress?: () => void;
  lastSyncTime?: Date | null;
  stepTimeZoneLabel?: string;
  activityStatus?: string;
  activityUnavailable?: boolean;
};

export function StepHeroCard({
  todaySteps,
  dailyGoal = 10000,
  distanceKm,
  calories,
  activeMinutes,
  currentStreak = 0,
  isSyncing = false,
  onSyncPress,
  lastSyncTime,
  stepTimeZoneLabel,
  activityStatus,
  activityUnavailable = false,
}: Props) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const pct = todaySteps === null ? null : Math.min(100, Math.round((todaySteps / dailyGoal) * 100));

  // أنيميشن دوران أيقونة المزامنة بسلاسة 60 إطار في الثانية
  const spinAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (isSyncing) {
      spinAnim.setValue(0);
      const loop = Animated.loop(
        Animated.timing(spinAnim, {
          toValue: 1,
          duration: 900,
          easing: Easing.linear,
          useNativeDriver: true,
        })
      );
      loop.start();
      return () => loop.stop();
    } else {
      spinAnim.setValue(0);
    }
  }, [isSyncing, spinAnim]);

  const spin = spinAnim.interpolate({
    inputRange: [0, 1],
    outputRange: ['0deg', '360deg'],
  });

  const lastSyncTimeText = useMemo(() => {
    if (!lastSyncTime) return null;
    try {
      const hours = lastSyncTime.getHours();
      const minutes = String(lastSyncTime.getMinutes()).padStart(2, '0');
      const ampm = hours >= 12 ? 'م' : 'ص';
      const formattedHours = hours % 12 || 12;
      return `${formattedHours}:${minutes} ${ampm}`;
    } catch {
      return null;
    }
  }, [lastSyncTime]);

  return (
    <LinearGradient
      colors={
        isDark
          ? ['#0F766E', '#115E59', '#064E3B']
          : ['#042F2E', '#0D6860', '#0F766E']
      }
      start={{ x: 0, y: 0 }}
      end={{ x: 1, y: 1 }}
      style={styles.card}>
      {/* Decorative ambient subtle glow */}
      <View style={styles.ambientGlow} />

      {/* Row 1: Badge on Right, 3 Compact Metrics on Left */}
      <View style={styles.topRow}>
        <View style={styles.tagWrap}>
          <ThemedText style={styles.tagEmoji}>
            {currentStreak > 0 ? '🔥' : '⚡'}
          </ThemedText>
          <ThemedText style={styles.tagText}>
            {currentStreak > 0
              ? `سلسلة ${currentStreak} ${currentStreak === 1 ? 'يوم' : 'أيام'}`
              : 'نشاطك اليومي'}
          </ThemedText>
        </View>

        {/* 3 Metrics inline */}
        {distanceKm !== null && calories !== null && activeMinutes !== null && <View style={styles.metricsInline}>
          <View style={styles.metricItem}>
            <IconSymbol name="mappin.circle.fill" size={10.5} color="#5EEAD4" />
            <ThemedText style={styles.metricText}>{distanceKm} كم</ThemedText>
          </View>
          <View style={styles.metricDot} />
          <View style={styles.metricItem}>
            <ThemedText style={styles.metricEmoji}>🔥</ThemedText>
            <ThemedText style={styles.metricText}>{formatSteps(calories)} ك.س</ThemedText>
          </View>
          <View style={styles.metricDot} />
          <View style={styles.metricItem}>
            <IconSymbol name="clock.fill" size={10.5} color="#5EEAD4" />
            <ThemedText style={styles.metricText}>{activeMinutes} د</ThemedText>
          </View>
        </View>}
      </View>

      {/* Row 2: Steps Count on Right, Target Percentage on Left */}
      <View style={styles.middleRow}>
        <View style={styles.stepCountWrap}>
          <ThemedText style={styles.stepsNumber}>
            {todaySteps === null ? '—' : formatSteps(todaySteps)}
          </ThemedText>
          <ThemedText style={styles.stepLabel}>
            {stepTimeZoneLabel ? `خطوة · ${stepTimeZoneLabel}` : 'خطوة'}
          </ThemedText>
        </View>

        <View style={styles.goalInfoWrap}>
          <ThemedText style={styles.goalTargetText}>
            {pct !== null && pct >= 100 ? '🎉 الهدف مكتمل' : `الهدف: ${formatSteps(dailyGoal)}`}
          </ThemedText>
          {pct !== null && <View
            style={[
              styles.goalPctBadge,
              pct >= 100 && styles.goalPctBadgeAchieved,
            ]}>
            <ThemedText style={styles.goalPctText}>{pct}%</ThemedText>
          </View>}
        </View>
      </View>

      {/* Row 3: Slim Progress Bar (3.5px) */}
      {pct !== null && <View style={styles.progressBarTrack}>
        <View style={[styles.progressBarFill, { width: `${pct}%` }]}>
          <LinearGradient
            colors={['#F59E0B', '#10B981', '#34D399']}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 0 }}
            style={StyleSheet.absoluteFill}
          />
        </View>
      </View>}
      {activityStatus && (
        <View style={[styles.activityStatus, activityUnavailable && styles.activityWarning]}>
          <ThemedText
            accessibilityRole={activityUnavailable ? 'alert' : undefined}
            accessibilityLiveRegion="polite"
            style={[styles.activityStatusText, activityUnavailable && styles.activityWarningText]}>
            {activityStatus}
          </ThemedText>
          <IconSymbol
            name={activityUnavailable ? 'exclamationmark.triangle.fill' : 'info.circle'}
            size={14}
            color={activityUnavailable ? '#FCD34D' : '#99F6E4'}
          />
        </View>
      )}

      {/* Row 4: Action Sync Bar (زر تفاعلي رئيسي للمزامنة الفورية مع صحتي / خطواتي) */}
      {onSyncPress && (
        <View style={styles.actionSyncRow}>
          <TouchableOpacity
            accessibilityRole="button"
            activeOpacity={0.75}
            onPress={onSyncPress}
            disabled={isSyncing}
            style={[styles.syncButton, isSyncing && styles.syncButtonActive]}>
            <Animated.View style={{ transform: [{ rotate: spin }] }}>
              <IconSymbol name="arrow.clockwise" size={12} color="#ECFEFF" />
            </Animated.View>
            <ThemedText style={styles.syncButtonText}>
              {isSyncing
                ? 'جاري المزامنة...'
                : Platform.OS === 'ios'
                ? 'مزامنة مع صحتي'
                : 'مزامنة خطواتي'}
            </ThemedText>
            {Platform.OS === 'ios' && !isSyncing && (
              <ThemedText style={styles.syncHeartEmoji}>❤️</ThemedText>
            )}
          </TouchableOpacity>

          {lastSyncTimeText ? (
            <ThemedText style={styles.lastSyncLabel}>
              آخر مزامنة: {lastSyncTimeText}
            </ThemedText>
          ) : !activityStatus ? (
            <ThemedText style={styles.lastSyncLabel}>
              مزامنة لحظية مباشرة
            </ThemedText>
          ) : null}
        </View>
      )}
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 16,
    paddingVertical: 11,
    paddingHorizontal: 14,
    position: 'relative',
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#0F766E',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 8,
      },
      android: { elevation: 4 },
    }),
  },
  ambientGlow: {
    position: 'absolute',
    top: -30,
    right: -30,
    width: 90,
    height: 90,
    borderRadius: 45,
    backgroundColor: 'rgba(94, 234, 212, 0.10)',
  },
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  tagWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0, 0, 0, 0.22)',
    paddingHorizontal: 8,
    paddingVertical: 2.5,
    borderRadius: 10,
    gap: 4,
  },
  tagEmoji: {
    fontSize: 11,
  },
  tagText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    color: '#ECFEFF',
  },
  metricsInline: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0, 0, 0, 0.18)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 10,
    gap: 5,
  },
  metricItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  metricEmoji: {
    fontSize: 10,
  },
  metricText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10.5,
    color: '#FFFFFF',
  },
  metricDot: {
    width: 2.5,
    height: 2.5,
    borderRadius: 1.5,
    backgroundColor: 'rgba(255, 255, 255, 0.35)',
  },
  middleRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  stepCountWrap: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 5,
  },
  stepsNumber: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 28,
    lineHeight: 32,
    color: '#FFFFFF',
    fontVariant: ['tabular-nums'],
  },
  stepLabel: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12.5,
    color: '#CCFBF1',
  },
  goalInfoWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  goalTargetText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    color: '#CCFBF1',
  },
  goalPctBadge: {
    backgroundColor: 'rgba(255, 255, 255, 0.18)',
    paddingHorizontal: 6,
    paddingVertical: 1.5,
    borderRadius: 8,
  },
  goalPctBadgeAchieved: {
    backgroundColor: 'rgba(251, 191, 36, 0.35)',
  },
  goalPctText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10.5,
    color: '#FFFFFF',
  },
  progressBarTrack: {
    width: '100%',
    height: 4,
    backgroundColor: 'rgba(0, 0, 0, 0.25)',
    borderRadius: 2,
    overflow: 'hidden',
  },
  progressBarFill: {
    height: '100%',
    borderRadius: 2,
  },
  actionSyncRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 8,
    paddingTop: 6,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255, 255, 255, 0.14)',
  },
  syncButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.16)',
    borderWidth: 1,
    borderColor: 'rgba(94, 234, 212, 0.35)',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    gap: 6,
  },
  syncButtonActive: {
    backgroundColor: 'rgba(15, 118, 110, 0.45)',
    borderColor: 'rgba(94, 234, 212, 0.6)',
  },
  syncButtonText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    color: '#ECFEFF',
  },
  syncHeartEmoji: {
    fontSize: 10,
    marginLeft: 2,
  },
  lastSyncLabel: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 10,
    color: 'rgba(204, 251, 241, 0.75)',
  },
  activityStatus: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 8,
    paddingHorizontal: 10,
    paddingVertical: 7,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: '#4D8078',
    backgroundColor: '#134C46',
  },
  activityWarning: {
    borderColor: '#B48B3C',
    backgroundColor: '#423420',
  },
  activityStatusText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    lineHeight: 20,
    color: '#ECFEFF',
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  activityWarningText: {
    color: '#FEF3C7',
  },
});
