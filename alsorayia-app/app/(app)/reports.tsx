import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { useThemeColor } from '@/hooks/use-theme-color';
import {
  fetchReportsByPersonsListUrl,
  fetchReportsLocationPersons,
  fetchReportsNamePersons,
  fetchReportsPage,
  fetchReportsPersonStatistics,
  type ReportsCommonNameRow,
  type ReportsLocationPersonsResponse,
  type ReportsLocationStatRow,
  type ReportsPageData,
  type ReportsPersonRow,
} from '@/lib/api/reports';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  LayoutAnimation,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  UIManager,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { styles } from '@/components/reports/styles';
import { TotalFamilyStatsCard } from '@/components/reports/sections/family-totals-card';
import { EducationCardsRow } from '@/components/reports/sections/education-cards-row';
import { LocationsPopulationCard } from '@/components/reports/sections/locations-population-card';
import { MalesByAgeCard, FemalesByAgeCard } from '@/components/reports/sections/age-ranking-cards';
import { CommonNamesCard } from '@/components/reports/sections/common-names-card';
import { GrandfathersTableCard } from '@/components/reports/sections/grandfathers-table-card';
import { PersonListModal } from '@/components/reports/sections/persons-list-modal';
import { PersonStatsModal } from '@/components/reports/sections/person-stats-modal';
import {
  LIST_INITIAL,
  MODAL_PAGE,
  NAMES_INITIAL,
  parseLocationIdFromUrl,
  type ListModalState,
  type StatsModalState,
} from '@/components/reports/helpers';

if (Platform.OS === 'android' && UIManager.setLayoutAnimationEnabledExperimental) {
  UIManager.setLayoutAnimationEnabledExperimental(true);
}

export default function ReportsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const mutedColor = useThemeColor({}, 'icon');

  const [data, setData] = useState<ReportsPageData | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState(false);

  // التحكم في تمدد الأجداد
  const [expandedGf, setExpandedGf] = useState<Set<number>>(() => new Set());
  // التحكم في فتح الأبناء أو الأنساب داخل كل جيل للأجداد: `${gfId}-${genKey}` -> 'members' | 'relatives' | null
  const [gfToggles, setGfToggles] = useState<Record<string, 'members' | 'relatives' | null>>({});

  // حدود التمرير لعرض المزيد (مثل Blade لتجنب طول الصفحة المفرط)
  const [locationsLimit, setLocationsLimit] = useState(LIST_INITIAL);
  const [malesLimit, setMalesLimit] = useState(LIST_INITIAL);
  const [femalesLimit, setFemalesLimit] = useState(LIST_INITIAL);
  const [namesLimit, setNamesLimit] = useState(NAMES_INITIAL);
  const [gfCardsLimit, setGfCardsLimit] = useState(LIST_INITIAL);
  const [modalListLimit, setModalListLimit] = useState(MODAL_PAGE);

  // مودال المدينة أو الاسم
  const [listModal, setListModal] = useState<ListModalState>({
    visible: false,
    title: '',
    mode: null,
    loading: false,
    error: false,
    persons: [],
    total: 0,
    males: 0,
    females: 0,
  });

  // مودال تفاصيل الشخص
  const [statsModal, setStatsModal] = useState<StatsModalState>({
    visible: false,
    loading: false,
    personName: '',
    totalDescendants: 0,
    maleDescendants: 0,
    femaleDescendants: 0,
    breakdown: null,
  });

  // تفعيل تبديل الأبناء / الأنساب داخل مودال الشخص
  const [modalGenToggles, setModalGenToggles] = useState<Record<string, 'members' | 'relatives' | null>>({});

  const load = useCallback(async () => {
    setLoadError(false);
    const res = await fetchReportsPage();
    if (!res) {
      setLoadError(true);
      setData(null);
    } else {
      setData(res);
      setLocationsLimit(LIST_INITIAL);
      setMalesLimit(LIST_INITIAL);
      setFemalesLimit(LIST_INITIAL);
      setNamesLimit(NAMES_INITIAL);
      setGfCardsLimit(LIST_INITIAL);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setLoading(true);
      await load();
      if (!cancelled) setLoading(false);
    })();
    return () => {
      cancelled = true;
    };
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const toggleGrandfather = useCallback((id: number) => {
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setExpandedGf((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const toggleGfSection = useCallback((gfId: number, genKey: string, section: 'members' | 'relatives') => {
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    const key = `${gfId}-${genKey}`;
    setGfToggles((prev) => {
      const current = prev[key];
      return {
        ...prev,
        [key]: current === section ? null : section,
      };
    });
  }, []);

  const toggleModalGenSection = useCallback((genKey: string, section: 'members' | 'relatives') => {
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setModalGenToggles((prev) => {
      const current = prev[genKey];
      return {
        ...prev,
        [genKey]: current === section ? null : section,
      };
    });
  }, []);

  const openLocationList = useCallback(async (row: ReportsLocationStatRow) => {
    const title = String(row.location_name ?? row.name ?? 'المدينة');
    setModalListLimit(MODAL_PAGE);
    setListModal({
      visible: true,
      title: `أشخاص ${title}`,
      mode: 'location',
      loading: true,
      error: false,
      persons: [],
      total: 0,
      males: 0,
      females: 0,
    });

    const lid =
      typeof row.id === 'number'
        ? row.id
        : typeof row.location_id === 'number'
        ? row.location_id
        : parseLocationIdFromUrl(row.persons_list_url ?? undefined);

    let res: ReportsLocationPersonsResponse | null = null;
    if (lid != null && !Number.isNaN(lid)) {
      res = await fetchReportsLocationPersons(lid);
    }
    if (!res && row.persons_list_url) {
      const raw = await fetchReportsByPersonsListUrl(row.persons_list_url);
      if (raw && Array.isArray((raw as ReportsLocationPersonsResponse).persons)) {
        res = raw as ReportsLocationPersonsResponse;
      }
    }

    const persons = res?.persons ?? [];
    const locName =
      typeof res?.location?.name === 'string'
        ? res.location.name
        : typeof res?.location === 'string'
        ? res.location
        : title;

    setListModal((m) => ({
      ...m,
      loading: false,
      error: !res,
      persons,
      title: `أشخاص ${locName}`,
      total: Number(res?.total ?? (row.total ?? row.count ?? persons.length)) || 0,
      males: Number(res?.males ?? (row.males ?? 0)) || 0,
      females: Number(res?.females ?? (row.females ?? 0)) || 0,
    }));
  }, []);

  const openNameList = useCallback(async (row: ReportsCommonNameRow) => {
    const title = typeof row.name === 'string' ? row.name.trim() : 'اسم';
    setModalListLimit(MODAL_PAGE);
    setListModal({
      visible: true,
      title: `أشخاص يحملون اسم "${title}"`,
      mode: 'name',
      loading: true,
      error: false,
      persons: [],
      total: 0,
      males: 0,
      females: 0,
    });

    let res = await fetchReportsNamePersons(title);
    if (!res && row.persons_list_url) {
      res = (await fetchReportsByPersonsListUrl(row.persons_list_url)) as typeof res;
    }

    const persons = res?.persons ?? [];
    const nameTitle =
      typeof res?.name === 'string'
        ? res.name
        : typeof (res?.name as unknown as { name?: string })?.name === 'string'
        ? (res?.name as unknown as { name: string }).name
        : title;

    setListModal((m) => ({
      ...m,
      loading: false,
      error: !res,
      persons,
      title: `أشخاص يحملون اسم "${nameTitle}"`,
      total: res?.total ?? (row.count ?? persons.length),
      males: res?.males ?? 0,
      females: res?.females ?? 0,
    }));
  }, []);

  const closeListModal = useCallback(() => {
    setListModal((m) => ({ ...m, visible: false }));
  }, []);

  const openPersonStats = useCallback(async (person: ReportsPersonRow | { id: number; full_name: string }) => {
    setModalGenToggles({});
    setStatsModal({
      visible: true,
      loading: true,
      personName: person.full_name,
      totalDescendants: 0,
      maleDescendants: 0,
      femaleDescendants: 0,
      breakdown: null,
    });

    const res = await fetchReportsPersonStatistics(person.id);
    const stats = res?.statistics;
    const breakdown = stats?.generations_breakdown ?? null;

    setStatsModal({
      visible: true,
      loading: false,
      personName: res?.person?.full_name ?? person.full_name,
      totalDescendants: Number(stats?.total_descendants ?? 0),
      maleDescendants: Number(stats?.male_descendants ?? 0),
      femaleDescendants: Number(stats?.female_descendants ?? 0),
      breakdown,
    });
  }, []);

  const closeStatsModal = useCallback(() => {
    setStatsModal((s) => ({ ...s, visible: false }));
  }, []);

  // حسابات إحصائيات الأماكن الكلية
  const locationsSummary = useMemo(() => {
    const list = data?.locationsStatistics ?? [];
    const total = list.reduce(
      (sum, item) => sum + (Number(item.total ?? item.count ?? item.persons_count ?? 0) || 0),
      0
    );
    const males = list.reduce((sum, item) => sum + (Number(item.males ?? 0) || 0), 0);
    const females = list.reduce((sum, item) => sum + (Number(item.females ?? 0) || 0), 0);
    return { list, total, males, females };
  }, [data]);

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={6} minSize={24} maxSize={90} />
      <HeaderBar
        title="تقارير وإحصائيات العائلة"
        subtitle="نظرة شاملة على إحصائيات وبيانات أفراد العائلة"
        showBack
        onBackPress={() => router.back()}
      />

      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color="#16a34a" />
          <ThemedText style={styles.loadingHint} lightColor={mutedColor} darkColor={mutedColor}>
            جاري تحميل التقارير والإحصائيات…
          </ThemedText>
        </View>
      ) : loadError ? (
        <View style={styles.centered}>
          <Ionicons name="alert-circle" size={44} color="#f59e0b" />
          <ThemedText type="subtitle" style={styles.errorTitle}>
            تعذّر تحميل البيانات
          </ThemedText>
          <ThemedText style={styles.errorHint} lightColor={mutedColor} darkColor={mutedColor}>
            تحقق من اتصال الإنترنت ثم أعد المحاولة.
          </ThemedText>
          <Pressable
            onPress={() => {
              setLoading(true);
              load().finally(() => setLoading(false));
            }}
            style={[styles.retryBtn, { backgroundColor: '#16a34a' }]}>
            <ThemedText style={styles.retryBtnText}>إعادة المحاولة</ThemedText>
          </Pressable>
        </View>
      ) : data ? (
        <ScrollView
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#16a34a" />
          }
          contentContainerStyle={[
            styles.scrollContent,
            { paddingBottom: insets.bottom + 32 },
          ]}>

          {/* ========================================================================= */}
          {/* القسم الأول: إحصائيات أبناء العائلة الأساسية */}
          {/* ========================================================================= */}
          <TotalFamilyStatsCard data={data} isDark={isDark} mutedColor={mutedColor} />

          {/* ========================================================================= */}
          {/* القسم الثاني: الإحصائيات التعليمية */}
          {/* ========================================================================= */}
          <EducationCardsRow data={data} isDark={isDark} />

          {/* ========================================================================= */}
          {/* القسم الثالث: إحصائيات الأماكن (عدد سكان المدن) */}
          {/* ========================================================================= */}
          <LocationsPopulationCard
            locationsSummary={locationsSummary}
            locationsLimit={locationsLimit}
            setLocationsLimit={setLocationsLimit}
            openLocationList={openLocationList}
            isDark={isDark}
            mutedColor={mutedColor}
          />

          {/* ========================================================================= */}
          {/* القسم الرابع: ترتيب أبناء العائلة حسب العمر */}
          {/* ========================================================================= */}

          {/* (أ) الذكور حسب العمر */}
          <MalesByAgeCard
            data={data}
            malesLimit={malesLimit}
            setMalesLimit={setMalesLimit}
            openPersonStats={openPersonStats}
            isDark={isDark}
            mutedColor={mutedColor}
          />

          {/* (ب) ترتيب النساء حسب العمر */}
          <FemalesByAgeCard
            data={data}
            femalesLimit={femalesLimit}
            setFemalesLimit={setFemalesLimit}
            openPersonStats={openPersonStats}
            isDark={isDark}
            mutedColor={mutedColor}
          />

          {/* ========================================================================= */}
          {/* القسم الخامس: أكثر الأسماء تكراراً */}
          {/* ========================================================================= */}
          <CommonNamesCard
            data={data}
            namesLimit={namesLimit}
            setNamesLimit={setNamesLimit}
            openNameList={openNameList}
            isDark={isDark}
            mutedColor={mutedColor}
          />

          {/* ========================================================================= */}
          {/* القسم السادس: عدد الأبناء والأحفاد حسب الجد (جدول مطابق لهيكلة Blade) */}
          {/* ========================================================================= */}
          <GrandfathersTableCard
            data={data}
            gfCardsLimit={gfCardsLimit}
            setGfCardsLimit={setGfCardsLimit}
            expandedGf={expandedGf}
            gfToggles={gfToggles}
            toggleGrandfather={toggleGrandfather}
            toggleGfSection={toggleGfSection}
            openPersonStats={openPersonStats}
            isDark={isDark}
            mutedColor={mutedColor}
          />
        </ScrollView>
      ) : null}

      {/* ========================================================================= */}
      {/* Modal 1: عرض أشخاص المنطقة أو الاسم (مطابق للـ Blade) */}
      {/* ========================================================================= */}
      <PersonListModal
        listModal={listModal}
        modalListLimit={modalListLimit}
        setModalListLimit={setModalListLimit}
        closeListModal={closeListModal}
        openPersonStats={openPersonStats}
        insets={insets}
        isDark={isDark}
        mutedColor={mutedColor}
      />

      {/* ========================================================================= */}
      {/* Modal 2: تفاصيل إحصائيات الشخص والأجيال (مطابق للـ Blade) */}
      {/* ========================================================================= */}
      <PersonStatsModal
        statsModal={statsModal}
        modalGenToggles={modalGenToggles}
        toggleModalGenSection={toggleModalGenSection}
        closeStatsModal={closeStatsModal}
        openPersonStats={openPersonStats}
        insets={insets}
        isDark={isDark}
        mutedColor={mutedColor}
      />
    </ThemedView>
  );
}


