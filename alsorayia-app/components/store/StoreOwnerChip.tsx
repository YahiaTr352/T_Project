import { ThemedText } from '@/components/themed-text';
import type { StoreOwner } from '@/lib/api/store';
import React from 'react';
import { Pressable, StyleSheet } from 'react-native';
import { STORE_ACCENT } from './constants';

type Props = {
  owner: StoreOwner;
  selected: boolean;
  onPress: () => void;
  cardBg: string;
  borderColor: string;
};

export function StoreOwnerChip({
  owner,
  selected,
  onPress,
  cardBg,
  borderColor,
}: Props) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.chip,
        {
          backgroundColor: selected ? STORE_ACCENT + '22' : cardBg,
          borderColor: selected ? STORE_ACCENT : borderColor,
        },
        pressed && { opacity: 0.88 },
      ]}>
      <ThemedText
        type="defaultSemiBold"
        style={[styles.name, selected && { color: STORE_ACCENT }]}
        numberOfLines={1}>
        {owner.name}
      </ThemedText>
      <ThemedText style={[styles.count, selected && { color: STORE_ACCENT }]}>
        {owner.products_count}
      </ThemedText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 999,
    borderWidth: 1.5,
    maxWidth: 200,
  },
  name: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    flexShrink: 1,
  },
  count: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    opacity: 0.75,
  },
});
