import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { useThemeColor } from '@/hooks/use-theme-color';
import {
  fetchBreastfeedingPublic,
  resolveBreastfeedingProfileUrl,
  type BreastfeedingChildPerspectiveRow,
  type BreastfeedingChildRow,
  type BreastfeedingMotherRow,
  type BreastfeedingPublicResponse,
  type BreastfeedingStats,
  type BreastfeedingViewMode,
} from '@/lib/api/breastfeeding';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter, type Href } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const ACCENT = '#8B5CF6';
const ACCENT_SOFT = '#A78BFA';
const PINK = '#EC4899';
const SEARCH_DEBOUNCE_MS = 350;

function avatarUri(raw: string | null | undefined): string | null {
  const u = raw?.trim();
  if (!u) return null;
  return resolveBreastfeedingProfileUrl(u);
}

function formatPeriod(
  start: string | null,
  end: string | null,
  active: boolean
): string {
  if (start && end) return `من ${start} إلى ${end}`;
  if (start && !end) return active ? `منذ ${start}` : `من ${start}`;
  if (!start && end) return `حتى ${end}`;
  return '';
}

function StatChip({
  label,
  value,
  borderColor,
  cardBg,
  mutedColor,
}: {
  label: string;
  value: number;
  borderColor: string;
  cardBg: string;
  mutedColor: string;
}) {
  return (
    <View style={[styles.statChip, { backgroundColor: cardBg, borderColor }]}>
      <ThemedText type="title" style={[styles.statValue, { color: ACCENT }]}>
        {value}
      </ThemedText>
      <ThemedText
        type="default"
        style={styles.statLabel}
        lightColor={mutedColor}
        darkColor={mutedColor}>
        {label}
      </ThemedText>
    </View>
  );
}

function ViewModeToggle({
  mode,
  onChange,
  borderColor,
  cardBg,
  mutedColor,
}: {
  mode: BreastfeedingViewMode;
  onChange: (m: BreastfeedingViewMode) => void;
  borderColor: string;
  cardBg: string;
  mutedColor: string;
}) {
  return (
    <View style={[styles.toggleWrap, { backgroundColor: cardBg, borderColor }]}>
      {(['mothers', 'children'] as const).map((m) => {
        const active = mode === m;
        return (
          <Pressable
            key={m}
            onPress={() => onChange(m)}
            style={({ pressed }) => [
              styles.toggleSegment,
              active && styles.toggleSegmentActive,
              active && { backgroundColor: ACCENT + '22' },
              pressed && !active && { opacity: 0.85 },
            ]}>
            <ThemedText
              type="defaultSemiBold"
              style={styles.toggleText}
              lightColor={active ? ACCENT : mutedColor}
              darkColor={active ? ACCENT_SOFT : mutedColor}>
              {m === 'mothers' ? 'حسب الأم' : 'حسب الطفل'}
            </ThemedText>
          </Pressable>
        );
      })}
    </View>
  );
}

function PersonAvatar({
  uri,
  size,
  borderColor,
  mutedColor,
}: {
  uri: string | null;
  size: number;
  borderColor: string;
  mutedColor: string;
}) {
  const radius = size > 44 ? 16 : 12;
  return (
    <View style={{ width: size, height: size, borderRadius: radius, overflow: 'hidden' }}>
      {uri ? (
        <Image
          source={{ uri }}
          style={{ width: size, height: size, borderRadius: radius }}
          contentFit="cover"
          transition={200}
        />
      ) : (
        <View
          style={{
            width: size,
            height: size,
            borderRadius: radius,
            borderWidth: 1,
            borderColor,
            alignItems: 'center',
            justifyContent: 'center',
          }}>
          <IconSymbol name="person.fill" size={size * 0.45} color={mutedColor} />
        </View>
      )}
    </View>
  );
}

function relationHasExtras(opts: {
  start: string | null;
  end: string | null;
  active: boolean;
  durationMonths: number | null;
  notes: string | null;
  extraPeople?: boolean;
}): boolean {
  if (opts.extraPeople) return true;
  if (formatPeriod(opts.start, opts.end, opts.active)) return true;
  if (opts.durationMonths != null) return true;
  if (opts.notes?.trim()) return true;
  return false;
}

function DetailsToggle({
  open,
  onToggle,
}: {
  open: boolean;
  onToggle: () => void;
}) {
  return (
    <TouchableOpacity
      onPress={onToggle}
      style={styles.detailsToggle}
      activeOpacity={0.75}
      hitSlop={8}>
      <ThemedText type="defaultSemiBold" style={styles.detailsToggleText}>
        {open ? 'إخفاء' : 'التفاصيل'}
      </ThemedText>
      <IconSymbol
        name="chevron.left"
        size={14}
        color={ACCENT}
        style={{ transform: [{ rotate: open ? '-90deg' : '0deg' }] }}
      />
    </TouchableOpacity>
  );
}

function RelationDetails({
  start,
  end,
  active,
  durationMonths,
  notes,
  mutedColor,
  borderColor,
}: {
  start: string | null;
  end: string | null;
  active: boolean;
  durationMonths: number | null;
  notes: string | null;
  mutedColor: string;
  borderColor: string;
}) {
  const period = formatPeriod(start, end, active);
  const note = notes?.trim();
  if (!period && durationMonths == null && !note) return null;

  return (
    <View style={styles.detailBlock}>
      {period || durationMonths != null ? (
        <View style={styles.metaChips}>
          {period ? (
            <View style={[styles.metaChip, { borderColor }]}>
              <IconSymbol name="clock.fill" size={13} color={ACCENT} />
              <ThemedText
                type="default"
                style={styles.metaChipText}
                lightColor={mutedColor}
                darkColor={mutedColor}>
                {period}
              </ThemedText>
            </View>
          ) : null}
          {durationMonths != null ? (
            <View style={[styles.metaChip, { borderColor }]}>
              <ThemedText type="defaultSemiBold" style={[styles.metaChipText, { color: ACCENT }]}>
                {durationMonths} شهرًا
              </ThemedText>
            </View>
          ) : null}
        </View>
      ) : null}
      {note ? (
        <View style={[styles.notesBox, { borderColor, backgroundColor: ACCENT + '10' }]}>
          <ThemedText
            type="default"
            style={styles.notesText}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            {note}
          </ThemedText>
        </View>
      ) : null}
    </View>
  );
}

function RelatedPersonChip({
  label,
  name,
  onPress,
  mutedColor,
  borderColor,
}: {
  label: string;
  name: string;
  onPress: () => void;
  mutedColor: string;
  borderColor: string;
}) {
  return (
    <TouchableOpacity
      style={[styles.relatedChip, { borderColor }]}
      onPress={onPress}
      activeOpacity={0.75}>
      <IconSymbol name="person.fill" size={16} color={ACCENT} />
      <View style={{ flex: 1 }}>
        <ThemedText
          type="default"
          style={styles.relatedChipLabel}
          lightColor={mutedColor}
          darkColor={mutedColor}>
          {label}
        </ThemedText>
        <ThemedText type="defaultSemiBold" style={styles.relatedChipName} numberOfLines={1}>
          {name}
        </ThemedText>
      </View>
      <IconSymbol name="chevron.left" size={14} color={mutedColor} />
    </TouchableOpacity>
  );
}

function MotherCard({
  item,
  cardBg,
  borderColor,
  mutedColor,
  onOpenPerson,
}: {
  item: BreastfeedingMotherRow;
  cardBg: string;
  borderColor: string;
  mutedColor: string;
  onOpenPerson: (id: number) => void;
}) {
  const uri = avatarUri(item.avatar);
  const n = item.children?.length ?? 0;

  return (
    <View style={[styles.card, { backgroundColor: cardBg, borderColor }]}>
      <TouchableOpacity
        style={styles.cardHeaderRow}
        activeOpacity={0.75}
        onPress={() => onOpenPerson(item.id)}>
        <PersonAvatar uri={uri} size={52} borderColor={borderColor} mutedColor={mutedColor} />
        <View style={styles.cardHeaderText}>
          <ThemedText type="defaultSemiBold" style={styles.cardTitle} numberOfLines={2}>
            {item.name}
          </ThemedText>
          <ThemedText
            type="default"
            style={styles.cardSub}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            أم مرضعة · {n} {n === 1 ? 'طفل مرتضع' : 'أطفال مرتضعون'}
          </ThemedText>
        </View>
        <IconSymbol name="chevron.left" size={18} color={mutedColor} />
      </TouchableOpacity>

      {n > 0 ? (
        <View style={[styles.childrenBlock, { borderTopColor: borderColor }]}>
          {item.children.map((child, idx) => (
            <ChildRelationRow
              key={child.relationship_id}
              child={child}
              borderColor={borderColor}
              mutedColor={mutedColor}
              isLast={idx === item.children.length - 1}
              onOpenPerson={onOpenPerson}
            />
          ))}
        </View>
      ) : null}
    </View>
  );
}

function ChildRelationRow({
  child,
  borderColor,
  mutedColor,
  isLast,
  onOpenPerson,
}: {
  child: BreastfeedingChildRow;
  borderColor: string;
  mutedColor: string;
  isLast: boolean;
  onOpenPerson: (id: number) => void;
}) {
  const [open, setOpen] = useState(false);
  const uri = avatarUri(child.avatar);
  const father = child.breastfeeding_father;
  const hasExtras = relationHasExtras({
    start: child.start_date,
    end: child.end_date,
    active: child.is_active,
    durationMonths: child.duration_months,
    notes: child.notes,
    extraPeople: !!father,
  });

  return (
    <View
      style={[
        styles.childRow,
        !isLast && { borderBottomColor: borderColor, borderBottomWidth: StyleSheet.hairlineWidth },
      ]}>
      <View style={styles.childRowMain}>
        <TouchableOpacity
          style={styles.childPersonTap}
          activeOpacity={0.75}
          onPress={() => onOpenPerson(child.id)}>
          <PersonAvatar uri={uri} size={40} borderColor={borderColor} mutedColor={mutedColor} />
          <View style={{ flex: 1 }}>
            <ThemedText type="defaultSemiBold" style={styles.childName} numberOfLines={1}>
              {child.name}
            </ThemedText>
            <ThemedText
              type="default"
              style={styles.childMeta}
              lightColor={mutedColor}
              darkColor={mutedColor}>
              الطفل المرتضع
            </ThemedText>
          </View>
        </TouchableOpacity>
        {hasExtras ? (
          <DetailsToggle open={open} onToggle={() => setOpen((v) => !v)} />
        ) : (
          <TouchableOpacity onPress={() => onOpenPerson(child.id)} hitSlop={8}>
            <IconSymbol name="chevron.left" size={16} color={mutedColor} />
          </TouchableOpacity>
        )}
      </View>

      {open && hasExtras ? (
        <View style={styles.childDetailsPad}>
          <RelationDetails
            start={child.start_date}
            end={child.end_date}
            active={child.is_active}
            durationMonths={child.duration_months}
            notes={child.notes}
            mutedColor={mutedColor}
            borderColor={borderColor}
          />
          {father ? (
            <RelatedPersonChip
              label="أب الرضاعة"
              name={father.name}
              onPress={() => onOpenPerson(father.id)}
              mutedColor={mutedColor}
              borderColor={borderColor}
            />
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

function ChildPerspectiveCard({
  item,
  cardBg,
  borderColor,
  mutedColor,
  onOpenPerson,
}: {
  item: BreastfeedingChildPerspectiveRow;
  cardBg: string;
  borderColor: string;
  mutedColor: string;
  onOpenPerson: (id: number) => void;
}) {
  const [open, setOpen] = useState(false);
  const uri = avatarUri(item.avatar);
  const mother = item.nursing_mother;
  const father = item.breastfeeding_father;
  const hasExtras = relationHasExtras({
    start: item.start_date,
    end: item.end_date,
    active: item.is_active,
    durationMonths: item.duration_months,
    notes: item.notes,
    extraPeople: true,
  });

  return (
    <View style={[styles.card, { backgroundColor: cardBg, borderColor }]}>
      <View style={styles.cardHeaderRow}>
        <TouchableOpacity
          style={styles.childPersonTap}
          activeOpacity={0.75}
          onPress={() => onOpenPerson(item.id)}>
          <PersonAvatar uri={uri} size={52} borderColor={borderColor} mutedColor={mutedColor} />
          <View style={styles.cardHeaderText}>
            <ThemedText type="defaultSemiBold" style={styles.cardTitle} numberOfLines={2}>
              {item.name}
            </ThemedText>
            <ThemedText
              type="default"
              style={styles.cardSub}
              lightColor={mutedColor}
              darkColor={mutedColor}>
              الطفل المرتضع
            </ThemedText>
          </View>
        </TouchableOpacity>
        {hasExtras ? (
          <DetailsToggle open={open} onToggle={() => setOpen((v) => !v)} />
        ) : (
          <TouchableOpacity onPress={() => onOpenPerson(item.id)} hitSlop={8}>
            <IconSymbol name="chevron.left" size={18} color={mutedColor} />
          </TouchableOpacity>
        )}
      </View>

      {open && hasExtras ? (
        <View style={[styles.linksRow, { borderTopColor: borderColor }]}>
          <View style={styles.childDetailsPad}>
            <RelationDetails
              start={item.start_date}
              end={item.end_date}
              active={item.is_active}
              durationMonths={item.duration_months}
              notes={item.notes}
              mutedColor={mutedColor}
              borderColor={borderColor}
            />
            <RelatedPersonChip
              label="الأم المرضعة"
              name={mother.name}
              onPress={() => onOpenPerson(mother.id)}
              mutedColor={mutedColor}
              borderColor={borderColor}
            />
            {father ? (
              <RelatedPersonChip
                label="أب الرضاعة"
                name={father.name}
                onPress={() => onOpenPerson(father.id)}
                mutedColor={mutedColor}
                borderColor={borderColor}
              />
            ) : null}
          </View>
        </View>
      ) : null}
    </View>
  );
}

function StatsGrid({
  stats,
  borderColor,
  cardBg,
  mutedColor,
}: {
  stats: BreastfeedingStats;
  borderColor: string;
  cardBg: string;
  mutedColor: string;
}) {
  return (
    <View style={styles.statsGrid}>
      <StatChip
        label="أمهات مرضعات"
        value={stats.total_nursing_mothers}
        borderColor={borderColor}
        cardBg={cardBg}
        mutedColor={mutedColor}
      />
      <StatChip
        label="أطفال مرتضعون"
        value={stats.total_breastfed_children}
        borderColor={borderColor}
        cardBg={cardBg}
        mutedColor={mutedColor}
      />
      <StatChip
        label="علاقات مستمرة"
        value={stats.active_breastfeeding}
        borderColor={borderColor}
        cardBg={cardBg}
        mutedColor={mutedColor}
      />
    </View>
  );
}

export default function BreastfeedingScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const cardBg = useThemeColor({}, 'card');
  const borderColor = useThemeColor({}, 'border');
  const mutedColor = useThemeColor({}, 'icon');
  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');

  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const [localView, setLocalView] = useState<BreastfeedingViewMode>('mothers');
  const [payload, setPayload] = useState<BreastfeedingPublicResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput), SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [searchInput]);

  const load = useCallback(async (isRefresh: boolean) => {
    if (isRefresh) setRefreshing(true);
    else {
      setLoading(true);
      setLoadError(false);
    }
    const q = debouncedSearch.trim();
    const data = await fetchBreastfeedingPublic({
      search: q.length > 0 ? q : undefined,
    });
    if (!isRefresh) {
      setLoading(false);
    } else {
      setRefreshing(false);
    }
    if (!data) {
      setLoadError(true);
      return;
    }
    setLoadError(false);
    setPayload(data);
  }, [debouncedSearch]);

  useEffect(() => {
    load(false);
  }, [load]);

  const onRefresh = useCallback(() => {
    load(true);
  }, [load]);

  const openPerson = useCallback(
    (id: number) => {
      if (!id) return;
      router.push(`/(app)/person/${id}` as Href);
    },
    [router]
  );

  const listData = useMemo(() => {
    if (!payload) return [];
    return localView === 'mothers' ? payload.mothers : payload.children;
  }, [payload, localView]);

  const stats = payload?.stats;

  const header = useMemo(
    () => (
      <View style={styles.headerBlock}>
        <LinearGradient
          colors={
            isDark
              ? [`${ACCENT}24`, `${ACCENT}10`, backgroundColor]
              : ['#F5F3FF', '#EDE9FE', '#FFFFFF']
          }
          locations={[0, 0.4, 1]}
          style={styles.heroGradient}>
          <ThemedText type="default" style={styles.heroIntro}>
            تفاصيل الرضاعة تظهر هنا داخل التطبيق. اضغط على أي شخص لفتح ملفه في شجرة العائلة.
          </ThemedText>
        </LinearGradient>

        {stats ? (
          <StatsGrid
            stats={stats}
            borderColor={borderColor}
            cardBg={cardBg}
            mutedColor={mutedColor}
          />
        ) : null}

        <ViewModeToggle
          mode={localView}
          onChange={setLocalView}
          borderColor={borderColor}
          cardBg={cardBg}
          mutedColor={mutedColor}
        />

        <View style={[styles.searchWrap, { borderColor, backgroundColor: cardBg }]}>
          <IconSymbol name="magnifyingglass" size={20} color={mutedColor} />
          <TextInput
            value={searchInput}
            onChangeText={setSearchInput}
            placeholder="بحث في اسم الأم أو الطفل…"
            placeholderTextColor={mutedColor}
            style={[styles.searchInput, { color: textColor }]}
            returnKeyType="search"
          />
          {searchInput.length > 0 ? (
            <TouchableOpacity onPress={() => setSearchInput('')} hitSlop={10}>
              <IconSymbol name="xmark.circle.fill" size={22} color={mutedColor} />
            </TouchableOpacity>
          ) : null}
        </View>

        {loading && payload && !refreshing ? (
          <View style={styles.inlineLoading}>
            <ActivityIndicator size="small" color={ACCENT} />
            <ThemedText
              type="default"
              style={styles.inlineLoadingText}
              lightColor={mutedColor}
              darkColor={mutedColor}>
              جاري تحديث النتائج…
            </ThemedText>
          </View>
        ) : null}
      </View>
    ),
    [
      isDark,
      backgroundColor,
      stats,
      borderColor,
      cardBg,
      mutedColor,
      localView,
      searchInput,
      textColor,
      loading,
      payload,
      refreshing,
    ]
  );

  const renderItem = useCallback(
    ({ item }: { item: BreastfeedingMotherRow | BreastfeedingChildPerspectiveRow }) => {
      if (localView === 'mothers') {
        return (
          <MotherCard
            item={item as BreastfeedingMotherRow}
            cardBg={cardBg}
            borderColor={borderColor}
            mutedColor={mutedColor}
            onOpenPerson={openPerson}
          />
        );
      }
      return (
        <ChildPerspectiveCard
          item={item as BreastfeedingChildPerspectiveRow}
          cardBg={cardBg}
          borderColor={borderColor}
          mutedColor={mutedColor}
          onOpenPerson={openPerson}
        />
      );
    },
    [localView, cardBg, borderColor, mutedColor, openPerson]
  );

  const keyExtractor = useCallback(
    (item: BreastfeedingMotherRow | BreastfeedingChildPerspectiveRow) => String(item.id),
    []
  );

  if (loading && !payload) {
    return (
      <ThemedView style={styles.container}>
        <AnimatedBubbles bubbleCount={6} minSize={24} maxSize={100} />
        <HeaderBar title="علاقات الرضاعة" showBack onBackPress={() => router.back()} />
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={ACCENT} />
          <ThemedText
            type="default"
            style={styles.loadingHint}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            جاري التحميل…
          </ThemedText>
        </View>
      </ThemedView>
    );
  }

  if (loadError && !payload) {
    return (
      <ThemedView style={styles.container}>
        <AnimatedBubbles bubbleCount={6} minSize={24} maxSize={100} />
        <HeaderBar title="علاقات الرضاعة" showBack onBackPress={() => router.back()} />
        <View style={styles.centered}>
          <IconSymbol name="exclamationmark.triangle.fill" size={40} color={mutedColor} />
          <ThemedText type="defaultSemiBold" style={styles.errorTitle}>
            تعذّر تحميل البيانات
          </ThemedText>
          <ThemedText
            type="default"
            style={styles.errorHint}
            lightColor={mutedColor}
            darkColor={mutedColor}>
            تحقق من الاتصال أو من صلاحية الوصول للموقع
          </ThemedText>
          <TouchableOpacity
            style={[styles.retryBtn, { backgroundColor: ACCENT }]}
            onPress={() => load(false)}
            activeOpacity={0.88}>
            <ThemedText style={styles.retryBtnText}>إعادة المحاولة</ThemedText>
          </TouchableOpacity>
        </View>
      </ThemedView>
    );
  }

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={6} minSize={24} maxSize={100} />
      <HeaderBar title="علاقات الرضاعة" showBack onBackPress={() => router.back()} />

      <FlatList
        data={listData}
        keyExtractor={keyExtractor}
        renderItem={renderItem}
        ListHeaderComponent={header}
        contentContainerStyle={[
          styles.listContent,
          { paddingBottom: insets.bottom + 28 },
        ]}
        showsVerticalScrollIndicator={false}
        ItemSeparatorComponent={() => <View style={{ height: 14 }} />}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={ACCENT}
            colors={[ACCENT]}
          />
        }
        ListEmptyComponent={
          !loading ? (
            <View style={styles.emptyWrap}>
              <IconSymbol name="info.circle.fill" size={36} color={mutedColor} />
              <ThemedText
                type="defaultSemiBold"
                style={styles.emptyHint}
                lightColor={mutedColor}
                darkColor={mutedColor}>
                {debouncedSearch.trim().length > 0
                  ? 'لا توجد نتائج مطابقة للبحث'
                  : 'لا توجد علاقات رضاعة مسجّلة حاليًا'}
              </ThemedText>
            </View>
          ) : null
        }
      />
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  headerBlock: {
    paddingHorizontal: 16,
    paddingBottom: 8,
  },
  heroGradient: {
    borderRadius: 20,
    padding: 18,
    marginBottom: 16,
    ...Platform.select({
      ios: {
        shadowColor: ACCENT,
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.12,
        shadowRadius: 14,
      },
      android: { elevation: 3 },
    }),
  },
  heroIntro: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    lineHeight: 24,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginBottom: 16,
  },
  statChip: {
    width: '48%',
    flexGrow: 1,
    minWidth: '47%',
    borderRadius: 16,
    borderWidth: 1,
    paddingVertical: 14,
    paddingHorizontal: 12,
    alignItems: 'center',
  },
  statValue: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
    marginBottom: 4,
  },
  statLabel: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    textAlign: 'center',
  },
  toggleWrap: {
    flexDirection: 'row',
    borderRadius: 14,
    borderWidth: 1,
    padding: 4,
    marginBottom: 14,
    gap: 4,
  },
  toggleSegment: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
  },
  toggleSegmentActive: {},
  toggleText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 10,
    marginBottom: 8,
  },
  inlineLoading: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingVertical: 10,
    marginBottom: 4,
  },
  inlineLoadingText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  searchInput: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 15,
    paddingVertical: Platform.OS === 'ios' ? 8 : 4,
  },
  listContent: {
    paddingTop: 4,
  },
  card: {
    marginHorizontal: 16,
    borderRadius: 18,
    borderWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.06,
        shadowRadius: 10,
      },
      android: { elevation: 2 },
    }),
  },
  cardHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    gap: 10,
  },
  avatarWrap: {
    width: 52,
    height: 52,
    borderRadius: 16,
    overflow: 'hidden',
  },
  avatar: {
    width: 52,
    height: 52,
    borderRadius: 16,
  },
  avatarPlaceholder: {
    width: 52,
    height: 52,
    borderRadius: 16,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardHeaderText: { flex: 1 },
  cardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  cardSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    marginTop: 4,
  },
  childrenBlock: {
    borderTopWidth: 1,
  },
  childRow: {},
  childRowMain: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 14,
    gap: 8,
  },
  childPersonTap: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minWidth: 0,
  },
  detailsToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 6,
    paddingHorizontal: 8,
    borderRadius: 10,
    backgroundColor: ACCENT + '14',
  },
  detailsToggleText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    color: ACCENT,
  },
  avatarWrapSm: {
    width: 40,
    height: 40,
    borderRadius: 12,
    overflow: 'hidden',
  },
  avatarSm: { width: 40, height: 40, borderRadius: 12 },
  avatarPlaceholderSm: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  childNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    justifyContent: 'flex-start',
    flexWrap: 'wrap',
  },
  childName: {
    fontFamily: 'Tajawal-SemiBold',
    fontSize: 14,
    flexShrink: 1,
  },
  childMeta: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    marginTop: 4,
  },
  childDetailsPad: {
    paddingHorizontal: 14,
    paddingBottom: 12,
    gap: 8,
  },
  detailBlock: {
    gap: 8,
  },
  metaChips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  metaChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  metaChipText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  notesBox: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  notesText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 22,
  },
  relatedChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  relatedChipLabel: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
  },
  relatedChipName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    marginTop: 1,
  },
  activeBadge: {
    backgroundColor: PINK + '18',
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 3,
  },
  activeBadgeText: {
    color: PINK,
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  linksRow: {
    borderTopWidth: 1,
    paddingTop: 4,
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 10,
  },
  loadingHint: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    marginTop: 8,
  },
  errorTitle: {
    fontFamily: 'Tajawal-Bold',
    marginTop: 8,
  },
  errorHint: {
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    fontSize: 14,
  },
  retryBtn: {
    marginTop: 16,
    paddingHorizontal: 22,
    paddingVertical: 12,
    borderRadius: 12,
  },
  retryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  emptyWrap: {
    paddingVertical: 40,
    alignItems: 'center',
    gap: 10,
  },
  emptyHint: {
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
  },
});
