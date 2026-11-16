import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { QURAN_ACCENT } from '@/components/quran/constants';
import { QuranCompetitionListCard } from '@/components/quran/QuranCompetitionListCard';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Colors } from '@/constants/theme';
import {
  fetchQuranCategory,
  type QuranCompetitionListItem,
} from '@/lib/api/quranCompetitions';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

function CompetitionSection({
  title,
  items,
  cardBg,
  borderColor,
  mutedColor,
  onPressItem,
}: {
  title: string;
  items: QuranCompetitionListItem[];
  cardBg: string;
  borderColor: string;
  mutedColor: string;
  onPressItem: (id: number) => void;
}) {
  if (items.length === 0) return null;
  return (
    <View style={styles.section}>
      <ThemedText type="defaultSemiBold" style={styles.sectionTitle}>
        {title}
      </ThemedText>
      <View style={styles.listGap}>
        {items.map((c) => (
          <QuranCompetitionListCard
            key={c.id}
            competition={c}
            onPress={() => onPressItem(c.id)}
            cardBg={cardBg}
            borderColor={borderColor}
            mutedColor={mutedColor}
          />
        ))}
      </View>
    </View>
  );
}

export default function QuranCategoryScreen() {
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const categoryId = useMemo(() => {
    const n = Number(idParam);
    return Number.isFinite(n) ? n : NaN;
  }, [idParam]);

  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const cardBg = colorScheme === 'dark' ? colors.card : '#FFFFFF';
  const mutedColor = colorScheme === 'dark' ? '#9CA3AF' : '#64748B';

  const [categoryName, setCategoryName] = useState('');
  const [categoryDesc, setCategoryDesc] = useState<string | null>(null);
  const [current, setCurrent] = useState<QuranCompetitionListItem[]>([]);
  const [previous, setPrevious] = useState<QuranCompetitionListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notFound, setNotFound] = useState(false);

  const goCompetition = useCallback(
    (competitionId: number) => {
      router.push(`/(app)/quran-competitions/${competitionId}` as Href);
    },
    [router]
  );

  const load = useCallback(async (): Promise<boolean> => {
    if (!Number.isFinite(categoryId) || categoryId <= 0) {
      setNotFound(true);
      return true;
    }
    setError(null);
    setNotFound(false);

    const res = await fetchQuranCategory(categoryId);

    if (!res.ok) {
      setError(res.message);
      setNotFound(res.status === 404);
      setCategoryName('');
      setCurrent([]);
      setPrevious([]);
      return true;
    }

    if (res.redirect_to_competition_id) {
      router.replace(`/(app)/quran-competitions/${res.redirect_to_competition_id}` as Href);
      return false;
    }

    setCategoryName(res.category.name);
    setCategoryDesc(res.category.description ?? null);
    setCurrent(res.current_competitions);
    setPrevious(res.previous_competitions);
    return true;
  }, [categoryId, router]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setLoading(true);
      const done = await load();
      if (!cancelled && done) setLoading(false);
    })();
    return () => {
      cancelled = true;
    };
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    const done = await load();
    if (done) setRefreshing(false);
  }, [load]);

  const headerTitle = categoryName || 'مسابقات القرآن';

  if (loading) {
    return (
      <ThemedView style={styles.centered}>
        <ActivityIndicator size="large" color={QURAN_ACCENT} />
      </ThemedView>
    );
  }

  if (notFound) {
    return (
      <ThemedView style={styles.flex1}>
        <HeaderBar
          title="التصنيف"
          showBack
          onBackPress={() => router.back()}
          style={{ paddingTop: insets.top }}
        />
        <View style={styles.centered}>
          <ThemedText style={{ color: mutedColor, textAlign: 'center' }}>
            {error ?? 'التصنيف غير موجود.'}
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

  const empty = current.length === 0 && previous.length === 0;

  return (
    <ThemedView style={styles.flex1}>
      <AnimatedBubbles bubbleCount={4} minSize={18} maxSize={70} />
      <HeaderBar
        title={headerTitle}
        showBack
        onBackPress={() => router.back()}
        style={{ paddingTop: insets.top }}
      />

      <ScrollView
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={QURAN_ACCENT} />
        }
        contentContainerStyle={[
          styles.scroll,
          { paddingBottom: insets.bottom + 24 },
        ]}>
        {categoryDesc ? (
          <ThemedText
            style={[styles.desc, { color: mutedColor }]}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            {categoryDesc}
          </ThemedText>
        ) : null}

        {empty ? (
          <View style={[styles.emptyCard, { backgroundColor: cardBg, borderColor: colors.border }]}>
            <ThemedText style={{ color: mutedColor, textAlign: 'center' }}>
              لا توجد مسابقات في هذا التصنيف حالياً.
            </ThemedText>
          </View>
        ) : (
          <>
            <CompetitionSection
              title="المسابقات الحالية"
              items={current}
              cardBg={cardBg}
              borderColor={colors.border}
              mutedColor={mutedColor}
              onPressItem={goCompetition}
            />
            <CompetitionSection
              title="مسابقات سابقة"
              items={previous}
              cardBg={cardBg}
              borderColor={colors.border}
              mutedColor={mutedColor}
              onPressItem={goCompetition}
            />
          </>
        )}

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
    paddingTop: 12,
    gap: 20,
  },
  desc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    lineHeight: 22,

  },
  section: { gap: 12 },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  listGap: { gap: 12 },
  emptyCard: {
    padding: 24,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
  },
  retryWrap: { alignItems: 'center', paddingVertical: 8 },
});
