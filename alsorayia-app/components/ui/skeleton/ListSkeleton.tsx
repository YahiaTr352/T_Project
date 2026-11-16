import React from 'react';
import { Dimensions, StyleSheet, View, useColorScheme } from 'react-native';
import { Colors } from '@/constants/theme';
import { Skeleton } from './Skeleton';
import type { ListSkeletonProps } from './types';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

export function ListSkeleton({
  count = 5,
  variant = 'notification',
  style,
}: ListSkeletonProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const items = Array.from({ length: count });

  if (variant === 'grid') {
    const itemWidth = (SCREEN_WIDTH - 32 - 12) / 2;
    return (
      <View style={[styles.gridContainer, style]}>
        {items.map((_, i) => (
          <View key={i} style={[styles.gridItem, { width: itemWidth }]}>
            <Skeleton width="100%" height={150} borderRadius={14} />
          </View>
        ))}
      </View>
    );
  }

  if (variant === 'card') {
    return (
      <View style={[styles.container, style]}>
        {items.map((_, i) => (
          <View
            key={i}
            style={[
              styles.cardItem,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View style={styles.cardHeader}>
              <Skeleton.Circle size={40} />
              <View style={styles.cardHeaderDetails}>
                <Skeleton width="40%" height={14} borderRadius={4} />
                <Skeleton width="25%" height={10} borderRadius={4} style={{ marginTop: 6 }} />
              </View>
            </View>
            <Skeleton.Text lines={2} lineHeight={12} gap={6} lastLineWidth="70%" />
            <Skeleton width="100%" height={140} borderRadius={10} style={{ marginTop: 10 }} />
          </View>
        ))}
      </View>
    );
  }

  if (variant === 'compact') {
    return (
      <View style={[styles.container, style]}>
        {items.map((_, i) => (
          <View
            key={i}
            style={[
              styles.compactItem,
              { borderColor: colors.border },
              i === items.length - 1 && styles.noBorder,
            ]}
          >
            <Skeleton.Circle size={32} />
            <View style={styles.flexOne}>
              <Skeleton width="60%" height={14} borderRadius={4} />
            </View>
            <Skeleton width={18} height={18} borderRadius={4} />
          </View>
        ))}
      </View>
    );
  }

  // Default: 'notification' or 'feed' row variant
  return (
    <View style={[styles.container, style]}>
      {items.map((_, i) => (
        <View
          key={i}
          style={[
            styles.notificationItem,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          {/* Avatar or Category Icon */}
          <Skeleton.Circle size={44} />

          {/* Texts and timestamp */}
          <View style={styles.notificationContent}>
            <View style={styles.notificationTopRow}>
              <Skeleton width="55%" height={14} borderRadius={4} />
              <Skeleton width={50} height={10} borderRadius={4} />
            </View>
            <Skeleton.Text
              lines={2}
              lineHeight={11}
              gap={6}
              lastLineWidth="80%"
              style={{ marginTop: 6 }}
            />
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 10,
  },
  notificationItem: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    gap: 12,
  },
  notificationContent: {
    flex: 1,
  },
  notificationTopRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    width: '100%',
  },
  cardItem: {
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 12,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 12,
  },
  cardHeaderDetails: {
    flex: 1,
  },
  compactItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 12,
  },
  noBorder: {
    borderBottomWidth: 0,
  },
  flexOne: {
    flex: 1,
  },
  gridContainer: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  gridItem: {
    overflow: 'hidden',
  },
});
