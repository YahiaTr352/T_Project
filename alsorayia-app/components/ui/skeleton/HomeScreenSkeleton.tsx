import React from 'react';
import { Dimensions, ScrollView, StyleSheet, View, useColorScheme } from 'react-native';
import { Colors, type ThemeColors } from '@/constants/theme';
import { Skeleton } from './Skeleton';
import { SkeletonGroup } from './SkeletonGroup';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

interface HomeScreenSkeletonProps {
  colors?: ThemeColors;
  showNews?: boolean;
}

/**
 * HomeScreenSkeleton
 *
 * A high-fidelity, zero-layout-shift skeleton screen specifically designed
 * for the main application Home screen (app/(tabs)/index.tsx).
 * Synchronized via SkeletonGroup for an ultra-smooth, premium shimmer experience.
 */
export function HomeScreenSkeleton({ colors: propColors, showNews = false }: HomeScreenSkeletonProps) {
  const colorScheme = useColorScheme();
  const colors = propColors ?? Colors[colorScheme ?? 'light'];

  return (
    <SkeletonGroup animation="shimmer" duration={1500}>
      <View style={styles.container}>
        {/* 1. Hero Slider Skeleton */}
        <View style={styles.sliderWrapper}>
          <Skeleton
            width={SCREEN_WIDTH}
            height={200}
            borderRadius={0}
            style={styles.sliderBox}
          >
            <View style={styles.slideContent}>
              <Skeleton
                width="65%"
                height={22}
                borderRadius={6}
                baseColor={colorScheme === 'dark' ? 'rgba(255,255,255,0.15)' : 'rgba(255,255,255,0.45)'}
                highlightColor={colorScheme === 'dark' ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.7)'}
              />
              <Skeleton
                width="40%"
                height={14}
                borderRadius={4}
                baseColor={colorScheme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(255,255,255,0.35)'}
                highlightColor={colorScheme === 'dark' ? 'rgba(255,255,255,0.2)' : 'rgba(255,255,255,0.6)'}
                style={{ marginTop: 8 }}
              />
            </View>

            {/* Pagination Dots */}
            <View style={styles.pagination}>
              <Skeleton width={24} height={8} borderRadius={4} />
              <Skeleton width={8} height={8} borderRadius={4} />
              <Skeleton width={8} height={8} borderRadius={4} />
            </View>
          </Skeleton>
        </View>

        {/* 2. Intro Card Skeleton */}
        <View style={styles.sectionContainer}>
          <View
            style={[
              styles.introCard,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Skeleton width={80} height={16} borderRadius={8} style={{ marginBottom: 12 }} />
            <Skeleton.Text lines={3} lineHeight={14} gap={8} lastLineWidth="60%" />
          </View>
        </View>

        {/* 3. Quiz / Poll Widget Skeleton */}
        <View style={styles.sectionContainer}>
          <View
            style={[
              styles.quizCard,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View style={styles.quizHeader}>
              <View style={styles.quizBadge}>
                <Skeleton.Circle size={8} />
                <Skeleton width={50} height={12} borderRadius={4} />
              </View>
              <Skeleton width={120} height={16} borderRadius={4} />
            </View>
            <Skeleton width="75%" height={16} borderRadius={4} style={{ marginTop: 12, marginBottom: 8 }} />
            <Skeleton.Text lines={2} lineHeight={12} gap={6} lastLineWidth="85%" />
            <View style={styles.quizOptionRow}>
              <Skeleton width="48%" height={38} borderRadius={10} />
              <Skeleton width="48%" height={38} borderRadius={10} />
            </View>
          </View>
        </View>

        {/* 4. Family News Section Skeleton */}
        {showNews ? (
        <View style={styles.sectionContainer}>
          <View style={styles.sectionHeader}>
            <Skeleton width={110} height={20} borderRadius={6} />
            <Skeleton width={50} height={16} borderRadius={4} />
          </View>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.horizontalList}
          >
            {[1, 2, 3].map((item) => (
              <View
                key={item}
                style={[
                  styles.newsCard,
                  { backgroundColor: colors.card, borderColor: colors.border },
                ]}
              >
                <Skeleton width="100%" height={110} borderRadius={12} />
                <Skeleton width="85%" height={14} borderRadius={4} style={{ marginTop: 10 }} />
                <Skeleton width="55%" height={11} borderRadius={4} style={{ marginTop: 6 }} />
              </View>
            ))}
          </ScrollView>
        </View>
        ) : null}

        {/* 5. Family Councils / Events Table Skeleton */}
        <View style={styles.sectionContainer}>
          <View style={styles.sectionHeader}>
            <Skeleton width={130} height={20} borderRadius={6} />
          </View>
          <View
            style={[
              styles.tableCard,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            {[1, 2, 3].map((row, idx) => (
              <View
                key={row}
                style={[
                  styles.tableRow,
                  { borderColor: colors.border },
                  idx === 2 && styles.noBorder,
                ]}
              >
                <View style={styles.tableCol1}>
                  <Skeleton width="70%" height={14} borderRadius={4} />
                  <Skeleton width="90%" height={11} borderRadius={4} style={{ marginTop: 6 }} />
                </View>
                <Skeleton width={50} height={12} borderRadius={4} />
                <Skeleton.Circle size={32} />
              </View>
            ))}
          </View>
        </View>

        {/* 6. Programs Grid Skeleton */}
        <View style={styles.sectionContainer}>
          <View style={styles.sectionHeader}>
            <Skeleton width={120} height={20} borderRadius={6} />
          </View>
          <View style={styles.programsGrid}>
            {[1, 2, 3, 4].map((p) => (
              <View
                key={p}
                style={[
                  styles.programCard,
                  { backgroundColor: colors.card, borderColor: colors.border },
                ]}
              >
                <Skeleton width="100%" height={90} borderRadius={12} />
              </View>
            ))}
          </View>
        </View>

        {/* Bottom spacer */}
        <View style={{ height: 40 }} />
      </View>
    </SkeletonGroup>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  sliderWrapper: {
    height: 200,
    marginBottom: 20,
  },
  sliderBox: {
    position: 'relative',
    justifyContent: 'flex-end',
    padding: 20,
  },
  slideContent: {
    position: 'absolute',
    bottom: 35,
    left: 20,
    right: 20,
  },
  pagination: {
    position: 'absolute',
    bottom: 12,
    alignSelf: 'center',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  sectionContainer: {
    marginHorizontal: 16,
    marginBottom: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  introCard: {
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
  },
  quizCard: {
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
  },
  quizHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  quizBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  quizOptionRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 14,
  },
  horizontalList: {
    gap: 12,
    paddingRight: 16,
  },
  newsCard: {
    width: 220,
    padding: 10,
    borderRadius: 16,
    borderWidth: 1,
  },
  tableCard: {
    borderRadius: 16,
    borderWidth: 1,
    paddingHorizontal: 14,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 12,
  },
  noBorder: {
    borderBottomWidth: 0,
  },
  tableCol1: {
    flex: 1,
  },
  programsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  programCard: {
    width: (SCREEN_WIDTH - 32 - 12) / 2,
    padding: 6,
    borderRadius: 14,
    borderWidth: 1,
  },
});
