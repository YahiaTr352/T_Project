import React from 'react';
import { StyleSheet, View, Pressable } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { toEnglishDigits } from '@/lib/utils/numberFormat';

interface StarRatingInputProps {
  value: number;
  maxRating?: number;
  onChange: (value: number) => void;
  colors: any;
}

export default function StarRatingInput({
  value,
  maxRating = 5,
  onChange,
  colors,
}: StarRatingInputProps) {
  const stars = Array.from({ length: maxRating }, (_, i) => i + 1);

  return (
    <View style={styles.container}>
      <View style={styles.starsRow}>
        {stars.map((star) => {
          const isFilled = star <= value;

          return (
            <Pressable
              key={star}
              onPress={() => onChange(star)}
              hitSlop={8}
              style={({ pressed }) => [
                styles.starBtn,
                pressed && { transform: [{ scale: 1.25 }] },
              ]}
            >
              <IconSymbol
                name="star.fill"
                size={32}
                color={isFilled ? '#fbbf24' : '#e5e7eb'} // Amber yellow for filled, grey for empty
              />
            </Pressable>
          );
        })}
      </View>

      {value > 0 && (
        <ThemedText style={[styles.ratingText, { color: colors.icon }]}>
          التقييم الحالي: {toEnglishDigits(value)} / {toEnglishDigits(maxRating)}
        </ThemedText>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    paddingVertical: 10,
    width: '100%',
  },
  starsRow: {
    flexDirection: 'row', // Left-to-right order for star ratings is standard, but simple to tap
    gap: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  starBtn: {
    padding: 2,
  },
  ratingText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    marginTop: 10,
  },
});
