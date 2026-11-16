import { IconSymbol } from '@/components/ui/icon-symbol';
import { Image } from 'expo-image';
import React from 'react';
import { StyleSheet, Text, View } from 'react-native';

interface PersonAvatarProps {
  uri?: string | null;
  size?: number;
  mutedColor: string;
  borderColor?: string;
  grayscale?: boolean;
  /** أول حرف من الاسم يظهر عند غياب الصورة بدل الأيقونة العامة */
  name?: string | null;
}

export function PersonAvatar({
  uri,
  size = 44,
  mutedColor,
  borderColor,
  grayscale = false,
  name,
}: PersonAvatarProps) {
  const radius = size / 2;
  const initial = (name || '').trim().charAt(0);

  return (
    <View style={{ width: size, height: size, borderRadius: radius, overflow: 'hidden' }}>
      {uri ? (
        <Image
          source={{ uri }}
          style={{
            width: size,
            height: size,
            borderRadius: radius,
            opacity: grayscale ? 0.42 : 1,
          }}
          contentFit="cover"
          transition={180}
        />
      ) : (
        <View
          style={[
            styles.fallback,
            {
              width: size,
              height: size,
              borderRadius: radius,
              borderColor: borderColor ?? mutedColor + '40',
              backgroundColor: mutedColor + '18',
              opacity: grayscale ? 0.5 : 1,
            },
          ]}>
          {initial ? (
            <ThemedInitial initial={initial} size={size} color={mutedColor} />
          ) : (
            <IconSymbol name="person.fill" size={size * 0.45} color={mutedColor} />
          )}
        </View>
      )}
      {grayscale && uri ? <View style={[StyleSheet.absoluteFill, styles.grayOverlay]} /> : null}
    </View>
  );
}

function ThemedInitial({ initial, size, color }: { initial: string; size: number; color: string }) {
  return (
    <Text
      style={{
        fontSize: size * 0.42,
        fontFamily: 'Tajawal-Bold',
        color,
        textAlign: 'center',
        lineHeight: size * 0.55,
      }}>
      {initial}
    </Text>
  );
}

const styles = StyleSheet.create({
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  grayOverlay: {
    backgroundColor: 'rgba(120, 120, 120, 0.38)',
  },
});
