import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import React from 'react';
import { StyleSheet, View } from 'react-native';
import { STORE_ACCENT } from './constants';

type Props = {
  mutedColor: string;
  title?: string;
  message?: string;
};

export function StoreEmptyPrompt({
  mutedColor,
  title = 'استكشف المتجر',
  message = 'اختر تصنيفاً، أو صاحب منتج، أو ابحث عن منتج لعرض النتائج.',
}: Props) {
  return (
    <View style={styles.wrap}>
      <View style={[styles.iconCircle, { backgroundColor: STORE_ACCENT + '18' }]}>
        <IconSymbol name="cart.fill" size={36} color={STORE_ACCENT} />
      </View>
      <ThemedText type="subtitle" style={styles.title}>
        {title}
      </ThemedText>
      <ThemedText
        style={[styles.message, { color: mutedColor }]}
        lightColor={mutedColor}
        darkColor={mutedColor}>
        {message}
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    paddingVertical: 32,
    paddingHorizontal: 24,
    gap: 10,
  },
  iconCircle: {
    width: 72,
    height: 72,
    borderRadius: 36,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    textAlign: 'center',
  },
  message: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    lineHeight: 22,
    textAlign: 'center',
  },
});
