import React from 'react';
import { StyleSheet, View, useColorScheme, type StyleProp, type ViewStyle } from 'react-native';
import { Colors } from '@/constants/theme';
import { Skeleton } from './Skeleton';

export interface CardSkeletonProps {
  /** Orientation of card contents (default: 'vertical') */
  orientation?: 'vertical' | 'horizontal';
  /** Height of the image placeholder (default: 120 for vertical, 80 for horizontal) */
  imageHeight?: number;
  /** Width of the image placeholder (primarily for horizontal layout, default: 80) */
  imageWidth?: number | string;
  /** Number of text description lines (default: 2) */
  lines?: number;
  /** Whether to show a badge or avatar on top (default: false) */
  showBadge?: boolean;
  /** Custom container style */
  style?: StyleProp<ViewStyle>;
}

export function CardSkeleton({
  orientation = 'vertical',
  imageHeight,
  imageWidth,
  lines = 2,
  showBadge = false,
  style,
}: CardSkeletonProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];

  const isHorizontal = orientation === 'horizontal';
  const imgH = imageHeight ?? (isHorizontal ? 80 : 130);
  const imgW = imageWidth ?? (isHorizontal ? 80 : '100%');

  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          flexDirection: isHorizontal ? 'row' : 'column',
        },
        style,
      ]}
    >
      {/* Thumbnail / Image placeholder */}
      <Skeleton
        width={imgW as any}
        height={imgH}
        borderRadius={isHorizontal ? 10 : 12}
      />

      {/* Content lines */}
      <View
        style={[
          styles.content,
          isHorizontal ? styles.horizontalContent : styles.verticalContent,
        ]}
      >
        {showBadge && (
          <Skeleton
            width={60}
            height={16}
            borderRadius={10}
            style={styles.badge}
          />
        )}
        <Skeleton
          width="85%"
          height={16}
          borderRadius={4}
          style={styles.title}
        />
        <Skeleton.Text
          lines={lines}
          lineHeight={12}
          gap={6}
          lastLineWidth="50%"
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 12,
    gap: 12,
    overflow: 'hidden',
  },
  content: {
    flex: 1,
  },
  verticalContent: {
    paddingTop: 4,
  },
  horizontalContent: {
    justifyContent: 'center',
  },
  badge: {
    marginBottom: 8,
  },
  title: {
    marginBottom: 8,
  },
});
