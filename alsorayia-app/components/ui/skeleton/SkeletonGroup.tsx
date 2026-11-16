import React, { createContext, useContext, useEffect } from 'react';
import {
  Easing,
  type SharedValue,
  useSharedValue,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';
import type { SkeletonAnimationType, SkeletonGroupProps } from './types';

interface SkeletonGroupContextValue {
  progress: SharedValue<number>;
  animation: SkeletonAnimationType;
  duration: number;
  baseColor?: string;
  highlightColor?: string;
  isGrouped: boolean;
}

const SkeletonGroupContext = createContext<SkeletonGroupContextValue | null>(null);

export function useSkeletonGroup(): SkeletonGroupContextValue | null {
  return useContext(SkeletonGroupContext);
}

/**
 * Skeleton.Group / SkeletonGroup
 *
 * Coordinates and synchronizes animation timing (shimmer sweeps or pulse loops)
 * across all descendant Skeleton elements on a screen for unified visual harmony.
 */
export function SkeletonGroup({
  children,
  animation = 'shimmer',
  duration = 1400,
  baseColor,
  highlightColor,
}: SkeletonGroupProps) {
  const progress = useSharedValue(0);

  useEffect(() => {
    if (animation === 'none') {
      progress.value = 0;
      return;
    }

    if (animation === 'shimmer') {
      progress.value = 0;
      progress.value = withRepeat(
        withTiming(1, { duration, easing: Easing.linear }),
        -1,
        false
      );
    } else if (animation === 'pulse') {
      progress.value = 0;
      progress.value = withRepeat(
        withTiming(1, { duration: duration / 2, easing: Easing.inOut(Easing.ease) }),
        -1,
        true
      );
    }
  }, [animation, duration, progress]);

  const value: SkeletonGroupContextValue = {
    progress,
    animation,
    duration,
    baseColor,
    highlightColor,
    isGrouped: true,
  };

  return (
    <SkeletonGroupContext.Provider value={value}>
      {children}
    </SkeletonGroupContext.Provider>
  );
}
