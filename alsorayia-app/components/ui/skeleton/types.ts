import type React from 'react';
import type { DimensionValue, StyleProp, ViewStyle } from 'react-native';

export type SkeletonAnimationType = 'shimmer' | 'pulse' | 'none';

export interface SkeletonThemeColors {
  base: string;
  highlight: string;
}

export interface SkeletonBaseProps {
  /** Width of the skeleton item (e.g. 100, '100%', '60%') */
  width?: DimensionValue;
  /** Height of the skeleton item (e.g. 20, 150) */
  height?: DimensionValue;
  /** Border radius for rounding corners (default: 8) */
  borderRadius?: number;
  /** Custom additional styling */
  style?: StyleProp<ViewStyle>;
  /** Animation type: 'shimmer' (linear sweep), 'pulse' (opacity breathing), or 'none' */
  animation?: SkeletonAnimationType;
  /** Duration of one full animation cycle in milliseconds (default: 1400) */
  duration?: number;
  /** Explicit override for the background base color */
  baseColor?: string;
  /** Explicit override for the shimmer highlight color */
  highlightColor?: string;
  /** Nested elements (if used as container placeholder) */
  children?: React.ReactNode;
}

export interface SkeletonTextProps extends Omit<SkeletonBaseProps, 'children'> {
  /** Number of text lines to display (default: 3) */
  lines?: number;
  /** Height of each text line in pixels (default: 14) */
  lineHeight?: number;
  /** Gap between adjacent lines in pixels (default: 8) */
  gap?: number;
  /** Width of the last line to simulate realistic paragraphs (default: '65%') */
  lastLineWidth?: DimensionValue;
  /** Explicit widths for each line (overrides lastLineWidth) */
  lineWidths?: DimensionValue[];
  /** Alignment of lines: 'left' | 'center' | 'right' */
  align?: 'left' | 'center' | 'right';
}

export interface SkeletonCircleProps extends Omit<SkeletonBaseProps, 'width' | 'height' | 'borderRadius'> {
  /** Diameter of the circle in pixels */
  size: number;
}

export interface SkeletonGroupProps {
  children: React.ReactNode;
  /** Animation mode applied to all children in this group (default: 'shimmer') */
  animation?: SkeletonAnimationType;
  /** Duration in ms for one synchronized animation loop across all children */
  duration?: number;
  /** Base background color override for children in this group */
  baseColor?: string;
  /** Highlight color override for children in this group */
  highlightColor?: string;
}

export interface ListSkeletonProps {
  /** Number of list skeleton rows to render (default: 5) */
  count?: number;
  /** Visual preset for the list item */
  variant?: 'notification' | 'feed' | 'compact' | 'card' | 'grid';
  /** Custom container style */
  style?: StyleProp<ViewStyle>;
}
