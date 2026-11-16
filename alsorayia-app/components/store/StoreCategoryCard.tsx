import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import type { StoreCategory } from '@/lib/api/store';
import { Image } from 'expo-image';
import React from 'react';
import { Pressable, StyleSheet, View, type ViewStyle } from 'react-native';
import { IMAGE_PLACEHOLDER_BLURHASH, STORE_ACCENT } from './constants';

type Props = {
  category: StoreCategory;
  onPress: () => void;
  style?: ViewStyle;
  cardBg: string;
  borderColor: string;
};

export function StoreCategoryCard({
  category,
  onPress,
  style,
  cardBg,
  borderColor,
}: Props) {
  const uri = category.image_url?.trim();

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.card,
        { backgroundColor: cardBg, borderColor },
        pressed && styles.pressed,
        style,
      ]}>
      <View style={styles.imageWrap}>
        {uri ? (
          <Image
            source={{ uri }}
            style={StyleSheet.absoluteFill}
            contentFit="cover"
            transition={200}
            cachePolicy="memory-disk"
            placeholder={{ blurhash: IMAGE_PLACEHOLDER_BLURHASH }}
          />
        ) : (
          <View style={[styles.placeholder, { backgroundColor: STORE_ACCENT + '18' }]}>
            <IconSymbol name="cart.fill" size={28} color={STORE_ACCENT} />
          </View>
        )}
        <View style={styles.countBadge}>
          <ThemedText style={styles.countText}>{category.products_count}</ThemedText>
        </View>
      </View>
      <ThemedText type="defaultSemiBold" style={styles.name} numberOfLines={2}>
        {category.name}
      </ThemedText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
  },
  pressed: {
    opacity: 0.92,
  },
  imageWrap: {
    aspectRatio: 1.15,
    width: '100%',
    backgroundColor: '#E2E8F0',
  },
  placeholder: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countBadge: {
    position: 'absolute',
    bottom: 8,
    left: 8,
    backgroundColor: 'rgba(0,0,0,0.55)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 10,
  },
  countText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    color: '#FFFFFF',
  },
  name: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    padding: 10,
  },
});
