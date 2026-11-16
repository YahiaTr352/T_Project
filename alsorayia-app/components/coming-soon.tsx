import { Colors, silaTheme as T } from '@/constants/theme';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect } from 'react';
import {
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from 'react-native';
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withTiming,
} from 'react-native-reanimated';

interface ComingSoonProps {
  /** العنوان الرئيسي */
  title: string;
  /** وصف توضيحي أسفل العنوان */
  subtitle?: string;
  /** نص الشارة — الافتراضي "قريبًا إن شاء الله" */
  badgeText?: string;
  /** اسم أيقونة SF Symbols */
  iconName?: string;
}

/**
 * مكون "قريبًا" قابل لإعادة الاستخدام — يعرض أيقونة متحركة
 * مع عنوان ووصف وشارة حالة.
 * يتكيف تلقائيًا مع الوضع الليلي/النهاري.
 */
export function ComingSoon({
  title,
  subtitle,
  badgeText = 'قريبًا إن شاء الله',
  iconName = 'sparkles',
}: ComingSoonProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const { width } = useWindowDimensions();

  /* ── أنيميشن: نبض + دوران خفيف + توهج ── */
  const pulse = useSharedValue(1);
  const rotate = useSharedValue(0);
  const glowOpacity = useSharedValue(0.3);

  useEffect(() => {
    pulse.value = withRepeat(
      withSequence(
        withTiming(1.15, { duration: 1200, easing: Easing.inOut(Easing.ease) }),
        withTiming(1, { duration: 1200, easing: Easing.inOut(Easing.ease) }),
      ),
      -1,
      false,
    );
    rotate.value = withRepeat(
      withSequence(
        withTiming(8, { duration: 2000, easing: Easing.inOut(Easing.ease) }),
        withTiming(-8, { duration: 2000, easing: Easing.inOut(Easing.ease) }),
      ),
      -1,
      false,
    );
    glowOpacity.value = withRepeat(
      withSequence(
        withTiming(0.6, { duration: 1500, easing: Easing.inOut(Easing.ease) }),
        withTiming(0.2, { duration: 1500, easing: Easing.inOut(Easing.ease) }),
      ),
      -1,
      false,
    );
  }, []);

  const iconStyle = useAnimatedStyle(() => ({
    transform: [
      { scale: pulse.value },
      { rotate: `${rotate.value}deg` },
    ],
  }));

  const glowStyle = useAnimatedStyle(() => ({
    opacity: glowOpacity.value,
  }));

  const iconSize = Math.min(width * 0.28, 120);

  return (
    <View style={styles.content}>
      {/* Glow ring behind the icon */}
      <Animated.View
        style={[
          glowStyle,
          styles.glowRing,
          {
            width: iconSize * 2.2,
            height: iconSize * 2.2,
            borderRadius: iconSize * 1.1,
            backgroundColor: isDark ? T.primaryDark : T.primaryLight,
          },
        ]}
      />

      {/* Animated icon */}
      <Animated.View style={[iconStyle, styles.iconContainer]}>
        <LinearGradient
          colors={
            isDark
              ? ['#1a5c3a', '#0d3320']
              : [T.primaryLight, T.primary]
          }
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={[
            styles.iconBg,
            {
              width: iconSize,
              height: iconSize,
              borderRadius: iconSize * 0.32,
            },
          ]}
        >
          <IconSymbol
            name={iconName as any}
            size={iconSize * 0.5}
            color={isDark ? T.primaryLight : T.white}
          />
        </LinearGradient>
      </Animated.View>

      {/* Text group */}
      <View style={styles.textGroup}>
        <Text style={[styles.title, { color: colors.text }]}>
          {title}
        </Text>
        {subtitle ? (
          <Text
            style={[
              styles.subtitle,
              { color: isDark ? '#9CA3AF' : '#6B7280' },
            ]}
          >
            {subtitle}
          </Text>
        ) : null}
      </View>

      {/* Badge */}
      <View
        style={[
          styles.badge,
          {
            backgroundColor: isDark
              ? 'rgba(74, 222, 128, 0.12)'
              : 'rgba(55, 160, 92, 0.08)',
            borderColor: isDark
              ? 'rgba(74, 222, 128, 0.25)'
              : 'rgba(55, 160, 92, 0.2)',
          },
        ]}
      >
        <View
          style={[styles.badgeDot, { backgroundColor: T.primary }]}
        />
        <Text
          style={[
            styles.badgeText,
            { color: isDark ? T.primaryLight : T.primaryDark },
          ]}
        >
          {badgeText}
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  content: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
    gap: 8,
  },
  glowRing: {
    position: 'absolute',
  },
  iconContainer: {
    marginBottom: 24,
  },
  iconBg: {
    justifyContent: 'center',
    alignItems: 'center',
    ...T.shadowStrong,
  },
  textGroup: {
    alignItems: 'center',
    gap: 8,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 28,
    textAlign: 'center',
  },
  subtitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 16,
    textAlign: 'center',
    lineHeight: 26,
    maxWidth: 280,
  },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 24,
    borderWidth: 1,
    marginTop: 16,
  },
  badgeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  badgeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
});
