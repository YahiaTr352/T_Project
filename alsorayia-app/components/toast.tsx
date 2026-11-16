import { Colors } from '@/constants/theme';
import React, { useEffect, useState } from 'react';
import { StyleSheet, View, useColorScheme } from 'react-native';
import Animated, {
    runOnJS,
    useAnimatedStyle,
    useSharedValue,
    withSpring,
    withTiming,
} from 'react-native-reanimated';
import { ThemedText } from './themed-text';
import { IconSymbol } from './ui/icon-symbol';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

interface ToastProps {
  message: string;
  type?: ToastType;
  visible: boolean;
  duration?: number;
  onHide?: () => void;
}

const ToastConfig = {
  success: {
    icon: 'checkmark.circle.fill',
    backgroundColor: '#10B981',
    iconColor: '#FFFFFF',
  },
  error: {
    icon: 'xmark.circle.fill',
    backgroundColor: '#EF4444',
    iconColor: '#FFFFFF',
  },
  info: {
    icon: 'info.circle.fill',
    backgroundColor: '#3B82F6',
    iconColor: '#FFFFFF',
  },
  warning: {
    icon: 'exclamationmark.triangle.fill',
    backgroundColor: '#F59E0B',
    iconColor: '#FFFFFF',
  },
};

export function Toast({ message, type = 'info', visible, duration = 3000, onHide }: ToastProps) {
  const translateY = useSharedValue(-100);
  const opacity = useSharedValue(0);
  const scale = useSharedValue(0.8);
  const [mounted, setMounted] = useState(visible);
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const config = ToastConfig[type];

  // Callback defined strictly on the JS Runtime
  const onAnimationFinished = React.useCallback(() => {
    setMounted(false);
    onHide?.();
  }, [onHide]);

  const hideToast = React.useCallback(() => {
    // تعديلات .value هي الواجهة الرسمية لقيم Reanimated المشتركة (false positive للقاعدة)
    // eslint-disable-next-line react-hooks/immutability
    translateY.value = withTiming(-100, { duration: 300 });
    // eslint-disable-next-line react-hooks/immutability
    opacity.value = withTiming(0, { duration: 300 });
    // eslint-disable-next-line react-hooks/immutability
    scale.value = withTiming(0.8, { duration: 300 }, (finished) => {
      if (finished) {
        runOnJS(onAnimationFinished)();
      }
    });
  }, [onAnimationFinished, opacity, scale, translateY]);

  useEffect(() => {
    if (visible) {
      setMounted(true);
      // Show animation
      // eslint-disable-next-line react-hooks/immutability
      translateY.value = withSpring(0, {
        damping: 15,
        stiffness: 150,
      });
      // eslint-disable-next-line react-hooks/immutability
      opacity.value = withTiming(1, { duration: 300 });
      // eslint-disable-next-line react-hooks/immutability
      scale.value = withSpring(1, {
        damping: 12,
        stiffness: 200,
      });

      // Auto hide after duration
      const timer = setTimeout(() => {
        hideToast();
      }, duration);

      return () => clearTimeout(timer);
    } else if (mounted) {
      hideToast();
    }
  }, [visible, duration, hideToast, mounted, opacity, scale, translateY]);

  const animatedStyle = useAnimatedStyle(() => {
    return {
      transform: [
        { translateY: translateY.value },
        { scale: scale.value },
      ],
      opacity: opacity.value,
    };
  });

  if (!mounted) {
    return null;
  }

  return (
    <Animated.View
      style={[
        styles.container,
        animatedStyle,
        {
          backgroundColor: config.backgroundColor,
        },
      ]}>
      <View style={styles.content}>
        <IconSymbol name={config.icon} size={20} color={config.iconColor} />
        <ThemedText style={[styles.message, { color: '#FFFFFF' }]}>
          {message}
        </ThemedText>
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    top: 50,
    left: 20,
    right: 20,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 4,
    },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 8,
    zIndex: 9999,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  message: {
    flex: 1,
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
  },
});
