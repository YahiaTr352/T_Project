import { ThemedText } from '@/components/themed-text';
import { Colors, silaTheme } from '@/constants/theme';
import { formatSteps } from '@/lib/walking/formatters';
import type { DayBarPoint } from '@/lib/walking/ledger';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useMemo } from 'react';
import { StyleSheet, View, useColorScheme } from 'react-native';

type Props = {
  points: DayBarPoint[];
};

export function StepsBarChart({ points }: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';
  const maxValue = useMemo(() => Math.max(1, ...points.map((p) => p.value)), [points]);

  return (
    <View
      style={[
        styles.chartContainer,
        {
          backgroundColor: isDark ? '#18181B' : '#FFFFFF',
          borderColor: colors.border,
        },
      ]}>
      <View
        style={[
          styles.chart,
          {
            direction: 'ltr',
            flexDirection: 'row',
          },
        ]}>
        {points.map((data, index) => {
          const isToday = data.isToday ?? (index === 0);
          const height = Math.max(6, (data.value / maxValue) * 92);

          return (
            <View key={data.key} style={styles.barContainer}>
              <View style={styles.barWrapper}>
                <View style={[styles.barOutline, { height }]}>
                  <LinearGradient
                    colors={
                      isToday
                        ? ['#F59E0B', '#10B981']
                        : isDark
                        ? ['#059669', '#047857']
                        : ['#34D399', '#059669']
                    }
                    start={{ x: 0, y: 0 }}
                    end={{ x: 0, y: 1 }}
                    style={styles.barFill}
                  />
                </View>
              </View>

              <ThemedText
                numberOfLines={1}
                adjustsFontSizeToFit
                minimumFontScale={0.8}
                style={[
                  styles.barLabel,
                  { color: isToday ? silaTheme.primary : colors.icon },
                  isToday && styles.barLabelToday,
                ]}>
                {data.dayName || (isToday ? 'اليوم' : data.label)}
              </ThemedText>

              <ThemedText
                numberOfLines={1}
                adjustsFontSizeToFit
                minimumFontScale={0.8}
                style={[
                  styles.barValue,
                  { color: isToday ? silaTheme.primary : colors.text },
                  isToday && styles.barValueToday,
                ]}>
                {formatSteps(data.value)}
              </ThemedText>
            </View>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  chartContainer: {
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
  },
  chart: {
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    height: 135,
    gap: 4,
  },
  barContainer: {
    flex: 1,
    alignItems: 'center',
    gap: 6,
  },
  barWrapper: {
    flex: 1,
    justifyContent: 'flex-end',
    width: '100%',
    alignItems: 'center',
  },
  barOutline: {
    width: '78%',
    borderRadius: 8,
    overflow: 'hidden',
  },
  barFill: {
    flex: 1,
    borderRadius: 8,
  },
  barLabel: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
  },
  barLabelToday: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11.5,
  },
  barValue: {
    fontSize: 9,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  barValueToday: {
    fontSize: 9.5,
  },
});
