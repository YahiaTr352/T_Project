import { ThemedText } from '@/components/themed-text';
import { Colors } from '@/constants/theme';
import type { LeaderboardEntry } from '@/lib/api/walkingApi';
import { formatStreakDays, formatSteps } from '@/lib/walking/formatters';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import React, { useMemo } from 'react';
import {
  Platform,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { AnonymousAvatar } from './AnonymousAvatar';

export interface StreakTier {
  /** قيمة السلسلة المشتركة لهذه المجموعة (عدد الأيام) */
  streak: number;
  /** المركز الحقيقي (Dense rank من الباك — يؤخذ من أول عضو) */
  rank: number;
  members: LeaderboardEntry[];
  completedCount: number;
}

type Props = {
  entries: LeaderboardEntry[];
  viewerPersonId?: number;
  viewerTodaySteps?: number;
  onPressPerson?: (personId: number) => void;
};

interface TierMeta {
  medal: string;
  label: string;
  accentColor: string;
  accentDark: string;
  bgLight: string;
  bgDark: string;
  borderLight: string;
  borderDark: string;
}

function getTierMeta(tierIndex: number, rank: number): TierMeta {
  if (tierIndex === 0) {
    return {
      medal: '👑',
      label: 'المركز الأول',
      accentColor: '#D97706',
      accentDark: '#FBBF24',
      bgLight: '#FFFBEB',
      bgDark: '#2D1B06',
      borderLight: '#FDE68A',
      borderDark: '#78350F',
    };
  }
  if (tierIndex === 1) {
    return {
      medal: '🥈',
      label: 'المركز الثاني',
      accentColor: '#64748B',
      accentDark: '#94A3B8',
      bgLight: '#F8FAFC',
      bgDark: '#1E293B',
      borderLight: '#E2E8F0',
      borderDark: '#334155',
    };
  }
  if (tierIndex === 2) {
    return {
      medal: '🥉',
      label: 'المركز الثالث',
      accentColor: '#C2410C',
      accentDark: '#FB923C',
      bgLight: '#FFF7ED',
      bgDark: '#291408',
      borderLight: '#FED7AA',
      borderDark: '#7C2D12',
    };
  }
  return {
    medal: '🏅',
    label: 'المركز ' + rank,
    accentColor: '#71717A',
    accentDark: '#A1A1AA',
    bgLight: '#FAFAFA',
    bgDark: '#1C1917',
    borderLight: '#E5E7EB',
    borderDark: '#292524',
  };
}

export function StreakChampionsBoard({ entries, viewerPersonId, viewerTodaySteps, onPressPerson }: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  const tiers = useMemo<StreakTier[]>(() => {
    const map = new Map<number, LeaderboardEntry[]>();
    for (const e of entries) {
      const key = Math.max(0, Math.round(e.streak_days ?? e.streak_10k ?? 0));
      if (!map.has(key)) map.set(key, []);
      map.get(key)!.push(e);
    }
    // ترتيب تنازلي حسب الأيام — الصدارة أولاً
    const sortedKeys = Array.from(map.keys()).sort((a, b) => b - a);
    return sortedKeys.map((streak) => {
      const members = map.get(streak)!;
      // ترتيب أعضاء نفس المركز تنازلياً حسب خطوات اليوم
      members.sort((a, b) => (b.total_steps || 0) - (a.total_steps || 0));
      // المركز من الباك (Dense) — أصغر rank داخل المجموعة
      const rank = Math.min(...members.map((m) => m.rank ?? 9999));
      const completedCount = members.filter((m) => m.is_today_completed).length;
      return { streak, rank, members, completedCount };
    });
  }, [entries]);

  const handlePress = (entry: LeaderboardEntry) => {
    const isAnon = Boolean(entry.is_anonymous && !entry.is_self);
    if (isAnon) return;
    const targetId = entry.person?.id || entry.person_id;
    if (!targetId) return;
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}
    onPressPerson?.(targetId);
  };

  const isViewerEntry = (e: LeaderboardEntry) =>
    Boolean(e.is_self || (viewerPersonId && e.person_id === viewerPersonId));

  if (tiers.length === 0) return null;

  const renderAvatar = (entry: LeaderboardEntry, size: number, ringColor: string) => {
    const isAnon = Boolean(entry.is_anonymous && !entry.is_self);
    const isViewer = isViewerEntry(entry);
    const dotSize = 10;
    return (
      <View
        style={[
          styles.avatarRing,
          {
            borderColor: isViewer ? '#EA580C' : ringColor,
            width: size + 4,
            height: size + 4,
            borderRadius: (size + 4) / 2,
          },
        ]}>
        {entry.person?.avatar ? (
          <Image
            source={{ uri: entry.person.avatar }}
            style={{ width: size, height: size, borderRadius: size / 2 }}
          />
        ) : isAnon ? (
          <AnonymousAvatar
            gender={entry.person?.gender}
            size={size}
            backgroundColor={isDark ? '#27272A' : '#E5E7EB'}
            iconColor={colors.text}
          />
        ) : (
          <View
            style={[
              styles.avatarFallback,
              {
                width: size,
                height: size,
                borderRadius: size / 2,
                backgroundColor: isDark ? '#431407' : '#FFEDD5',
              },
            ]}>
            <ThemedText
              style={[
                styles.avatarFallbackText,
                {
                  fontSize: Math.round(size * 0.42),
                  color: isDark ? '#FB923C' : '#9A3412',
                },
              ]}>
              {entry.person?.name?.slice(0, 1) || 'ع'}
            </ThemedText>
          </View>
        )}
        {/* نقطة إنجاز اليوم (تظهر فقط عند إتمام الهدف) */}
        {entry.is_today_completed && (
          <View
            style={[
              styles.todayDot,
              {
                width: dotSize,
                height: dotSize,
                borderRadius: dotSize / 2,
                backgroundColor: '#22C55E',
                borderColor: isDark ? '#1C1917' : '#FFFFFF',
              },
            ]}
          />
        )}
      </View>
    );
  };

  return (
    <View style={styles.container}>
      {tiers.map((tier, tierIndex) => {
        const meta = getTierMeta(tierIndex, tier.rank);
        const memberCount = tier.members.length;

        return (
          <View key={'tier-' + tier.streak} style={styles.tierSection}>
            {/* 1. هيدر المركز: يعرض المركز والميدالية وشعلة الأيام */}
            <View style={styles.tierHeaderRow}>
              <View style={styles.tierTitleGroup}>
                <View
                  style={[
                    styles.rankBadge,
                    {
                      backgroundColor: isDark ? meta.borderDark : meta.borderLight,
                      borderColor: isDark ? meta.accentDark : meta.borderLight,
                    },
                  ]}>
                  <ThemedText style={styles.rankMedal}>{meta.medal}</ThemedText>
                  <ThemedText
                    style={[
                      styles.rankNumber,
                      { color: isDark ? meta.accentDark : meta.accentColor },
                    ]}>
                    #{tier.rank}
                  </ThemedText>
                </View>

                <ThemedText style={[styles.tierTitleText, { color: colors.text }]}>
                  {meta.label}
                </ThemedText>

                <ThemedText style={[styles.tierCountText, { color: colors.icon }]}>
                  ({memberCount === 1 ? 'بطل واحد' : memberCount === 2 ? 'بطلان' : memberCount + ' أبطال'})
                </ThemedText>
              </View>

              {/* شعلة الأيام الموحدة للمركز */}
              <View
                style={[
                  styles.streakHeaderBadge,
                  {
                    backgroundColor: isDark ? '#2D160A' : '#FFF7ED',
                    borderColor: isDark ? '#9A3412' : '#FED7AA',
                  },
                ]}>
                <ThemedText style={{ fontSize: 13 }}>🔥</ThemedText>
                <ThemedText
                  style={[
                    styles.streakHeaderText,
                    { color: isDark ? '#FB923C' : '#EA580C' },
                  ]}>
                  {formatStreakDays(tier.streak)}
                </ThemedText>
              </View>
            </View>

            {/* 2. شبكة الأبطال: كل 3 جنب بعض في السطر (Row by row, 3 per row) */}
            <View style={styles.gridWrap}>
              {tier.members.map((entry, mi) => {
                const isAnon = Boolean(entry.is_anonymous && !entry.is_self);
                const isViewer = isViewerEntry(entry);
                const displayName = isAnon
                  ? 'متخفي 🥷'
                  : (entry.person?.name || 'بطل');
                const stepsCount =
                  isViewer && viewerTodaySteps != null && viewerTodaySteps > 0
                    ? Math.max(entry.total_steps ?? 0, viewerTodaySteps)
                    : (entry.total_steps ?? 0);
                const isCompleted = Boolean(entry.is_today_completed || (isViewer && stepsCount >= 10000));

                return (
                  <TouchableOpacity
                    key={entry.person_id ? 'm_' + tierIndex + '_' + entry.person_id : 'm_' + tierIndex + '_anon_' + mi}
                    activeOpacity={isAnon ? 1 : 0.75}
                    disabled={isAnon}
                    onPress={() => handlePress(entry)}
                    style={[
                      styles.cardCol3,
                      {
                        backgroundColor: isViewer
                          ? (isDark ? '#2B170B' : '#FFF7ED')
                          : (isDark ? meta.bgDark : meta.bgLight),
                        borderColor: isViewer
                          ? '#EA580C'
                          : (isDark ? meta.borderDark : meta.borderLight),
                      },
                      isViewer && styles.viewerBorderHighlight,
                    ]}>
                    {/* أفاتار البطل */}
                    {renderAvatar(entry, 38, isDark ? meta.accentDark : meta.accentColor)}

                    {/* اسم البطل */}
                    <ThemedText
                      numberOfLines={2}
                      style={[
                        styles.cardName,
                        {
                          color: isViewer
                            ? (isDark ? '#FB923C' : '#EA580C')
                            : colors.text,
                        },
                      ]}>
                      {displayName}
                    </ThemedText>

                    {isViewer && (
                      <ThemedText style={styles.cardViewerTag} numberOfLines={1}>
                        (أنت ⭐)
                      </ThemedText>
                    )}

                    {/* عدد الخطوات لليوم */}
                    <View style={styles.cardStepsRow}>
                      <ThemedText style={{ fontSize: 10 }}>👟</ThemedText>
                      <ThemedText
                        numberOfLines={1}
                        style={[
                          styles.cardStepsText,
                          { color: isDark ? '#E2E8F0' : '#334155' },
                        ]}>
                        {formatSteps(stepsCount)}
                      </ThemedText>
                    </View>

                    {/* شارة أنجز اليوم (فقط عند الإنجاز — لا توجد كلمة قيد الانتظار أو قيد الإنجاز) */}
                    {isCompleted ? (
                      <View style={styles.completedTag}>
                        <ThemedText style={styles.completedTagText}>
                          ✅ أنجز اليوم
                        </ThemedText>
                      </View>
                    ) : null}
                  </TouchableOpacity>
                );
              })}
            </View>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 14,
    marginVertical: 4,
  },

  /* ── Tier Section ── */
  tierSection: {
    gap: 8,
  },
  tierHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 4,
  },
  tierTitleGroup: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  rankBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    paddingHorizontal: 8,
    paddingVertical: 3.5,
    borderRadius: 10,
    borderWidth: 1,
  },
  rankMedal: {
    fontSize: 13,
  },
  rankNumber: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 12.5,
  },
  tierTitleText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
  },
  tierCountText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11.5,
  },
  streakHeaderBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 10,
    borderWidth: 1,
  },
  streakHeaderText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },

  /* ── 3-Column Grid (كل ثلاثة جنب بعض) ── */
  gridWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 7,
    justifyContent: 'flex-start',
  },
  cardCol3: {
    width: '31.4%',
    alignItems: 'center',
    paddingVertical: 9,
    paddingHorizontal: 4,
    borderRadius: 14,
    borderWidth: 1.2,
    gap: 4,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.04,
        shadowRadius: 3,
      },
      android: { elevation: 1 },
    }),
  },
  cardName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10.5,
    lineHeight: 13.5,
    textAlign: 'center',
    width: '100%',
    marginTop: 2,
  },
  cardViewerTag: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9,
    color: '#EA580C',
    marginTop: -2,
  },
  cardStepsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    justifyContent: 'center',
  },
  cardStepsText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    textAlign: 'center',
  },
  completedTag: {
    backgroundColor: 'rgba(34, 197, 94, 0.16)',
    borderColor: '#22C55E',
    borderWidth: 0.8,
    borderRadius: 6,
    paddingHorizontal: 5,
    paddingVertical: 2,
    marginTop: 1,
  },
  completedTagText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9,
    color: '#16A34A',
    textAlign: 'center',
  },

  /* ── Common Elements ── */
  viewerBorderHighlight: {
    borderWidth: 1.8,
  },
  avatarRing: {
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 1.5,
    position: 'relative',
  },
  avatarFallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarFallbackText: {
    fontFamily: 'Tajawal-ExtraBold',
  },
  todayDot: {
    position: 'absolute',
    bottom: -1,
    right: -1,
    borderWidth: 1.5,
  },
});
