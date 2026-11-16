import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { IMAGE_PLACEHOLDER_BLURHASH, QURAN_ACCENT } from '@/components/quran/constants';
import type { QuranCompetitionListItem } from '@/lib/api/quranCompetitions';
import { Image } from 'expo-image';
import React from 'react';
import { Pressable, StyleSheet, View, type ViewStyle } from 'react-native';

type Props = {
  competition: QuranCompetitionListItem;
  onPress: () => void;
  style?: ViewStyle;
  cardBg: string;
  borderColor: string;
  mutedColor: string;
};

function formatDates(start: string | null, end: string | null): string {
  if (start && end) return `${start} — ${end}`;
  if (start) return `من ${start}`;
  if (end) return `حتى ${end}`;
  return '';
}

export function QuranCompetitionListCard({
  competition,
  onPress,
  style,
  cardBg,
  borderColor,
  mutedColor,
}: Props) {
  const uri = competition.cover_image_url?.trim();
  const dates = formatDates(competition.start_date, competition.end_date);
  const subtitle = [
    competition.hijri_year ? `${competition.hijri_year} هـ` : '',
    competition.category?.name ?? '',
  ]
    .filter(Boolean)
    .join(' · ');

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
          <View style={[styles.placeholder, { backgroundColor: QURAN_ACCENT + '14' }]}>
            <IconSymbol name="book.fill" size={26} color={QURAN_ACCENT} />
          </View>
        )}
      </View>
      <View style={styles.body}>
        <ThemedText type="defaultSemiBold" style={styles.title} numberOfLines={2}>
          {competition.title}
        </ThemedText>
        {subtitle ? (
          <ThemedText
            style={[styles.meta, { color: mutedColor }]}
            numberOfLines={1}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            {subtitle}
          </ThemedText>
        ) : null}
        {dates ? (
          <ThemedText
            style={[styles.meta, { color: mutedColor }]}
            numberOfLines={1}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            {dates}
          </ThemedText>
        ) : null}
        {competition.winners_count > 0 ? (
          <ThemedText style={[styles.winners, { color: QURAN_ACCENT }]}>
            {competition.winners_count} فائز
          </ThemedText>
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
  },
  pressed: { opacity: 0.92 },
  imageWrap: {
    width: 100,
    minHeight: 100,
    backgroundColor: '#E2E8F0',
  },
  placeholder: {
    flex: 1,
    minHeight: 100,
    justifyContent: 'center',
    alignItems: 'center',
  },
  body: {
    flex: 1,
    padding: 12,
    gap: 4,
    justifyContent: 'center',
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  meta: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
  },
  winners: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    marginTop: 2,
  },
});
