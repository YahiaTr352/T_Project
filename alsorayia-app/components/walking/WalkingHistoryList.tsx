import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, silaTheme } from '@/constants/theme';
import type { WalkingRecord } from '@/lib/api/walkingApi';
import { cleanDateYMD, formatDateAr, formatSteps, getStepTierConfig } from '@/lib/walking/formatters';
import React from 'react';
import { ActivityIndicator, Platform, StyleSheet, View, useColorScheme } from 'react-native';

type Props = {
  records: WalkingRecord[];
  isLoading: boolean;
  localSteps?: Record<string, number>;
  today?: { dateKey: string; steps: number | null; status?: string };
  showTimeZone?: boolean;
  refreshFailed?: boolean;
};

export function WalkingHistoryList({ records, isLoading, localSteps, today, showTimeZone, refreshFailed }: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';
  const rows: { key: number | string; date: string; record?: WalkingRecord }[] = today
    ? [...new Set([today.dateKey, ...records.map((record) => cleanDateYMD(record.date)), ...Object.keys(localSteps ?? {})])]
      .filter((date) => date && date <= today.dateKey)
      .sort((a, b) => b.localeCompare(a))
      .slice(0, 30)
      .map((date) => ({ key: date, date }))
    : records.map((record) => ({ key: record.id || record.date, date: record.date, record }));

  if (isLoading && rows.length === 0) {
    return (
      <View style={styles.loadingBox}>
        <ActivityIndicator size="large" color={silaTheme.primary} />
        <ThemedText style={[styles.loadingText, { color: colors.icon }]}>
          جاري استرجاع سجل الأيام السابقة...
        </ThemedText>
      </View>
    );
  }

  if (rows.length === 0) {
    return (
      <View
        style={[
          styles.emptyCard,
          {
            backgroundColor: isDark ? '#18181B' : '#F9FAFB',
            borderColor: colors.border,
          },
        ]}>
        <ThemedText style={{ fontSize: 32, marginBottom: 6 }}>📜</ThemedText>
        <ThemedText style={styles.emptyTitle}>لا يوجد سجل سابق مسجل</ThemedText>
        <ThemedText style={[styles.emptySubtitle, { color: colors.icon }]}>
          ستظهر خطواتك ونشاطك التاريخي هنا تلقائياً يوماً بيوم.
        </ThemedText>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {refreshFailed && <ThemedText style={[styles.metricText, { color: colors.icon }]}>
        تعذّر تحديث الأيام السابقة من صحتي
      </ThemedText>}
      {rows.map(({ key, date: rowDate, record }) => {
        const date = cleanDateYMD(rowDate);
        const currentDay = today?.dateKey === date ? today : undefined;
        const local = localSteps?.[date];
        const displayedSteps = currentDay ? currentDay.steps : local;
        const steps = displayedSteps ?? record?.steps ?? null;
        const distanceKm = displayedSteps != null ? Number((displayedSteps * 0.00076).toFixed(2)) : record?.distance_km;
        const calories = displayedSteps != null ? Math.round(displayedSteps * 0.04) : record?.calories;
        const minutes = displayedSteps != null ? Math.round(displayedSteps / 100) : record?.duration_minutes;
        const isDeviceTime = !!today || local != null;
        const tier = getStepTierConfig(steps ?? 0, isDark);

        return (
          <View
            key={key}
            style={[
              styles.card,
              {
                backgroundColor: isDark ? '#18181B' : '#FFFFFF',
                borderColor: colors.border,
              },
            ]}>
            <View style={styles.headerRow}>
              <View style={styles.dateColumn}>
                <ThemedText style={styles.dateText}>{formatDateAr(rowDate, { includeWeekday: true })}</ThemedText>
                {showTimeZone && (
                  <ThemedText style={[styles.metricText, { color: colors.icon }]}>
                    {isDeviceTime ? 'بتوقيت الجهاز' : 'بتوقيت الرياض'}
                  </ThemedText>
                )}
              </View>
              <View
                style={[
                  styles.stepsPill,
                  {
                    backgroundColor: isDark ? tier.badgeBgDark : tier.badgeBgLight,
                    borderWidth: 1,
                    borderColor: tier.borderColor,
                  },
                ]}>
                <ThemedText style={[styles.stepsVal, { color: tier.textColor }]}>
                  {steps === null ? '—' : `${formatSteps(steps)} خطوة`}
                </ThemedText>
              </View>
            </View>

            {currentDay?.status || steps === null ? (
              <ThemedText style={[styles.metricText, { color: colors.icon }]}>
                {currentDay?.status ?? 'قراءة صحتي غير متاحة لهذا اليوم'}
              </ThemedText>
            ) : <View style={styles.metricsRow}>
              {distanceKm != null && (
                <View style={styles.metricItem}>
                  <IconSymbol name="mappin.circle.fill" size={14} color="#10B981" />
                  <ThemedText style={[styles.metricText, { color: colors.icon }]}>
                    {distanceKm} كم
                  </ThemedText>
                </View>
              )}

              {calories != null && (
                <View style={styles.metricItem}>
                  <ThemedText style={{ fontSize: 12 }}>🔥</ThemedText>
                  <ThemedText style={[styles.metricText, { color: colors.icon }]}>
                    {formatSteps(calories)} ك.س
                  </ThemedText>
                </View>
              )}

              {minutes != null && (
                <View style={styles.metricItem}>
                  <IconSymbol name="clock.fill" size={14} color="#3B82F6" />
                  <ThemedText style={[styles.metricText, { color: colors.icon }]}>
                    {minutes} دقيقة
                  </ThemedText>
                </View>
              )}
            </View>}
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 10,
    marginVertical: 10,
  },
  dateColumn: {
    flexShrink: 1,
  },
  loadingBox: {
    paddingVertical: 40,
    alignItems: 'center',
    gap: 10,
  },
  loadingText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
  emptyCard: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 24,
    alignItems: 'center',
    marginVertical: 10,
  },
  emptyTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    marginBottom: 4,
  },
  emptySubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    textAlign: 'center',
  },
  card: {
    borderRadius: 18,
    borderWidth: 1,
    padding: 14,
    gap: 8,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.04,
        shadowRadius: 4,
      },
      android: { elevation: 1 },
    }),
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  dateText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  stepsPill: {
    backgroundColor: 'rgba(16, 185, 129, 0.12)',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 10,
  },
  stepsVal: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    color: '#059669',
  },
  metricsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  metricItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  metricText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
});
