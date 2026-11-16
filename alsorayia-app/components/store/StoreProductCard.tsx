import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { formatListPrice, type ProductListItem } from '@/lib/api/store';
import { Image } from 'expo-image';
import React from 'react';
import { Pressable, StyleSheet, View, type ViewStyle } from 'react-native';
import { IMAGE_PLACEHOLDER_BLURHASH, STORE_ACCENT } from './constants';

type Props = {
  product: ProductListItem;
  onPress: () => void;
  style?: ViewStyle;
  cardBg: string;
  borderColor: string;
  mutedColor: string;
};

export function StoreProductCard({
  product,
  onPress,
  style,
  cardBg,
  borderColor,
  mutedColor,
}: Props) {
  const uri = product.main_image_url?.trim();
  const subtitle = product.owner?.name ?? product.category?.name ?? '';

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
          <View style={[styles.placeholder, { backgroundColor: STORE_ACCENT + '14' }]}>
            <IconSymbol name="photo.fill" size={26} color={STORE_ACCENT} />
          </View>
        )}
      </View>
      <View style={styles.body}>
        <ThemedText type="defaultSemiBold" style={styles.name} numberOfLines={2}>
          {product.name}
        </ThemedText>
        {subtitle ? (
          <ThemedText
            style={[styles.subtitle, { color: mutedColor }]}
            numberOfLines={1}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            {subtitle}
          </ThemedText>
        ) : null}
        <ThemedText style={[styles.price, { color: STORE_ACCENT }]}>
          {formatListPrice(product.price)}
        </ThemedText>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
  },
  pressed: {
    opacity: 0.92,
  },
  imageWrap: {
    aspectRatio: 1,
    width: '100%',
    backgroundColor: '#E2E8F0',
  },
  placeholder: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: {
    padding: 10,
    gap: 4,
  },
  name: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  subtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  price: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    marginTop: 2,
  },
});
