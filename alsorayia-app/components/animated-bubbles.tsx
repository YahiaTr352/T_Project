import React, { useEffect, useState } from 'react';
import { StyleSheet, View, useColorScheme } from 'react-native';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
  withSpring,
  interpolate,
  Easing,
} from 'react-native-reanimated';
import { Colors } from '@/constants/theme';

interface Bubble {
  id: number;
  size: number;
  initialX: number;
  initialY: number;
  duration: number;
  delay: number;
}

interface AnimatedBubblesProps {
  bubbleCount?: number;
  minSize?: number;
  maxSize?: number;
  animated?: boolean;
}

// توليد فقاعات عشوائية — يُستدعى من initializer الـ useState أو من الـ effect فقط
// (استدعاء Math.random أثناء الـ render مباشرة غير صالح في قواعد React Compiler)
function generateBubbles(count: number, minSize: number, maxSize: number): Bubble[] {
  return Array.from({ length: count }, (_, i) => ({
    id: i,
    size: minSize + Math.random() * (maxSize - minSize),
    initialX: Math.random() * 100, // Percentage of screen width
    initialY: Math.random() * 100, // Percentage of screen height
    duration: 3000 + Math.random() * 4000, // 3-7 seconds
    delay: Math.random() * 2000, // 0-2 seconds delay
  }));
}

export function AnimatedBubbles({
  bubbleCount,
  minSize = 40,
  maxSize = 120,
  animated = false,
}: AnimatedBubblesProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];

  // If not animated and no count specified, use 3 bubbles
  // If animated and no count specified, use 8 bubbles
  const count = bubbleCount ?? (animated ? 8 : 3);

  // Generate random bubbles ONCE (lazy initial state) — previously regenerated on
  // every render, making the green blobs jump around behind the content on each
  // state update (e.g. every walking sync), which looked like background flicker.
  const [bubbles, setBubbles] = useState<Bubble[]>(() =>
    generateBubbles(count, minSize, maxSize)
  );

  // إعادة التوليد فقط إذا تغيّرت خصائص التكوين (نادر التغير في كل المتصلين)
  useEffect(() => {
    setBubbles(generateBubbles(count, minSize, maxSize));
  }, [count, minSize, maxSize]);

  return (
    <View style={[StyleSheet.absoluteFill, { overflow: 'hidden' }]} pointerEvents="none">
      {bubbles.map((bubble) => (
        <Bubble
          key={bubble.id}
          size={bubble.size}
          initialX={bubble.initialX}
          initialY={bubble.initialY}
          duration={bubble.duration}
          delay={bubble.delay}
          color={colors.primary}
          animated={animated}
        />
      ))}
    </View>
  );
}

interface BubbleProps {
  size: number;
  initialX: number;
  initialY: number;
  duration: number;
  delay: number;
  color: string;
  animated: boolean;
}

function Bubble({ size, initialX, initialY, duration, delay, color, animated }: BubbleProps) {
  const translateY = useSharedValue(0);
  const translateX = useSharedValue(0);
  const scale = useSharedValue(1);
  const opacity = useSharedValue(0.07);

  useEffect(() => {
    if (!animated) {
      // Static bubbles - subtle background tint, no animation
      opacity.value = 0.07;
      scale.value = 1;
      return;
    }

    // Start animation after delay
    const timer = setTimeout(() => {
      // Vertical movement (floating up)
      translateY.value = withRepeat(
        withTiming(-100, {
          duration: duration,
          easing: Easing.linear,
        }),
        -1,
        false
      );

      // Horizontal movement (gentle drift)
      translateX.value = withRepeat(
        withTiming(initialX + (Math.random() * 20 - 10), {
          duration: duration * 1.5,
          easing: Easing.inOut(Easing.sin),
        }),
        -1,
        true
      );

      // Scale animation (breathing effect)
      scale.value = withRepeat(
        withSpring(1.1, {
          damping: 8,
          stiffness: 50,
        }),
        -1,
        true
      );

      // Opacity animation (fade in/out)
      opacity.value = withRepeat(
        withTiming(0.3, {
          duration: duration * 0.8,
          easing: Easing.inOut(Easing.sin),
        }),
        -1,
        true
      );
    }, delay);

    return () => clearTimeout(timer);
  }, [animated]);

  const animatedStyle = useAnimatedStyle(() => {
    if (!animated) {
      return {
        opacity: opacity.value,
        transform: [{ scale: scale.value }],
      };
    }

    return {
      transform: [
        { translateY: translateY.value },
        { translateX: translateX.value },
        { scale: scale.value },
      ],
      opacity: opacity.value,
    };
  });

  return (
    <Animated.View
      style={[
        styles.bubble,
        {
          width: size,
          height: size,
          borderRadius: size / 2,
          backgroundColor: color,
          left: `${initialX}%`,
          top: `${initialY}%`,
        },
        animatedStyle,
      ]}
    />
  );
}

const styles = StyleSheet.create({
  bubble: {
    position: 'absolute',
    opacity: 0.1,
  },
});
