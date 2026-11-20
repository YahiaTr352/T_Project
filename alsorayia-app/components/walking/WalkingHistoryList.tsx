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
  showTimeZone?: boolean;
};

export function WalkingHistoryList({ records, isLoading, localSteps, showTimeZone }: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  if (isLoading && records.length === 0) {
    return (
      <View style={styles.loadingBox}>
        <ActivityIndicator size="large" color={silaTheme.primary} />
        <ThemedText style={[styles.loadingText, { color: colors.icon }]}>
          جاري استرجاع سجل الأيام السابقة...
        </ThemedText>
      </View>
    );
  }

  if (records.length === 0) {
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
      {records.map((rec) => {
        const local = localSteps?.[cleanDateYMD(rec.date)];
        const steps = local ?? rec.steps;
        const distanceKm = local != null ? Number((steps * 0.00076).toFixed(2)) : rec.distance_km;
        const calories = local != null ? Math.round(steps * 0.04) : rec.calories;
        const minutes = local != null ? Math.round(steps / 100) : rec.duration_minutes;
        const tier = getStepTierConfig(steps, isDark);

        return (
          <View
            key={rec.id || rec.date}
            style={[
              styles.card,
              {
                backgroundColor: isDark ? '#18181B' : '#FFFFFF',
                borderColor: colors.border,
              },
            ]}>
            <View style={styles.headerRow}>
              <View style={styles.dateColumn}>
                <ThemedText style={styles.dateText}>{formatDateAr(rec.date, { includeWeekday: true })}</ThemedText>
                {showTimeZone && (
                  <ThemedText style={[styles.metricText, { color: colors.icon }]}>
                    {local != null ? 'بتوقيت الجهاز' : 'بتوقيت الرياض'}
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
                  {formatSteps(steps)} خطوة
                </ThemedText>
              </View>
            </View>

            <View style={styles.metricsRow}>
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
            </View>
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
