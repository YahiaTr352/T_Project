import React, { useEffect, useState } from 'react';
import {
  type LayoutChangeEvent,
  StyleSheet,
  View,
  useColorScheme,
} from 'react-native';
import Animated, {
  Easing,
  interpolate,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';
import { LinearGradient } from 'expo-linear-gradient';
import { SkeletonGroup, useSkeletonGroup } from './SkeletonGroup';
import type {
  SkeletonAnimationType,
  SkeletonBaseProps,
  SkeletonCircleProps,
  SkeletonTextProps,
} from './types';

// Theme-driven subtle color defaults for light and dark modes
const DEFAULT_LIGHT_BASE = '#E5E7EB';
const DEFAULT_LIGHT_HIGHLIGHT = 'rgba(255, 255, 255, 0.75)';

const DEFAULT_DARK_BASE = '#27272a';
const DEFAULT_DARK_HIGHLIGHT = 'rgba(255, 255, 255, 0.12)';

/**
 * Skeleton Core Primitive
 *
 * Provides a high-performance 60fps shimmer or pulse loading placeholder
 * that seamlessly adapts to the system theme (light/dark) and integrates
 * with `Skeleton.Group` for synchronized animations.
 */
export function Skeleton({
  width,
  height,
  borderRadius = 8,
  style,
  animation: propAnimation,
  duration: propDuration,
  baseColor: propBaseColor,
  highlightColor: propHighlightColor,
  children,
}: SkeletonBaseProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const group = useSkeletonGroup();

  const animation: SkeletonAnimationType =
    propAnimation ?? group?.animation ?? 'shimmer';
  const duration = propDuration ?? group?.duration ?? 1400;

  const baseColor =
    propBaseColor ??
    group?.baseColor ??
    (isDark ? DEFAULT_DARK_BASE : DEFAULT_LIGHT_BASE);

  const highlightColor =
    propHighlightColor ??
    group?.highlightColor ??
    (isDark ? DEFAULT_DARK_HIGHLIGHT : DEFAULT_LIGHT_HIGHLIGHT);

  // Standalone progress if not embedded in a SkeletonGroup
  const standaloneProgress = useSharedValue(0);
  const activeProgress = group?.progress ?? standaloneProgress;

  useEffect(() => {
    if (group?.isGrouped || animation === 'none') {
      return;
    }

    if (animation === 'shimmer') {
      standaloneProgress.value = 0;
      standaloneProgress.value = withRepeat(
        withTiming(1, { duration, easing: Easing.linear }),
        -1,
        false
      );
    } else if (animation === 'pulse') {
      standaloneProgress.value = 0;
      standaloneProgress.value = withRepeat(
        withTiming(1, {
          duration: duration / 2,
          easing: Easing.inOut(Easing.ease),
        }),
        -1,
        true
      );
    }
  }, [group?.isGrouped, animation, duration, standaloneProgress]);

  const initialWidth = typeof width === 'number' ? width : 300;
  const [measuredWidth, setMeasuredWidth] = useState<number>(initialWidth);

  const handleLayout = (e: LayoutChangeEvent) => {
    const layoutW = e.nativeEvent.layout.width;
    if (layoutW > 0 && Math.abs(layoutW - measuredWidth) > 1) {
      setMeasuredWidth(layoutW);
    }
  };

  const shimmerAnimatedStyle = useAnimatedStyle(() => {
    const w = measuredWidth || 300;
    const translateX = interpolate(
      activeProgress.value,
      [0, 1],
      [-w, w * 1.5]
    );
    return {
      transform: [{ translateX }],
    };
  });

  const pulseAnimatedStyle = useAnimatedStyle(() => {
    const opacity = interpolate(activeProgress.value, [0, 1], [1, 0.45]);
    return { opacity };
  });

  return (
    <View
      onLayout={handleLayout}
      accessibilityElementsHidden={true}
      importantForAccessibility="no-hide-descendants"
      style={[
        styles.base,
        {
          width,
          height,
          borderRadius,
          backgroundColor: baseColor,
        },
        style,
      ]}
    >
      {animation === 'shimmer' && (
        <Animated.View
          style={[
            StyleSheet.absoluteFill,
            { width: measuredWidth || '100%' },
            shimmerAnimatedStyle,
          ]}
          pointerEvents="none"
        >
          <LinearGradient
            colors={['transparent', highlightColor, 'transparent']}
            start={{ x: 0, y: 0.5 }}
            end={{ x: 1, y: 0.5 }}
            style={StyleSheet.absoluteFill}
          />
        </Animated.View>
      )}

      {animation === 'pulse' && (
        <Animated.View
          style={[
            StyleSheet.absoluteFill,
            { backgroundColor: highlightColor },
            pulseAnimatedStyle,
          ]}
          pointerEvents="none"
        />
      )}

      {children}
    </View>
  );
}

/**
 * Skeleton.Text
 *
 * Renders multiple placeholder lines with realistic paragraph variation
 * and configurable line heights and spacing.
 */
function SkeletonText({
  lines = 3,
  lineHeight = 14,
  gap = 8,
  lastLineWidth = '65%',
  lineWidths,
  align = 'left',
  style,
  borderRadius = 4,
  ...rest
}: SkeletonTextProps) {
  const lineArray = Array.from({ length: Math.max(1, lines) });

  return (
    <View
      style={[
        styles.textContainer,
        {
          gap,
          alignItems:
            align === 'center'
              ? 'center'
              : align === 'right'
              ? 'flex-end'
              : 'flex-start',
        },
        style,
      ]}
    >
      {lineArray.map((_, index) => {
        let width = '100%' as const;
        if (lineWidths && lineWidths[index] !== undefined) {
          width = lineWidths[index] as any;
        } else if (index === lineArray.length - 1 && lines > 1) {
          width = lastLineWidth as any;
        }

        return (
          <Skeleton
            key={index}
            width={width}
            height={lineHeight}
            borderRadius={borderRadius}
            {...rest}
          />
        );
      })}
    </View>
  );
}

/**
 * Skeleton.Circle
 *
 * Shortcut for circular placeholders (avatars, action icons, badges).
 */
function SkeletonCircle({ size, style, ...rest }: SkeletonCircleProps) {
  return (
    <Skeleton
      width={size}
      height={size}
      borderRadius={size / 2}
      style={style}
      {...rest}
    />
  );
}

/**
 * Skeleton.Rect / Skeleton.Box
 *
 * Semantic placeholder for images, banners, card blocks, or chips.
 */
function SkeletonRect(props: SkeletonBaseProps) {
  return <Skeleton {...props} />;
}

// Compound component pattern attachment
Skeleton.Text = SkeletonText;
Skeleton.Circle = SkeletonCircle;
Skeleton.Rect = SkeletonRect;
Skeleton.Box = SkeletonRect;
Skeleton.Group = SkeletonGroup;

const styles = StyleSheet.create({
  base: {
    overflow: 'hidden',
    position: 'relative',
  },
  textContainer: {
    width: '100%',
  },
});
