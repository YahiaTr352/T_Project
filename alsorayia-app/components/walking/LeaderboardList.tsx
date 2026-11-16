import { ThemedText } from '@/components/themed-text';
import { Colors, silaTheme } from '@/constants/theme';
import type { LeaderboardData, LeaderboardPeriod, WalkingPrivacyMode, WalkingClub } from '@/lib/api/walkingApi';
import type { LeaderboardCategory } from '@/hooks/use-walking';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React, { useMemo, useState, useRef, useEffect } from 'react';
import Animated, { LinearTransition } from 'react-native-reanimated';
import { AnimatedCounter } from './AnimatedCounter';
import {
  ActivityIndicator,
  Platform,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { AnonymousAvatar } from './AnonymousAvatar';
import { LeaderboardPodium } from './LeaderboardPodium';
import { LeaderboardPrivacyModal } from './LeaderboardPrivacyModal';
// === أبطال الاستمرارية (Streak Champions) - المكون محفوظ لإعادة التفعيل مستقبلاً ===
import { StreakChampionsBoard } from './StreakChampionsBoard';
import { Ionicons } from '@expo/vector-icons';

import { formatSteps, formatStreakDays, getStepTierConfig, formatLeaderboardPeriodRange } from '@/lib/walking/formatters';

export const LEADERBOARD_CLUBS: {
  key: LeaderboardCategory;
  label: string;
  shortLabel: string;
  subLabel: string;
  icon: string;
  minSteps: number;
  maxSteps?: number;
  badgeColor: string;
  badgeDark: string;
  accentLight: string;
  accentDark: string;
}[] = [
  {
    key: 'steps',
    label: 'سباق الخطوات',
    shortLabel: 'سباق الخطوات',
    subLabel: 'الترتيب العام للجميع',
    icon: '👟',
    minSteps: 0,
    badgeColor: '#10B981',
    badgeDark: '#059669',
    accentLight: '#D1FAE5',
    accentDark: '#064E3B',
  },
  {
    key: 'club_5k',
    label: 'نادي الـ 5,000 خطوة يومياً',
    shortLabel: 'نادي الـ 5,000',
    subLabel: 'يومياً',
    icon: '🥉',
    minSteps: 5000,
    maxSteps: 9999,
    badgeColor: '#D97706',
    badgeDark: '#F59E0B',
    accentLight: '#FEF3C7',
    accentDark: '#451A03',
  },
  {
    key: 'club_10k',
    label: 'نادي الـ 10,000 خطوة يومياً',
    shortLabel: 'نادي الـ 10,000',
    subLabel: 'يومياً',
    icon: '🥈',
    minSteps: 10000,
    maxSteps: 19999,
    badgeColor: '#0284C7',
    badgeDark: '#38BDF8',
    accentLight: '#E0F2FE',
    accentDark: '#082F49',
  },
  {
    key: 'club_20k',
    label: 'نادي الـ 20,000 خطوة يومياً',
    shortLabel: 'نادي الـ 20,000',
    subLabel: 'يومياً',
    icon: '🥇',
    minSteps: 20000,
    badgeColor: '#7C3AED',
    badgeDark: '#A78BFA',
    accentLight: '#EDE9FE',
    accentDark: '#2E1065',
  },
];

type Props = {
  clubs?: WalkingClub[];
  userClubStreaks?: Record<string, number>;
  data: LeaderboardData | null;
  selectedPeriod: LeaderboardPeriod;
  onPeriodChange: (period: LeaderboardPeriod) => void;
  streakData?: LeaderboardData | null;
  isLoadingStreak?: boolean;
  isLoadingMoreStreak?: boolean;
  hasMoreStreak?: boolean;
  onLoadMoreStreak?: () => void;
  activeCategory?: LeaderboardCategory;
  onCategoryChange?: (category: LeaderboardCategory) => void;
  selectedGenderFilter?: 'male' | 'female';
  onGenderFilterChange?: (filter?: 'male' | 'female') => void;
  isLoading: boolean;
  isLoadingMore?: boolean;
  hasMore?: boolean;
  onLoadMore?: () => void;
  userStreak5k?: number;
  userStreak10k?: number;
  userStreak20k?: number;
  userPersonId?: number;
  privacyMode?: WalkingPrivacyMode;
  onUpdatePrivacyMode?: (mode: WalkingPrivacyMode) => Promise<boolean>;
  todaySteps?: number;
};

const PERIOD_TABS: { key: LeaderboardPeriod; label: string }[] = [
  { key: 'today', label: 'اليوم' },
  { key: 'weekly', label: 'الأسبوع' },
  { key: 'monthly', label: 'الشهر' },
  { key: 'all_time', label: 'الإجمالي' },
];

export function LeaderboardList({
  clubs,
  userClubStreaks,
  data,
  selectedPeriod,
  onPeriodChange,
  streakData,
  isLoadingStreak = false,
  isLoadingMoreStreak = false,
  hasMoreStreak = false,
  onLoadMoreStreak,
  activeCategory,
  onCategoryChange,
  selectedGenderFilter,
  onGenderFilterChange,
  isLoading,
  isLoadingMore = false,
  hasMore = false,
  onLoadMore,
  userStreak5k,
  userStreak10k,
  userStreak20k,
  userPersonId,
  privacyMode = 'visible',
  onUpdatePrivacyMode,
  todaySteps,
}: Props) {
  const [internalCategory, setInternalCategory] = useState<LeaderboardCategory>('steps');
  const currentCategory: LeaderboardCategory = activeCategory ?? internalCategory;
  const isStreakMode = currentCategory === 'streak';
  const resolvedClubs = useMemo(() => {
    if (clubs && clubs.length > 0) {
      const raceClub = LEADERBOARD_CLUBS[0];
      const dynamicList = clubs.map((c) => ({
        key: (c.slug || c.key) as LeaderboardCategory,
        label: c.name,
        shortLabel: c.short_name || c.name,
        subLabel: c.sub_label || 'يومياً',
        icon: c.icon || '👟',
        minSteps: c.min_steps,
        maxSteps: c.max_steps ?? undefined,
        badgeColor: c.badge_color || '#10B981',
        badgeDark: c.badge_dark || '#059669',
        accentLight: c.accent_light || '#D1FAE5',
        accentDark: c.accent_dark || '#064E3B',
      }));
      return [raceClub, ...dynamicList];
    }
    return LEADERBOARD_CLUBS;
  }, [clubs]);

  const clubsList = useMemo(() => resolvedClubs.slice(1), [resolvedClubs]);
  const isFourClubs = clubsList.length === 4;

  const activeClub = resolvedClubs.find((c) => c.key === currentCategory);
  const isClubMode = Boolean(activeClub && activeClub.minSteps > 0);

  const userClubStreak = useMemo(() => {
    if (userClubStreaks && typeof userClubStreaks[currentCategory] === 'number') {
      return userClubStreaks[currentCategory];
    }
    if (currentCategory === 'club_5k') return userStreak5k ?? 0;
    if (currentCategory === 'club_20k') return userStreak20k ?? 0;
    return userStreak10k ?? 0;
  }, [currentCategory, userClubStreaks, userStreak5k, userStreak10k, userStreak20k]);

  const [privacyModalVisible, setPrivacyModalVisible] = useState(false);
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  const handleCategoryChange = (category: LeaderboardCategory) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    if (onCategoryChange) {
      onCategoryChange(category);
    } else {
      setInternalCategory(category);
    }
  };

  const handlePersonPress = (personId: number) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}
    router.push(`/(app)/person/${personId}` as any);
  };

  // Active dataset and status based on selected category (Clubs 5k, 10k, 20k and Streak all use streak data)
  const isStreakOrClub = isStreakMode || isClubMode;
  const activeData = isStreakOrClub ? streakData : data;
  const currentLoading = isStreakOrClub ? isLoadingStreak : isLoading;
  const currentLoadingMore = isStreakOrClub ? isLoadingMoreStreak : isLoadingMore;
  const currentHasMore = isStreakOrClub ? hasMoreStreak : hasMore;
  const handleLoadMore = isStreakOrClub ? onLoadMoreStreak : onLoadMore;

  const viewerRank = activeData?.viewer_rank;
  const isTodayPeriod = selectedPeriod === 'today';
  const liveViewerSteps =
    isTodayPeriod && todaySteps != null && todaySteps > 0
      ? Math.max(viewerRank?.total_steps ?? 0, todaySteps)
      : (viewerRank?.total_steps ?? 0);

  const isFemaleBoard =
    activeData?.gender_visibility?.active_gender_filter === 'female' ||
    viewerRank?.person?.gender === 'female';

  // المنصة = أول 3 بالترتيب، والبقية = ما بعدهم بالترتيب.
  // مهم: لا نفلتر برقم المركز (rank) لأن المراكز كثيفة (Dense) وقد تتكرر مع التعادل
  // (مثال: 1,1,3) فكان المتعادلون يختفون من المنصة ولا يظهرون في البقية.
  const enhancedTopThree = useMemo(() => {
    const raw = (activeData?.top ?? []).slice(0, 3);
    return raw.map((entry) => {
      const isViewer = viewerRank?.person_id === entry.person_id || entry.is_self || (userPersonId && entry.person_id === userPersonId);
      const effectiveStreak = isViewer ? (entry.streak_days ?? entry.streak_10k ?? userClubStreak ?? 0) : (entry.streak_days ?? entry.streak_10k ?? 0);
      const effectiveSteps = isViewer && isTodayPeriod && todaySteps != null && todaySteps > 0
        ? Math.max(entry.total_steps || 0, todaySteps)
        : (entry.total_steps || 0);
      return {
        ...entry,
        total_steps: effectiveSteps,
        streak_days: effectiveStreak,
        streak_10k: effectiveStreak,
      };
    });
  }, [activeData?.top, viewerRank?.person_id, userPersonId, userClubStreak, isTodayPeriod, todaySteps]);

  const enhancedRemaining = useMemo(() => {
    const raw = (activeData?.top ?? []).slice(3);
    return raw.map((entry) => {
      const isViewer = viewerRank?.person_id === entry.person_id || entry.is_self || (userPersonId && entry.person_id === userPersonId);
      const effectiveStreak = isViewer ? (entry.streak_days ?? entry.streak_10k ?? userClubStreak ?? 0) : (entry.streak_days ?? entry.streak_10k ?? 0);
      const effectiveSteps = isViewer && isTodayPeriod && todaySteps != null && todaySteps > 0
        ? Math.max(entry.total_steps || 0, todaySteps)
        : (entry.total_steps || 0);
      return {
        ...entry,
        total_steps: effectiveSteps,
        streak_days: effectiveStreak,
        streak_10k: effectiveStreak,
      };
    });
  }, [activeData?.top, viewerRank?.person_id, userPersonId, userClubStreak, isTodayPeriod, todaySteps]);

  // لوحة الاستمرارية والنوادي: كل المتنافسين مرتبين بالأيام التراكمية
  const enhancedStreakEntries = useMemo(() => {
    const raw = (streakData?.top ?? []).slice();
    // ترتيب دفاعي: الأعلى سلسلة أولاً ثم الأقل rank
    raw.sort((a, b) => (b.streak_days ?? b.streak_10k ?? 0) - (a.streak_days ?? a.streak_10k ?? 0) || (a.rank ?? 9999) - (b.rank ?? 9999));
    return raw.map((entry) => {
      const isViewer =
        streakData?.viewer_rank?.person_id === entry.person_id ||
        entry.is_self ||
        (userPersonId && entry.person_id === userPersonId);
      const effectiveStreak = isViewer ? (entry.streak_days ?? entry.streak_10k ?? userClubStreak ?? 0) : (entry.streak_days ?? entry.streak_10k ?? 0);
      const effectiveSteps = isViewer && todaySteps != null && todaySteps > 0
        ? Math.max(entry.total_steps || 0, todaySteps)
        : (entry.total_steps || 0);
      return {
        ...entry,
        total_steps: effectiveSteps,
        streak_days: effectiveStreak,
        streak_10k: effectiveStreak,
      };
    });
  }, [streakData?.top, streakData?.viewer_rank?.person_id, userPersonId, userClubStreak, todaySteps]);

  // أثناء التحديث الصامت (مزامنة الخطوات في الخلفية) نُبقي القائمة القديمة ظاهرة
  // ولا نستبدلها بالسبينر — الاستبدال كان يقلّص محتوى الـ ScrollView في iOS
  // فيقفز السكرول لفوق مع كل مزامنة. السبينر الكامل للتحميل الأول فقط.
  const hasData = (activeData?.top?.length ?? 0) > 0;
  const showFullLoading = currentLoading && !hasData;

  const prevRankRef = useRef<number | null>(null);
  useEffect(() => {
    if (viewerRank?.rank) {
      if (prevRankRef.current != null && viewerRank.rank < prevRankRef.current) {
        try {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch {}
      }
      prevRankRef.current = viewerRank.rank;
    }
  }, [viewerRank?.rank]);

  const effectiveViewerStreak = viewerRank?.streak_days ?? userClubStreak ?? viewerRank?.streak_10k ?? 0;

  const maxStreak = useMemo(() => {
    const topEntry = activeData?.top?.[0];
    return Math.max(1, topEntry?.streak_days ?? topEntry?.streak_10k ?? 1);
  }, [activeData?.top]);

  const maxSteps = useMemo(() => {
    return activeData?.top?.[0]?.total_steps || 1;
  }, [activeData?.top]);

  const handleTabPress = (period: LeaderboardPeriod) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    onPeriodChange(period);
  };

  return (
    <View style={styles.container}>
      {/* 1. TOP HERO: User Standing Card ("أنت هنا!") - فوري وواضح لكبار السن */}
      {viewerRank && (
        <LinearGradient
          colors={
            isStreakMode
              ? isDark
                ? ['#431407', '#290E04']
                : ['#FFEDD5', '#FED7AA']
              : isClubMode
                ? isDark
                  ? [activeClub!.accentDark, '#0B0F17']
                  : [activeClub!.accentLight, '#FFFFFF']
                : isDark
                  ? ['#064E3B', '#022C22']
                  : ['#D1FAE5', '#A7F3D0']
          }
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={[
            styles.viewerHeroCard,
            {
              borderColor: isStreakMode
                ? isDark ? '#EA580C' : '#F97316'
                : isClubMode
                  ? (viewerRank.is_qualified ?? (liveViewerSteps >= (activeClub?.minSteps ?? 0) && (!activeClub?.maxSteps || liveViewerSteps <= activeClub.maxSteps)))
                    ? activeClub!.badgeColor
                    : (isDark ? '#4B5563' : '#D1D5DB')
                  : isDark ? '#059669' : '#10B981',
            },
          ]}>
          <View
            style={[
              styles.viewerRankBadge,
              {
                backgroundColor: isStreakMode
                  ? isDark ? '#EA580C' : '#C2410C'
                  : isClubMode
                    ? viewerRank.rank
                      ? (isDark ? activeClub!.badgeDark : activeClub!.badgeColor)
                      : (isDark ? '#374151' : '#9CA3AF')
                    : isDark ? '#059669' : '#047857',
              },
            ]}>
            <ThemedText style={styles.viewerRankNumber}>
              {viewerRank.rank ? `#${viewerRank.rank}` : '⏳'}
            </ThemedText>
          </View>

          <View style={styles.viewerAvatar}>
            {viewerRank.person?.avatar ? (
              <Image source={{ uri: viewerRank.person.avatar }} style={styles.avatar} />
            ) : (
              <View
                style={[
                  styles.avatarPlaceholder,
                  {
                    backgroundColor: isStreakMode
                      ? isDark ? '#EA580C' : '#F97316'
                      : isClubMode
                        ? (isDark ? activeClub!.badgeDark : activeClub!.badgeColor)
                        : isDark ? '#059669' : '#10B981',
                  },
                ]}>
                <ThemedText style={{ color: '#FFFFFF', fontFamily: 'Tajawal-Bold', fontSize: 16 }}>
                  {viewerRank.person?.name?.slice(0, 1) || 'أ'}
                </ThemedText>
              </View>
            )}
          </View>

          <View style={{ flex: 1 }}>
            <ThemedText
              style={[
                styles.viewerTitle,
                {
                  color: isStreakMode
                    ? isDark ? '#FFFFFF' : '#7C2D12'
                    : isClubMode
                      ? isDark ? '#FFFFFF' : (isDark ? activeClub!.badgeDark : activeClub!.badgeColor)
                      : isDark ? '#FFFFFF' : '#064E3B',
                },
              ]}>
              {isStreakMode
                ? 'سلسلة الأيام النشطة الخاصة بك'
                : isClubMode
                  ? viewerRank.active_club && viewerRank.active_club !== activeClub!.key
                    ? (viewerRank.active_club === 'club_20k' ? 'أنت متقدم في نادي الـ 20,000! 🥇' : 'أنت متقدم في نادي الـ 10,000! 🥈')
                    : (viewerRank.is_qualified ?? (effectiveViewerStreak > 0 && (!activeClub?.maxSteps || liveViewerSteps <= activeClub.maxSteps)))
                      ? `عضو في ${activeClub!.label}! 🎉`
                      : `طريقك لدخول ${activeClub!.label}`
                  : isFemaleBoard
                    ? 'ترتيبكِ الحالي بين نساء العائلة'
                    : 'ترتيبك الحالي بين رجال العائلة'}
            </ThemedText>
            <View style={styles.viewerSubtitleRow}>
              <ThemedText
                style={[
                  styles.viewerSubtitle,
                  {
                    color: isStreakMode
                      ? isDark ? '#FDBA74' : '#9A3412'
                      : isClubMode
                        ? isDark ? '#E2E8F0' : '#475569'
                        : isDark ? '#A7F3D0' : '#047857',
                  },
                ]}>
                {isStreakOrClub ? (
                  isClubMode ? (
                    viewerRank.is_today_completed
                      ? `🎉 أنجزت ${formatSteps(activeClub!.minSteps)} خطوة اليوم وسلسلتك مستمرة!`
                      : effectiveViewerStreak > 0
                        ? `⚡ امشِ ${formatSteps(activeClub!.minSteps)} خطوة اليوم لتستمر سلسلتك!`
                        : `ابدأ سلسلتك في ${activeClub!.label} بإنجاز الخطوات اليوم! 💪`
                  ) : (
                    viewerRank.is_today_completed
                      ? '🎉 أنجزت 10,000 خطوة اليوم والسلسلة مستمرة!'
                      : effectiveViewerStreak > 0
                        ? '⚡ امشِ 10,000 خطوة اليوم لتستمر السلسلة!'
                        : 'ابدأ سلسلتك اليوم بإنجاز 10k! 💪'
                  )
                ) : viewerRank.in_top ? (
                  isFemaleBoard
                    ? 'أنتِ ضمن قائمة المتصدرات! 🌟'
                    : 'أنت ضمن قائمة المتصدرين! 🌟'
                ) : (
                  isFemaleBoard
                    ? 'استمري في المشي لتصلي للصدارة! 💪'
                    : 'استمر في المشي لتصل للصدارة! 💪'
                )}
              </ThemedText>
            </View>
          </View>

          <View style={{ alignItems: 'flex-end' }}>
            {isStreakOrClub ? (
              <>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, flexShrink: 0 }}>
                  <ThemedText style={{ fontSize: 15 }}>🔥</ThemedText>
                  <AnimatedCounter
                    value={effectiveViewerStreak}
                    formatter={formatStreakDays}
                    style={[
                      styles.viewerSteps,
                      {
                        color: isClubMode
                          ? (isDark ? activeClub!.badgeDark : activeClub!.badgeColor)
                          : (isDark ? '#FB923C' : '#9A3412'),
                      },
                    ]}
                  />
                </View>
                <ThemedText
                  style={[
                    styles.viewerStepsLabel,
                    {
                      color: isClubMode
                        ? (isDark ? activeClub!.badgeDark : activeClub!.badgeColor)
                        : (isDark ? '#FDBA74' : '#9A3412'),
                    },
                  ]}>
                  {isClubMode ? `متتالية (+${formatSteps(activeClub!.minSteps)})` : 'متتالية (+10k)'}
                </ThemedText>
              </>
            ) : (
              <>
                <AnimatedCounter
                  value={liveViewerSteps}
                  style={[
                    styles.viewerSteps,
                    {
                      color: isDark ? '#34D399' : '#064E3B',
                    },
                  ]}
                />
                <ThemedText
                  style={[
                    styles.viewerStepsLabel,
                    {
                      color: isDark ? '#A7F3D0' : '#047857',
                    },
                  ]}>
                  خطوة
                </ThemedText>
              </>
            )}
          </View>
        </LinearGradient>
      )}

      {/* 2. MASTER COMPETITION & CLUBS SELECTOR: Fixed View (No Horizontal Scroll) */}
      <View style={styles.clubsTabsContainer}>
        {/* الصف الأول: سباق الخطوات العام (المنافسة المفتوحة) */}
        {(() => {
          const raceConfig = LEADERBOARD_CLUBS[0];
          const isRaceSelected = currentCategory === 'steps';
          return (
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={() => handleCategoryChange('steps')}
              style={[
                styles.raceCardItem,
                {
                  backgroundColor: isRaceSelected
                    ? isDark ? raceConfig.accentDark : raceConfig.accentLight
                    : isDark ? '#1F2937' : '#F9FAFB',
                  borderColor: isRaceSelected
                    ? raceConfig.badgeColor
                    : isDark ? '#374151' : '#E5E7EB',
                },
                isRaceSelected && styles.clubTabItemActive,
              ]}>
              <View style={styles.raceCardLeft}>
                <ThemedText style={styles.raceCardIcon}>{raceConfig.icon}</ThemedText>
                <View style={{ flex: 1 }}>
                  <ThemedText
                    style={[
                      styles.raceCardTitle,
                      {
                        color: isRaceSelected
                          ? isDark ? '#FFFFFF' : raceConfig.badgeColor
                          : colors.text,
                        fontFamily: isRaceSelected ? 'Tajawal-Bold' : 'Tajawal-Medium',
                      },
                    ]}>
                    {raceConfig.label}
                  </ThemedText>
                  <ThemedText style={[styles.raceCardSubtitle, { color: colors.icon }]}>
                    {raceConfig.subLabel}
                  </ThemedText>
                </View>
              </View>

              <View
                style={[
                  styles.raceCardBadge,
                  {
                    backgroundColor: isRaceSelected
                      ? raceConfig.badgeColor
                      : isDark ? '#374151' : '#E5E7EB',
                  },
                ]}>
                <ThemedText
                  style={[
                    styles.raceCardBadgeText,
                    {
                      color: isRaceSelected ? '#FFFFFF' : colors.icon,
                    },
                  ]}>
                  {isRaceSelected ? 'الترتيب العام' : 'عرض السباق'}
                </ThemedText>
              </View>
            </TouchableOpacity>
          );
        })()}

        {/* الصف الثاني: نوادي الخطوات اليومية (متجاوبة: تقسيم متساوٍ لـ 4 نوادي أو أقل في صف واحد، وتمرير أفقي ناعم إذا كانت 5 فأكثر) */}
        {(() => {
          if (clubsList.length === 0) return null;

          const renderClubItem = (club: (typeof resolvedClubs)[0], isScrollable = false) => {
            const isSelected = currentCategory === club.key;
            return (
              <TouchableOpacity
                key={club.key}
                activeOpacity={0.8}
                onPress={() => handleCategoryChange(club.key)}
                style={[
                  styles.clubColItem,
                  isScrollable && styles.clubColItemScrollable,
                  isFourClubs && !isScrollable && styles.clubColItemFour,
                  {
                    backgroundColor: isSelected
                      ? isDark ? club.accentDark : club.accentLight
                      : isDark ? '#1F2937' : '#F9FAFB',
                    borderColor: isSelected
                      ? club.badgeColor
                      : isDark ? '#374151' : '#E5E7EB',
                  },
                  isSelected && styles.clubTabItemActive,
                ]}>
                <ThemedText style={[styles.clubColIcon, isFourClubs && !isScrollable && styles.clubColIconFour]}>
                  {club.icon}
                </ThemedText>
                <ThemedText
                  numberOfLines={isFourClubs && !isScrollable ? 2 : 1}
                  style={[
                    styles.clubColTitle,
                    isFourClubs && !isScrollable && styles.clubColTitleFour,
                    {
                      color: isSelected
                        ? isDark ? '#FFFFFF' : club.badgeColor
                        : colors.text,
                      fontFamily: isSelected ? 'Tajawal-Bold' : 'Tajawal-Medium',
                    },
                  ]}>
                  {club.shortLabel}
                </ThemedText>
                <ThemedText
                  numberOfLines={1}
                  style={[
                    styles.clubColSub,
                    isFourClubs && !isScrollable && styles.clubColSubFour,
                    {
                      color: isSelected
                        ? isDark ? club.badgeDark : club.badgeColor
                        : colors.icon,
                    },
                  ]}>
                  {club.subLabel}
                </ThemedText>
              </TouchableOpacity>
            );
          };

          return clubsList.length <= 4 ? (
            <View style={[styles.clubsRow, isFourClubs && styles.clubsRowFour]}>
              {clubsList.map((club) => renderClubItem(club, false))}
            </View>
          ) : (
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.clubsScrollRow}
              style={styles.clubsScrollView}
            >
              {clubsList.map((club) => renderClubItem(club, true))}
            </ScrollView>
          );
        })()}
      </View>

      {/* =========================================================================
          === أبطال الاستمرارية (Streak Champions) - كود محفوظ للرجوع إليه مستقبلاً ===
          =========================================================================
          {isStreakMode && (
            <View style={[styles.masterTabsWrapper, { backgroundColor: isDark ? '#1F2937' : '#F3F4F6' }]}>
              <TouchableOpacity
                activeOpacity={0.8}
                onPress={() => handleCategoryChange('streak')}
                style={[
                  styles.masterTab,
                  styles.masterTabActive,
                  { backgroundColor: isDark ? '#431407' : '#FFFFFF' },
                  isDark && { borderColor: '#EA580C', borderWidth: 1 },
                ]}>
                <ThemedText style={styles.masterTabIcon}>🔥</ThemedText>
                <ThemedText style={[styles.masterTabText, styles.masterTabTextActive, { color: isDark ? '#FB923C' : '#EA580C' }]}>
                  أبطال الاستمرارية
                </ThemedText>
              </TouchableOpacity>
            </View>
          )}
          ========================================================================= */}

      {/* 3. DYNAMIC SECONDARY CONTROLS (Only period pills when in General Steps mode) */}
      {!isStreakMode && !isClubMode && (
        <View style={styles.periodChipsRow}>
          {PERIOD_TABS.map((tab) => {
            const isSelected = selectedPeriod === tab.key;
            return (
              <TouchableOpacity
                key={tab.key}
                activeOpacity={0.75}
                onPress={() => handleTabPress(tab.key)}
                style={[
                  styles.periodChip,
                  {
                    backgroundColor: isSelected
                      ? silaTheme.primary
                      : isDark ? '#1F2937' : '#F3F4F6',
                    borderColor: isSelected
                      ? silaTheme.primary
                      : isDark ? '#374151' : '#E5E7EB',
                  },
                ]}>
                <ThemedText
                  style={[
                    styles.periodChipText,
                    { color: isSelected ? '#FFFFFF' : colors.icon },
                    isSelected && styles.periodChipTextActive,
                  ]}>
                  {tab.label}
                </ThemedText>
              </TouchableOpacity>
            );
          })}
        </View>
      )}

      {/* 4. UTILITY ROW: Privacy Setting & Active Period Date Range */}
      <View style={styles.utilityBarRow}>
        <View style={styles.periodRangeBadge}>
          <Ionicons
            name={isStreakMode ? 'flame-outline' : isClubMode ? 'ribbon-outline' : 'calendar-outline'}
            size={13}
            color={colors.icon}
          />
          <ThemedText style={[styles.periodRangeText, { color: colors.icon }]}>
            {isStreakMode
              ? 'سلسلة الأيام النشطة (+10,000 خطوة)'
              : isClubMode
                ? `${activeClub?.label} • سلسلة استمرارية`
                : formatLeaderboardPeriodRange(selectedPeriod, activeData?.date_range)}
          </ThemedText>
        </View>

        {/* Privacy Trigger Pill */}
        <TouchableOpacity
          activeOpacity={0.8}
          onPress={() => {
            try { Haptics.selectionAsync(); } catch {}
            setPrivacyModalVisible(true);
          }}
          style={[
            styles.privacyTriggerPill,
            {
              backgroundColor: isDark ? '#27272A' : '#FFFFFF',
              borderColor: privacyMode === 'hidden'
                ? '#EF4444'
                : privacyMode === 'anonymous'
                ? '#F59E0B'
                : (isDark ? '#3F3F46' : '#E5E7EB'),
            },
          ]}>
          <ThemedText style={styles.privacyTriggerIcon}>
            {privacyMode === 'hidden' ? '🚫' : privacyMode === 'anonymous' ? '🥷' : '🏆'}
          </ThemedText>
          <ThemedText
            style={[
              styles.privacyTriggerText,
              {
                color: privacyMode === 'hidden'
                  ? '#EF4444'
                  : privacyMode === 'anonymous'
                  ? '#F59E0B'
                  : colors.text,
              },
            ]}>
            {privacyMode === 'hidden'
              ? 'مستبعد'
              : privacyMode === 'anonymous'
              ? 'متخفي'
              : 'ظاهر للجميع'}
          </ThemedText>
          <Ionicons name="options-outline" size={13} color={colors.icon} />
        </TouchableOpacity>
      </View>

      {/* Hidden Mode Banner Notice */}
      {privacyMode === 'hidden' && (
        <View
          style={[
            styles.hiddenBannerCard,
            {
              backgroundColor: isDark ? '#1C1917' : '#FEF2F2',
              borderColor: isDark ? '#7F1D1D' : '#FCA5A5',
            },
          ]}>
          <View style={styles.hiddenBannerIconWrap}>
            <ThemedText style={{ fontSize: 22 }}>🚫</ThemedText>
          </View>
          <View style={styles.hiddenBannerBody}>
            <ThemedText style={[styles.hiddenBannerTitle, { color: isDark ? '#FCA5A5' : '#B91C1C' }]}>
              أنت غير مدرج في لوحة المتصدرين
            </ThemedText>
            <ThemedText style={[styles.hiddenBannerDesc, { color: colors.icon }]}>
              خطواتك وسلسلتك مسجلة بالكامل في حسابك الشخصي، ولكن لا تظهر للمتنافسين بناءً على إعدادات خصوصيتك.
            </ThemedText>
          </View>
          <TouchableOpacity
            activeOpacity={0.8}
            onPress={() => setPrivacyModalVisible(true)}
            style={[styles.hiddenBannerBtn, { backgroundColor: isDark ? '#450A0A' : '#FEE2E2' }]}>
            <ThemedText style={[styles.hiddenBannerBtnText, { color: isDark ? '#FCA5A5' : '#B91C1C' }]}>
              تعديل
            </ThemedText>
          </TouchableOpacity>
        </View>
      )}

      {/* 2. Loading Spinner or Content */}
      {showFullLoading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator
            size="large"
            color={isStreakOrClub ? (isClubMode ? (activeClub?.badgeColor || '#059669') : '#EA580C') : silaTheme.primary}
          />
          <ThemedText style={[styles.loadingText, { color: colors.icon }]}>
            {isStreakOrClub
              ? isClubMode
                ? `جاري جلب أبطال ${activeClub?.label || 'النادي'}...`
                : 'جاري جلب أبطال سلسلة الأيام النشطة...'
              : 'جاري جلب قائمة المتصدرين...'}
          </ThemedText>
        </View>
      ) : (isStreakOrClub ? enhancedStreakEntries.length === 0 : enhancedTopThree.length === 0 && enhancedRemaining.length === 0) ? (
        /* Empty State */
        <View
          style={[
            styles.emptyCard,
            {
              backgroundColor: isDark ? '#18181B' : '#FFFFFF',
              borderColor: colors.border,
            },
          ]}>
          <View
            style={[
              styles.trophyIconWrap,
              isStreakMode
                ? {
                    backgroundColor: isDark ? '#431407' : '#FFEDD5',
                    borderColor: isDark ? '#9A3412' : '#FED7AA',
                  }
                : isClubMode
                  ? {
                      backgroundColor: isDark ? activeClub!.accentDark : activeClub!.accentLight,
                      borderColor: activeClub!.badgeColor,
                    }
                  : {
                      backgroundColor: isDark ? '#2D2305' : '#FEF3C7',
                      borderColor: isDark ? '#78350F' : '#FDE68A',
                    },
            ]}>
            <ThemedText style={styles.trophyEmoji}>
              {isStreakMode ? '🔥' : isClubMode ? activeClub!.icon : '🏆'}
            </ThemedText>
          </View>
          <ThemedText style={styles.emptyTitle}>
            {isStreakMode
              ? isFemaleBoard
                ? 'لوحة شرف السلسلة بانتظار أول بطلة!'
                : 'لوحة شرف السلسلة بانتظار أول بطل!'
              : isClubMode
                ? isFemaleBoard
                  ? `سلسلة استمرارية ${activeClub!.label} بانتظار أول بطلة!`
                  : `سلسلة استمرارية ${activeClub!.label} بانتظار أول بطل!`
                : selectedPeriod === 'today'
                  ? isFemaleBoard
                    ? 'لوحة شرف اليوم بانتظار أول متصدرة!'
                    : 'لوحة شرف اليوم بانتظار أول متصدر!'
                  : selectedPeriod === 'weekly'
                    ? isFemaleBoard
                      ? 'لوحة شرف الأسبوع بانتظار أول متصدرة!'
                      : 'لوحة شرف الأسبوع بانتظار أول متصدر!'
                    : selectedPeriod === 'monthly'
                      ? isFemaleBoard
                        ? 'لوحة شرف هذا الشهر بانتظار أول متصدرة!'
                        : 'لوحة شرف هذا الشهر بانتظار أول متصدر!'
                      : 'لا توجد خطوات مسجلة بعد'}
          </ThemedText>
          <ThemedText style={[styles.emptySubtitle, { color: colors.icon }]}>
            {isStreakMode
              ? isFemaleBoard
                ? 'كوني أول من تبدأ سلسلة الأيام النشطة اليوم بقطع 10,000 خطوة أو أكثر والمحافظة عليها! 🌟'
                : 'كن أول من يبدأ سلسلة الأيام النشطة اليوم بقطع 10,000 خطوة أو أكثر والمحافظة عليها! 🌟'
              : isClubMode
                ? isFemaleBoard
                  ? `حافظي على وتيرة ${formatSteps(activeClub!.minSteps)} خطوة يومياً لبناء سلسلة استمرارية واعتلاء الصدارة! 🌟`
                  : `حافظ على وتيرة ${formatSteps(activeClub!.minSteps)} خطوة يومياً لبناء سلسلة استمرارية واعتلاء الصدارة! 🌟`
                : isFemaleBoard
                  ? 'ابدئي بالمشي الآن ومزامنة خطواتكِ لتعتلي صدارة لوحة الشرف بين نساء العائلة! 🌟'
                  : 'ابدأ بالمشي الآن ومزامنة خطواتك لتعتلي صدارة لوحة الشرف بين رجال العائلة! 🌟'}
          </ThemedText>
        </View>
      ) : isStreakOrClub ? (
        <>
          {/* وضع الاستمرارية والنوادي: شبكة الأبطال (كل 3 أبطال جنب بعض في كل مركز) */}
          <StreakChampionsBoard
            entries={enhancedStreakEntries}
            viewerPersonId={userPersonId}
            viewerTodaySteps={todaySteps}
            onPressPerson={handlePersonPress}
          />

          {/* زر عرض المزيد للاستمرارية والنوادي */}
          {currentHasMore && (
            <TouchableOpacity
              activeOpacity={0.8}
              disabled={currentLoadingMore}
              onPress={handleLoadMore}
              style={[
                styles.loadMoreBtn,
                {
                  backgroundColor: isDark ? '#27272A' : '#F3F4F6',
                  borderColor: colors.border,
                },
              ]}>
              {currentLoadingMore ? (
                <ActivityIndicator
                  size="small"
                  color={isClubMode ? (activeClub?.badgeColor || '#EA580C') : '#EA580C'}
                />
              ) : (
                <ThemedText
                  style={[
                    styles.loadMoreText,
                    { color: isClubMode ? (activeClub?.badgeColor || '#EA580C') : '#EA580C' },
                  ]}>
                  عرض المزيد من المتنافسين
                </ThemedText>
              )}
            </TouchableOpacity>
          )}
        </>
      ) : (
        <>
          {/* سباق الخطوات فقط: البوديوم للثلاثة الأوائل */}
          {enhancedTopThree.length > 0 && (
            <LeaderboardPodium
              topThree={enhancedTopThree}
              onPressPerson={handlePersonPress}
              mode="steps"
            />
          )}

          {/* سباق الخطوات فقط: قائمة المراكز 4+ */}
          {enhancedRemaining.length > 0 && (
            <View style={styles.listContainer}>
              {enhancedRemaining.map((entry, idx) => {
                const isViewer = viewerRank?.person_id === entry.person_id || entry.is_self;
                const ratio = isStreakOrClub
                  ? Math.min(100, Math.round(((entry.streak_days || entry.streak_10k || 0) / maxStreak) * 100))
                  : Math.min(100, Math.round((entry.total_steps / maxSteps) * 100));

                const tierConfig = getStepTierConfig(entry.total_steps, isDark);
                const isRowAnon = Boolean(entry.is_anonymous && !isViewer);

                return (
                  <Animated.View
                    key={entry.person_id ? `p_${entry.person_id}` : `anon_${entry.rank}_${idx}`}
                    layout={LinearTransition.springify().damping(16).stiffness(120)}
                    style={[
                      styles.rowCard,
                      {
                        backgroundColor: isViewer
                          ? isDark
                            ? isStreakOrClub ? '#2B170B' : '#14382A'
                            : isStreakOrClub ? '#FFF7ED' : '#ECFDF5'
                          : isDark
                            ? '#18181B'
                            : '#FFFFFF',
                        borderColor: isViewer
                          ? isStreakOrClub ? '#EA580C' : silaTheme.primary
                          : colors.border,
                      },
                    ]}>
                    {/* Rank Badge */}
                    <View style={styles.rankBadge}>
                      <ThemedText style={[styles.rankNumber, { color: colors.icon }]}>
                        #{entry.rank}
                      </ThemedText>
                    </View>

                    {/* Avatar */}
                    <TouchableOpacity
                      activeOpacity={isRowAnon ? 1 : 0.75}
                      disabled={isRowAnon}
                      onPress={() => {
                        const targetId = entry.person?.id || entry.person_id;
                        if (targetId) handlePersonPress(targetId);
                      }}
                      style={styles.avatarWrap}>
                      {entry.person?.avatar ? (
                        <Image source={{ uri: entry.person.avatar }} style={styles.avatar} />
                      ) : isRowAnon ? (
                        <AnonymousAvatar
                          gender={entry.person?.gender}
                          avatar={null}
                          size={40}
                          backgroundColor={isDark ? '#27272A' : '#E5E7EB'}
                          iconColor={colors.text}
                        />
                      ) : (
                        <View style={[styles.avatarPlaceholder, { backgroundColor: isDark ? '#27272A' : '#E5E7EB' }]}>
                          <ThemedText style={[styles.avatarText, { color: colors.text }]}>
                            {entry.person?.name?.slice(0, 1) || 'ع'}
                          </ThemedText>
                        </View>
                      )}
                    </TouchableOpacity>

                    {/* Person details & Bar */}
                    <View style={styles.infoCol}>
                      <View style={styles.nameAndSteps}>
                        <View style={styles.nameStreakGroup}>
                          <TouchableOpacity
                            activeOpacity={isRowAnon ? 1 : 0.75}
                            disabled={isRowAnon}
                            style={styles.nameTouch}
                            onPress={() => {
                              const targetId = entry.person?.id || entry.person_id;
                              if (targetId) handlePersonPress(targetId);
                            }}>
                            <ThemedText
                              numberOfLines={2}
                              style={[
                                styles.nameText,
                                isViewer && { color: isStreakOrClub ? '#EA580C' : silaTheme.primary },
                              ]}>
                              {isRowAnon ? 'متسابق متخفي' : entry.person?.name}
                              {isViewer && ' (أنت)'}
                            </ThemedText>
                          </TouchableOpacity>

                          {/* In Streak / Club Mode: Show badge only when completed (no waiting tag) */}
                          {isStreakOrClub && entry.is_today_completed ? (
                            <View
                              style={[
                                styles.todayStatusBadge,
                                {
                                  backgroundColor: isDark ? 'rgba(5, 150, 105, 0.22)' : '#ECFDF5',
                                  borderColor: isDark ? '#059669' : '#A7F3D0',
                                },
                              ]}>
                              <ThemedText
                                style={[
                                  styles.todayStatusText,
                                  {
                                    color: isDark ? '#34D399' : '#059669',
                                  },
                                ]}>
                                ✅ أنجز اليوم
                              </ThemedText>
                            </View>
                          ) : null}
                        </View>

                        {/* Metric Value: Both streak days and steps in Streak/Club mode */}
                        {isStreakOrClub ? (
                          <View style={{ alignItems: 'flex-end', gap: 2 }}>
                            <View style={styles.streakValuePill}>
                              <ThemedText style={{ fontSize: 13 }}>🔥</ThemedText>
                              <ThemedText style={[styles.streakDaysVal, { color: isDark ? '#FB923C' : '#EA580C' }]}>
                                {formatStreakDays(entry.streak_days ?? entry.streak_10k)}
                              </ThemedText>
                            </View>
                            <ThemedText style={[styles.stepsSubVal, { color: colors.icon }]}>
                              👟 {formatSteps(entry.total_steps)} خطوة
                            </ThemedText>
                          </View>
                        ) : (
                          <View style={{ flexDirection: 'row-reverse', alignItems: 'center', gap: 4 }}>
                            <AnimatedCounter
                              value={entry.total_steps}
                              style={[styles.stepsVal, { color: tierConfig.textColor }]}
                            />
                            <ThemedText style={[styles.stepsVal, { color: tierConfig.textColor }]}>
                              خطوة
                            </ThemedText>
                          </View>
                        )}
                      </View>

                      {/* Progress Bar */}
                      <View style={[styles.progressBarBg, { backgroundColor: isDark ? '#27272A' : '#F3F4F6' }]}>
                        <View
                          style={[
                            styles.progressBarFill,
                            {
                              width: `${ratio}%`,
                              backgroundColor: isStreakOrClub
                                ? '#EA580C'
                                : isViewer
                                  ? silaTheme.primary
                                  : tierConfig.barColor,
                            },
                          ]}
                        />
                      </View>
                    </View>
                  </Animated.View>
                );
              })}

              {/* Load More Button / Indicator */}
              {currentHasMore && (
                <TouchableOpacity
                  activeOpacity={0.8}
                  disabled={currentLoadingMore}
                  onPress={handleLoadMore}
                  style={[
                    styles.loadMoreBtn,
                    {
                      backgroundColor: isDark ? '#27272A' : '#F3F4F6',
                      borderColor: colors.border,
                    },
                  ]}>
                  {currentLoadingMore ? (
                    <ActivityIndicator size="small" color={isStreakMode ? '#EA580C' : silaTheme.primary} />
                  ) : (
                    <ThemedText style={[styles.loadMoreText, { color: isStreakMode ? '#EA580C' : silaTheme.primary }]}>
                      عرض المزيد من المتنافسين
                    </ThemedText>
                  )}
                </TouchableOpacity>
              )}
            </View>
          )}
        </>
      )}

      <LeaderboardPrivacyModal
        visible={privacyModalVisible}
        currentMode={privacyMode}
        onClose={() => setPrivacyModalVisible(false)}
        onSave={async (mode) => {
          if (onUpdatePrivacyMode) {
            return await onUpdatePrivacyMode(mode);
          }
          return false;
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginVertical: 10,
  },
  viewerHeroCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 20,
    borderWidth: 1.5,
    gap: 12,
    marginBottom: 14,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.1,
        shadowRadius: 6,
      },
      android: { elevation: 3 },
    }),
  },
  viewerRankBadge: {
    minWidth: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  viewerRankNumber: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 14,
  },
  viewerAvatar: {
    position: 'relative',
  },
  viewerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  viewerSubtitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 2,
  },
  viewerSubtitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    lineHeight: 18,
  },
  viewerSteps: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 18,
  },
  viewerStepsLabel: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
  clubsTabsContainer: {
    marginBottom: 12,
  },
  raceCardItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 15,
    borderWidth: 1.5,
    marginBottom: 8,
  },
  raceCardLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  raceCardIcon: {
    fontSize: 22,
  },
  raceCardTitle: {
    fontSize: 14,
  },
  raceCardSubtitle: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
    marginTop: 1,
  },
  raceCardBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  raceCardBadgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  clubsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  clubsRowFour: {
    gap: 5,
  },
  clubsScrollView: {
    width: '100%',
  },
  clubsScrollRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 2,
  },
  clubColItemScrollable: {
    minWidth: 108,
    paddingHorizontal: 12,
  },
  clubColItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 9,
    paddingHorizontal: 4,
    borderRadius: 14,
    borderWidth: 1.5,
    minHeight: 62,
  },
  clubColItemFour: {
    paddingVertical: 6,
    paddingHorizontal: 2,
    borderRadius: 12,
    minHeight: 65,
  },
  clubColIcon: {
    fontSize: 18,
    marginBottom: 2,
  },
  clubColIconFour: {
    fontSize: 15,
    marginBottom: 1,
  },
  clubColTitle: {
    fontSize: 12,
    textAlign: 'center',
  },
  clubColTitleFour: {
    fontSize: 10.5,
    lineHeight: 13,
    textAlign: 'center',
  },
  clubColSub: {
    fontSize: 10,
    fontFamily: 'Tajawal-Medium',
    marginTop: 1,
    textAlign: 'center',
  },
  clubColSubFour: {
    fontSize: 9.5,
    lineHeight: 11,
    marginTop: 1,
    textAlign: 'center',
  },
  clubTabItemActive: {
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.12,
        shadowRadius: 4,
      },
      android: { elevation: 2 },
    }),
  },
  masterTabsWrapper: {
    flexDirection: 'row',
    borderRadius: 18,
    padding: 4,
    marginBottom: 12,
    gap: 6,
  },
  masterTab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    minHeight: 48,
    borderRadius: 14,
  },
  masterTabActive: {
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.12,
        shadowRadius: 5,
      },
      android: { elevation: 3 },
    }),
  },
  masterTabIcon: {
    fontSize: 18,
  },
  masterTabText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
  },
  masterTabTextActive: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
  },
  periodChipsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 6,
    marginBottom: 12,
  },
  periodChip: {
    flex: 1,
    paddingVertical: 9,
    paddingHorizontal: 4,
    borderRadius: 20,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 38,
  },
  periodChipText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12.5,
  },
  periodChipTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  utilityBarRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
    paddingHorizontal: 2,
    minHeight: 36,
  },
  periodRangeBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 4,
    paddingVertical: 4,
    flexShrink: 1,
  },
  periodRangeText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },

  privacyTriggerPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    borderWidth: 1,
  },
  privacyTriggerIcon: {
    fontSize: 13,
  },
  privacyTriggerText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  hiddenBannerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
    marginBottom: 14,
  },
  hiddenBannerIconWrap: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  hiddenBannerBody: {
    flex: 1,
  },
  hiddenBannerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    marginBottom: 2,
  },
  hiddenBannerDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    lineHeight: 16,
  },
  hiddenBannerBtn: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 10,
  },
  hiddenBannerBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  loadingContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
    gap: 12,
  },
  loadingText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
  emptyCard: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    borderRadius: 20,
    borderWidth: 1,
    marginVertical: 12,
  },
  trophyIconWrap: {
    width: 60,
    height: 60,
    borderRadius: 30,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    marginBottom: 12,
  },
  trophyEmoji: {
    fontSize: 30,
  },
  emptyTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    marginBottom: 6,
    textAlign: 'center',
  },
  emptySubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    textAlign: 'center',
    lineHeight: 20,
    maxWidth: 280,
  },
  listContainer: {
    gap: 8,
    marginTop: 8,
    marginBottom: 16,
  },
  rowCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 10,
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
  },
  rankBadge: {
    width: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rankNumber: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  avatarWrap: {
    position: 'relative',
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
  },
  avatarPlaceholder: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  infoCol: {
    flex: 1,
    minWidth: 0,
    gap: 6,
  },
  nameAndSteps: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 6,
    minWidth: 0,
  },
  nameStreakGroup: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flex: 1,
    minWidth: 0,
    flexShrink: 1,
  },
  nameTouch: {
    flexShrink: 1,
    minWidth: 0,
  },
  nameText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11.5,
    lineHeight: 15.5,
    flexShrink: 1,
  },
  todayStatusBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 8,
    borderWidth: 1,
    flexShrink: 0,
  },
  todayStatusText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9.5,
    textAlign: 'center',
  },
  streakValuePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    flexShrink: 0,
  },
  streakDaysVal: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  stepsSubVal: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10.5,
  },
  stepsVal: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    flexShrink: 0,
  },
  progressBarBg: {
    height: 5,
    borderRadius: 3,
    overflow: 'hidden',
    width: '100%',
  },
  progressBarFill: {
    height: '100%',
    borderRadius: 3,
  },
  loadMoreBtn: {
    paddingVertical: 12,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 6,
  },
  loadMoreText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
});
