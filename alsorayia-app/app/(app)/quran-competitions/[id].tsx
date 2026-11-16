import { AnimatedBubbles } from '@/components/animated-bubbles';
import { HtmlContent, hasHtmlContent } from '@/components/html-content';
import HeaderBar from '@/components/headerBar';
import { QURAN_ACCENT } from '@/components/quran/constants';
import { StoreMediaGallery } from '@/components/store/StoreMediaGallery';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import {
  fetchQuranCompetition,
  quranMediaToStoreShape,
  type QuranCompetitionDetail,
  type QuranManager,
  type QuranSection,
  type QuranWinner,
} from '@/lib/api/quranCompetitions';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  View,
  useColorScheme,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

function formatPeriod(start: string | null, end: string | null, hijri: string): string {
  const parts: string[] = [];
  if (hijri) parts.push(`${hijri} هـ`);
  if (start && end) parts.push(`${start} — ${end}`);
  else if (start) parts.push(`من ${start}`);
  else if (end) parts.push(`حتى ${end}`);
  return parts.join(' · ');
}

function WinnersBlock({
  winners,
  cardBg,
  borderColor,
  mutedColor,
}: {
  winners: QuranWinner[];
  cardBg: string;
  borderColor: string;
  mutedColor: string;
}) {
  if (winners.length === 0) return null;
  const sorted = [...winners].sort((a, b) => a.position - b.position);
  return (
    <View style={[styles.block, { backgroundColor: cardBg, borderColor }]}>
      <ThemedText type="defaultSemiBold" style={styles.blockTitle}>
        الفائزون
      </ThemedText>
      {sorted.map((w) => (
        <View key={w.id} style={[styles.winnerRow, { borderTopColor: borderColor }]}>
          <View style={[styles.positionBadge, { backgroundColor: QURAN_ACCENT + '18' }]}>
            <ThemedText style={{ color: QURAN_ACCENT, fontFamily: 'Tajawal-Bold', fontSize: 12 }}>
              {w.position_name || `#${w.position}`}
            </ThemedText>
          </View>
          <View style={styles.winnerBody}>
            <ThemedText type="defaultSemiBold" style={styles.winnerName}>
              {w.person?.name ?? '—'}
            </ThemedText>
            {w.category ? (
              <ThemedText
                style={[styles.winnerMeta, { color: mutedColor }]}
                lightColor={mutedColor}
                darkColor={mutedColor}>
                {w.category}
              </ThemedText>
            ) : null}
            {w.notes ? (
              <ThemedText
                style={[styles.winnerMeta, { color: mutedColor }]}
                lightColor={mutedColor}
                darkColor={mutedColor}>
                {w.notes}
              </ThemedText>
            ) : null}
          </View>
        </View>
      ))}
    </View>
  );
}

function SectionsBlock({
  sections,
  cardBg,
  borderColor,
  mutedColor,
}: {
  sections: QuranSection[];
  cardBg: string;
  borderColor: string;
  mutedColor: string;
}) {
  if (sections.length === 0) return null;
  const sorted = [...sections].sort((a, b) => a.sort_order - b.sort_order);
  return (
    <View style={[styles.block, { backgroundColor: cardBg, borderColor }]}>
      <ThemedText type="defaultSemiBold" style={styles.blockTitle}>
        الأقسام
      </ThemedText>
      {sorted.map((section) => (
        <View key={section.id} style={[styles.sectionBlock, { borderTopColor: borderColor }]}>
          <ThemedText type="defaultSemiBold" style={styles.sectionName}>
            {section.name}
          </ThemedText>
          {(section.people ?? [])
            .slice()
            .sort((a, b) => a.sort_order - b.sort_order)
            .map((p) => (
              <ThemedText key={p.id} type="default" style={[styles.personLine, { color: mutedColor }]}>
                • {p.name}
              </ThemedText>
            ))}
        </View>
      ))}
    </View>
  );
}

function ManagersBlock({
  managers,
  cardBg,
  borderColor,
}: {
  managers: QuranManager[];
  cardBg: string;
  borderColor: string;
}) {
  if (managers.length === 0) return null;
  const sorted = [...managers].sort((a, b) => a.sort_order - b.sort_order);
  return (
    <View style={[styles.block, { backgroundColor: cardBg, borderColor }]}>
      <ThemedText type="defaultSemiBold" style={styles.blockTitle}>
        المشرفون
      </ThemedText>
      {sorted.map((m) => (
        <View key={m.id} style={styles.managerRow}>
          <IconSymbol name="person.fill" size={16} color={QURAN_ACCENT} />
          <ThemedText type="default" style={styles.managerName}>
            {m.person?.name ?? '—'}
          </ThemedText>
        </View>
      ))}
    </View>
  );
}

export default function QuranCompetitionScreen() {
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const competitionId = useMemo(() => {
    const n = Number(idParam);
    return Number.isFinite(n) ? n : NaN;
  }, [idParam]);

  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { width: contentWidth } = useWindowDimensions();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const cardBg = colorScheme === 'dark' ? colors.card : '#FFFFFF';
  const mutedColor = colorScheme === 'dark' ? '#9CA3AF' : '#64748B';

  const [competition, setCompetition] = useState<QuranCompetitionDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notFound, setNotFound] = useState(false);

  const load = useCallback(async () => {
    if (!Number.isFinite(competitionId) || competitionId <= 0) {
      setNotFound(true);
      setLoading(false);
      return;
    }
    setError(null);
    setNotFound(false);

    const res = await fetchQuranCompetition(competitionId);
    setLoading(false);

    if (!res.ok) {
      setError(res.message);
      setNotFound(res.status === 404);
      setCompetition(null);
      return;
    }

    setCompetition(res.competition);
  }, [competitionId]);

  useEffect(() => {
    setLoading(true);
    void load();
  }, [load]);

  if (loading) {
    return (
      <ThemedView style={styles.centered}>
        <ActivityIndicator size="large" color={QURAN_ACCENT} />
      </ThemedView>
    );
  }

  if (notFound || !competition) {
    return (
      <ThemedView style={styles.flex1}>
        <HeaderBar
          title="المسابقة"
          showBack
          onBackPress={() => router.back()}
          style={{ paddingTop: insets.top }}
        />
        <View style={styles.centered}>
          <ThemedText style={{ color: mutedColor, textAlign: 'center' }}>
            {error ?? 'المسابقة غير موجودة.'}
          </ThemedText>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={{ color: QURAN_ACCENT, fontFamily: 'Tajawal-Bold' }}>
              رجوع
            </ThemedText>
          </Pressable>
        </View>
      </ThemedView>
    );
  }

  const period = formatPeriod(
    competition.start_date,
    competition.end_date,
    competition.hijri_year
  );
  const storeMedia = quranMediaToStoreShape(competition.media);
  const hasGallery =
    Boolean(competition.cover_image_url?.trim()) || storeMedia.length > 0;

  return (
    <ThemedView style={styles.flex1}>
      <AnimatedBubbles bubbleCount={3} minSize={18} maxSize={60} />
      <HeaderBar
        title={competition.title}
        showBack
        onBackPress={() => router.back()}
        style={{ paddingTop: insets.top }}
      />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={[
          styles.scroll,
          { paddingBottom: insets.bottom + 24 },
        ]}>
        {hasGallery ? (
          <StoreMediaGallery
            media={storeMedia}
            mainImageUrl={competition.cover_image_url}
          />
        ) : null}

        <View style={styles.titleBlock}>
          <ThemedText type="title" style={styles.competitionTitle}>
            {competition.title}
          </ThemedText>
          {competition.category?.name ? (
            <ThemedText
              style={[styles.metaLine, { color: mutedColor }]}
              lightColor={mutedColor}
              darkColor={mutedColor}>
              {competition.category.name}
            </ThemedText>
          ) : null}
          {period ? (
            <ThemedText
              style={[styles.metaLine, { color: mutedColor }]}
              lightColor={mutedColor}
              darkColor={mutedColor}>
              {period}
            </ThemedText>
          ) : null}
        </View>

        {(hasHtmlContent(competition.description) || competition.description) && (
          <View style={[styles.block, { backgroundColor: cardBg, borderColor: colors.border }]}>
            <ThemedText type="defaultSemiBold" style={styles.blockTitle}>
              الوصف
            </ThemedText>
            <HtmlContent
              html={competition.description}
              contentWidth={contentWidth - 40}
            />
          </View>
        )}

        <WinnersBlock
          winners={competition.winners}
          cardBg={cardBg}
          borderColor={colors.border}
          mutedColor={mutedColor}
        />

        <SectionsBlock
          sections={competition.sections}
          cardBg={cardBg}
          borderColor={colors.border}
          mutedColor={mutedColor}
        />

        <ManagersBlock
          managers={competition.managers}
          cardBg={cardBg}
          borderColor={colors.border}
        />

        {error ? (
          <Pressable onPress={() => void load()} style={styles.retryWrap}>
            <ThemedText style={{ color: QURAN_ACCENT, fontFamily: 'Tajawal-Bold' }}>
              إعادة التحميل
            </ThemedText>
          </Pressable>
        ) : null}
      </ScrollView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  flex1: { flex: 1 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 12,
  },
  scroll: {
    paddingHorizontal: 20,
    paddingTop: 8,
    gap: 16,
  },
  titleBlock: { gap: 6 },
  competitionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
  },
  metaLine: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
  block: {
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
  },
  blockTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  winnerRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  positionBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    maxWidth: 120,
  },
  winnerBody: { flex: 1, gap: 2 },
  winnerName: { fontFamily: 'Tajawal-Bold', fontSize: 15 },
  winnerMeta: { fontFamily: 'Tajawal-Regular', fontSize: 13 },
  sectionBlock: {
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    gap: 4,
  },
  sectionName: { fontFamily: 'Tajawal-Bold', fontSize: 14, marginBottom: 4 },
  personLine: { fontFamily: 'Tajawal-Regular', fontSize: 14, lineHeight: 22 },
  managerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  managerName: { fontFamily: 'Tajawal-Medium', fontSize: 14 },
  retryWrap: { alignItems: 'center', paddingVertical: 8 },
});
