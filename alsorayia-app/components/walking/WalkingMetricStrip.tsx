import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import React from 'react';
import { StyleSheet, View, useColorScheme } from 'react-native';

import { formatSteps } from '@/lib/walking/formatters';

type Props = {
  monthlySteps: number;
  totalSteps: number;
};

export function WalkingMetricStrip({ monthlySteps, totalSteps }: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const cardBg = colorScheme === 'dark' ? colors.card : '#FFFFFF';

  return (
    <View style={styles.row}>
      <View style={[styles.card, { backgroundColor: cardBg, borderColor: colors.border }]}>
        <View style={[styles.iconWrap, { backgroundColor: '#3B82F620' }]}>
          <IconSymbol name="calendar" size={22} color="#3B82F6" />
        </View>
        <ThemedText style={styles.value}>{formatSteps(monthlySteps)}</ThemedText>
        <ThemedText style={[styles.label, { color: colors.icon }]}>هذا الشهر</ThemedText>
      </View>
      <View style={[styles.card, { backgroundColor: cardBg, borderColor: colors.border }]}>
        <View style={[styles.iconWrap, { backgroundColor: '#8B5CF620' }]}>
          <IconSymbol name="chart.bar.fill" size={22} color="#8B5CF6" />
        </View>
        <ThemedText style={styles.value}>{formatSteps(totalSteps)}</ThemedText>
        <ThemedText style={[styles.label, { color: colors.icon }]}>الإجمالي</ThemedText>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 4,
  },
  card: {
    flex: 1,
    borderRadius: 18,
    borderWidth: 1,
    paddingVertical: 18,
    paddingHorizontal: 14,
    alignItems: 'center',
  },
  iconWrap: {
    width: 44,
    height: 44,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 10,
  },
  value: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
    marginBottom: 4,
  },
  label: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    textAlign: 'center',
  },
});
