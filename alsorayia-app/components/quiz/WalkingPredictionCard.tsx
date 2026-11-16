import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { WEB_BASE_URL } from '@/lib/api/alsorayiaClient';
import type {
  PredictionStandingRow,
  QuizQuestion,
  WalkingPredictionData,
} from '@/lib/api/quiz';
import { toEnglishDigits } from '@/lib/walking/formatters';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React, { useMemo } from 'react';
import { Platform, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

function resolveAvatarUri(raw?: string | null, gender?: string | null): string {
  const isFemale = gender === 'female';
  const defaultUrl = `${WEB_BASE_URL}/assets/img/${isFemale ? 'avatar-female-hejap-1.png' : 'avatar-male.png'}`;

  if (!raw || typeof raw !== 'string' || !raw.trim()) {
    return defaultUrl;
  }
  const u = raw.trim();
  if (/^https?:\/\//i.test(u)) {
    if (u.includes('localhost') || u.includes('127.0.0.1')) {
      return u.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, WEB_BASE_URL);
    }
    return u;
  }
  const base = WEB_BASE_URL.replace(/\/$/, '');
  return `${base}/${u.replace(/^\//, '')}`;
}

interface WalkingPredictionCardProps {
  question: QuizQuestion;
  colors: any;
  onRefresh?: () => void;
}

export default function WalkingPredictionCard({
  question,
  colors,
  onRefresh,
}: WalkingPredictionCardProps) {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const predData: WalkingPredictionData | undefined | null = question.prediction_data;
  const isEnded = Boolean(predData?.is_ended);
  const viewerStanding: PredictionStandingRow | null = predData?.viewer_standing ?? null;
  const viewerPersonId = viewerStanding?.person_id;

  const viewerEffectivePts = Number(
    viewerStanding?.total_points ??
    viewerStanding?.projected_points ??
    viewerStanding?.actual_points ??
    0
  );
  const viewerHasVotedWithZero = Boolean(viewerStanding && viewerEffectivePts === 0);

  // ══════════════════════════════════════════════════════════════
  // حساب الترتيب المتتالي (Dense Ranking):
  // تصفية أصحاب الصفر (الفعلية والمتوقعة = 0)؛ لا يظهر إلا من رصيده >= 1 نقطة
  // التعادل في النقاط يمنح نفس المركز (الرابع والرابع / الخامس والخامس)
  // ══════════════════════════════════════════════════════════════
  const { topWinners, contenders } = useMemo(() => {
    if (!predData) {
      return { topWinners: [] as PredictionStandingRow[], contenders: [] as PredictionStandingRow[] };
    }

    const rawRows: PredictionStandingRow[] =
      Array.isArray(predData.standings) && predData.standings.length > 0
        ? [...predData.standings]
        : [...(predData.top_standings ?? []), ...(predData.contenders ?? [])];

    // استبعاد أصحاب الصفر تماماً: لا يظهر في المنصة أو الجدول إلا من لديه نقطة واحدة على الأقل (>= 1)
    const allRows = rawRows.filter((r) => {
      const pts = Number(r.total_points ?? r.projected_points ?? r.actual_points ?? 0);
      return pts > 0;
    });

    // فرز المشاركين بالنقاط تنازلياً ثم المطابقات
    allRows.sort((a, b) => {
      const ptsA = Number(a.total_points ?? a.projected_points ?? a.actual_points ?? 0);
      const ptsB = Number(b.total_points ?? b.projected_points ?? b.actual_points ?? 0);
      if (ptsB !== ptsA) return ptsB - ptsA;

      const mA = Number(a.matches_count ?? 0);
      const mB = Number(b.matches_count ?? 0);
      if (mB !== mA) return mB - mA;

      return (a.answer_id ?? 0) - (b.answer_id ?? 0);
    });

    let denseRank = 0;
    let prevPoints: number | null = null;

    const denseRanked: PredictionStandingRow[] = allRows.map((row) => {
      const pts = Number(row.total_points ?? row.projected_points ?? row.actual_points ?? 0);
      if (prevPoints === null || pts !== prevPoints) {
        denseRank++;
        prevPoints = pts;
      }
      return { ...row, rank: denseRank, total_points: pts };
    });

    // المراكز الثلاثة الأولى للمنصة (أول 3 مراكز، بحد أقصى 4 متسابقين في حال التعادل بالمركز 3)
    const podiumCount = 3;
    let winners = denseRanked.filter((r) => r.rank >= 1 && r.rank <= podiumCount);
    if (winners.length > 4) {
      winners = winners.slice(0, podiumCount);
    }

    const winnerIds = new Set(winners.map((w) => w.person_id).filter(Boolean));
    const rest = denseRanked.filter((r) => !r.person_id || !winnerIds.has(r.person_id));

    return { topWinners: winners, contenders: rest };
  }, [predData]);

  if (!predData) return null;

  const totalParticipantsCount = topWinners.length + contenders.length;

  const handlePressParticipant = (personId?: number | null) => {
    if (!personId) return;
    router.push(`/(app)/person/${personId}` as any);
  };

  return (
    <View style={styles.container}>
      {/* ══════════════════════════════════════════════════════
           هيدر الترتيب وحالة التحديث
           ══════════════════════════════════════════════════════ */}
      <View style={styles.leaderboardSection}>
        <View style={styles.sectionHeaderRow}>
          <View style={styles.sectionHeaderLeft}>
            <View style={styles.trophyIconWrap}>
              <IconSymbol name="trophy.fill" size={14} color="#059669" />
            </View>
            <View>
              <ThemedText style={styles.sectionHeaderTitle}>ترتيب أبطال التوقعات</ThemedText>
            </View>
          </View>
        </View>

        {totalParticipantsCount === 0 ? (
          <View style={[styles.emptyList, { borderColor: colors.border }]}>
            <ThemedText style={[styles.emptyListText, { color: colors.icon }]}>
              المنافسة مشتعلة وبانتظار المتصدرين! سجل توقعاتك لتتصدر المراكز الأولى 🏆
            </ThemedText>
          </View>
        ) : (
          <View style={styles.podiumWrapper}>
            {/* ══════════════════════════════════════════════════════
                 1. منصة التتويج الهرمية للمتصدرين (المراكز الثلاثة الأولى)
                 ══════════════════════════════════════════════════════ */}
            {topWinners.length > 0 && (
              <View style={styles.podiumContainer}>
                {topWinners.map((row) => {
                  const rank = row.rank;
                  const isMe = Boolean(
                    row.is_current_user ||
                    row.is_viewer ||
                    (viewerPersonId && row.person_id === viewerPersonId)
                  );
                  const pts = Number(row.total_points ?? 0);
                  const avatarUri = resolveAvatarUri(row.photo_url || (row as any).avatar, row.gender);

                  let pillarHeight = 84;
                  let avatarSize = 52;
                  let ringColor = '#f59e0b';
                  let ringWidth = 2.5;
                  let gradientColors: [string, string, ...string[]] = ['#fef3c7', '#f59e0b', '#d97706'];
                  let rankNumColor = '#78350f';
                  let rankFontSize = 22;

                  if (rank === 1) {
                    pillarHeight = 84;
                    avatarSize = 52;
                    ringColor = '#f59e0b';
                    ringWidth = 2.5;
                    gradientColors = ['#fef3c7', '#f59e0b', '#d97706'];
                    rankNumColor = '#78350f';
                    rankFontSize = 22;
                  } else if (rank === 2) {
                    pillarHeight = 60;
                    avatarSize = 46;
                    ringColor = '#94a3b8';
                    ringWidth = 2;
                    gradientColors = ['#f1f5f9', '#cbd5e1', '#94a3b8'];
                    rankNumColor = '#1e293b';
                    rankFontSize = 18;
                  } else {
                    pillarHeight = 44;
                    avatarSize = 42;
                    ringColor = '#ea580c';
                    ringWidth = 2;
                    gradientColors = ['#ffedd5', '#fed7aa', '#ea580c'];
                    rankNumColor = '#7c2d12';
                    rankFontSize = 16;
                  }

                  const displayName = row.name || (row as any).full_name || 'متسابق';

                  return (
                    <View key={row.person_id ? `top-w-${row.person_id}` : `top-idx-${rank}`} style={styles.podiumColumn}>
                      <TouchableOpacity
                        activeOpacity={0.75}
                        disabled={!row.person_id}
                        onPress={() => handlePressParticipant(row.person_id)}
                        style={styles.podiumUserClickable}
                      >
                        <View style={styles.avatarHolder}>
                          <View
                            style={[
                              styles.avatarRing,
                              {
                                width: avatarSize + 6,
                                height: avatarSize + 6,
                                borderRadius: (avatarSize + 6) / 2,
                                borderColor: isMe ? '#10b981' : ringColor,
                                borderWidth: ringWidth,
                              },
                            ]}
                          >
                            <Image
                              source={{ uri: avatarUri }}
                              style={{
                                width: avatarSize,
                                height: avatarSize,
                                borderRadius: avatarSize / 2,
                              }}
                              contentFit="cover"
                              transition={200}
                              cachePolicy="memory-disk"
                            />
                          </View>

                          {isMe && (
                            <View style={styles.mePill}>
                              <ThemedText style={styles.mePillText}>أنت</ThemedText>
                            </View>
                          )}
                        </View>

                        <ThemedText
                          numberOfLines={2}
                          style={[
                            styles.podiumPersonName,
                            { color: colors.text },
                            rank === 1 && styles.podiumPersonNameFirst,
                            isMe && { color: '#059669', fontFamily: 'Tajawal-ExtraBold' },
                          ]}
                        >
                          {displayName}
                        </ThemedText>
                      </TouchableOpacity>

                      {/* شارة النقاط */}
                      <View style={styles.podiumActualBadge}>
                        <ThemedText style={styles.podiumActualStar}>⭐</ThemedText>
                        <ThemedText style={styles.podiumActualNum}>{toEnglishDigits(pts)}</ThemedText>
                        <ThemedText style={styles.podiumActualLabel}>نقاط</ThemedText>
                      </View>

                      {/* عمود المنصة */}
                      <LinearGradient
                        colors={gradientColors}
                        style={[styles.podiumBase, { height: pillarHeight }]}
                        start={{ x: 0, y: 0 }}
                        end={{ x: 0, y: 1 }}
                      >
                        <ThemedText
                          style={[
                            styles.podiumRankNumber,
                            { color: rankNumColor, fontSize: rankFontSize },
                          ]}
                        >
                          {toEnglishDigits(rank)}
                        </ThemedText>
                      </LinearGradient>
                    </View>
                  );
                })}
              </View>
            )}

            {/* ══════════════════════════════════════════════════════
                 2. جدول المنافسين على الصدارة (الترتيب المتتالي Dense Ranking)
                 ══════════════════════════════════════════════════════ */}
            {contenders.length > 0 && (
              <View style={styles.contendersSection}>
                <View style={styles.contendersHeader}>
                  <View style={styles.contendersHeaderTitleWrap}>
                    <IconSymbol name="list.number" size={11} color="#059669" />
                    <ThemedText style={styles.contendersHeaderTitle}>المنافسون على الصدارة</ThemedText>
                    <ThemedText style={styles.contendersHeaderCount}>
                      ({toEnglishDigits(contenders.length)})
                    </ThemedText>
                  </View>
                  <ThemedText style={styles.contendersHeaderCols}>النقاط</ThemedText>
                </View>

                <ScrollView
                  style={styles.contendersScroll}
                  contentContainerStyle={styles.contendersList}
                  nestedScrollEnabled
                  showsVerticalScrollIndicator
                  scrollEventThrottle={16}
                >
                  {contenders.map((c, cIdx) => {
                    const cRank = c.rank;
                    const cIsMe = Boolean(
                      c.is_current_user ||
                      c.is_viewer ||
                      (viewerPersonId && c.person_id === viewerPersonId)
                    );
                    const cPts = Number(c.total_points ?? 0);
                    const cAvatarUri = resolveAvatarUri(c.photo_url || (c as any).avatar, c.gender);
                    const cDisplayName = c.name || (c as any).full_name || 'متسابق';

                    return (
                      <View
                        key={c.person_id ? `contender-p-${c.person_id}` : `contender-idx-${cIdx}`}
                        style={[
                          styles.contenderRow,
                          {
                            backgroundColor: cIsMe
                              ? 'rgba(16, 185, 129, 0.08)'
                              : isDark
                                ? 'rgba(255,255,255,0.03)'
                                : 'rgba(0,0,0,0.02)',
                            borderColor: cIsMe
                              ? '#10b981'
                              : isDark
                                ? 'rgba(255,255,255,0.06)'
                                : 'rgba(0,0,0,0.04)',
                          },
                        ]}
                      >
                        {/* اليمين: الترتيب + الصورة + الاسم */}
                        <TouchableOpacity
                          activeOpacity={0.7}
                          disabled={!c.person_id}
                          onPress={() => handlePressParticipant(c.person_id)}
                          style={styles.contenderRightSide}
                        >
                          <View
                            style={[
                              styles.contenderRankBadge,
                              cIsMe && styles.contenderRankBadgeMe,
                              cRank === 0 && { backgroundColor: isDark ? '#334155' : '#e2e8f0', width: 28 },
                            ]}
                          >
                            <ThemedText
                              style={[
                                styles.contenderRankText,
                                cIsMe && styles.contenderRankTextMe,
                                cRank === 0 && { fontSize: 8.5 },
                              ]}
                            >
                              {cRank > 0 ? toEnglishDigits(cRank) : '⏳ 0'}
                            </ThemedText>
                          </View>

                          <View style={styles.contenderAvatarWrap}>
                            <Image
                              source={{ uri: cAvatarUri }}
                              style={styles.contenderAvatarImg}
                              contentFit="cover"
                              transition={200}
                              cachePolicy="memory-disk"
                            />
                          </View>

                          <ThemedText
                            numberOfLines={2}
                            style={[
                              styles.contenderName,
                              { color: colors.text },
                              cIsMe && { color: '#065f46', fontFamily: 'Tajawal-ExtraBold' },
                            ]}
                          >
                            {cDisplayName}
                          </ThemedText>

                          {cIsMe && (
                            <View style={styles.mePillSmall}>
                              <ThemedText style={styles.mePillSmallText}>أنت</ThemedText>
                            </View>
                          )}
                        </TouchableOpacity>

                        {/* اليسار: النقاط */}
                        <View style={styles.contenderLeftSide}>
                          {cPts > 0 ? (
                            <View style={styles.contenderActualBadge}>
                              <ThemedText style={styles.contenderActualStar}>⭐</ThemedText>
                              <ThemedText style={styles.contenderActualNum}>
                                {toEnglishDigits(cPts)}
                              </ThemedText>
                              <ThemedText style={styles.contenderActualLabel}>نقاط</ThemedText>
                            </View>
                          ) : (
                            <View style={styles.contenderZeroBadge}>
                              <ThemedText style={styles.contenderZeroText}>0 نقطة</ThemedText>
                            </View>
                          )}
                        </View>
                      </View>
                    );
                  })}
                </ScrollView>
              </View>
            )}
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginTop: 6,
    gap: 8,
  },

  /* ── Header ── */
  leaderboardSection: {
    gap: 8,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  sectionHeaderLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  trophyIconWrap: {
    width: 28,
    height: 28,
    borderRadius: 8,
    backgroundColor: '#d1fae5',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sectionHeaderTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  sectionHeaderSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    color: '#64748b',
  },
  refreshBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#ecfdf5',
    borderWidth: 1,
    borderColor: '#a7f3d0',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
  },
  refreshBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
    color: '#059669',
  },
  emptyList: {
    paddingVertical: 20,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 12,
    borderWidth: 1,
    borderStyle: 'dashed',
  },
  emptyListText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    textAlign: 'center',
    paddingHorizontal: 16,
  },

  /* ── 1. Stepped Podium ── */
  podiumWrapper: {
    width: '100%',
  },
  podiumContainer: {
    flexDirection: 'row-reverse',
    alignItems: 'flex-end',
    justifyContent: 'center',
    gap: 6,
    width: '100%',
    paddingTop: 8,
    marginBottom: 8,
  },
  podiumColumn: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'flex-end',
    minWidth: 0,
  },
  avatarHolder: {
    position: 'relative',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  avatarRing: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ffffff',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 3,
      },
      android: {
        elevation: 2,
      },
    }),
  },
  mePill: {
    position: 'absolute',
    bottom: -4,
    backgroundColor: '#10b981',
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: 8,
  },
  mePillText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 8,
    color: '#ffffff',
  },
  podiumPersonName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9.5,
    lineHeight: 12.5,
    textAlign: 'center',
    maxWidth: '96%',
    marginBottom: 2,
  },
  podiumPersonNameFirst: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 10.5,
    lineHeight: 13.5,
  },
  podiumUserClickable: {
    alignItems: 'center',
    width: '100%',
  },
  podiumActualBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
    backgroundColor: '#fef3c7',
    borderWidth: 1,
    borderColor: '#fde68a',
    paddingHorizontal: 5,
    paddingVertical: 1.5,
    borderRadius: 7,
    marginBottom: 3,
  },
  podiumActualStar: {
    fontSize: 9,
  },
  podiumActualNum: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 10.5,
    color: '#92400e',
    lineHeight: 13,
  },
  podiumActualLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 8,
    color: '#b45309',
    lineHeight: 12,
  },
  podiumBase: {
    width: '100%',
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 2,
  },
  podiumRankNumber: {
    fontFamily: 'Tajawal-ExtraBold',
    lineHeight: 24,
  },

  /* ── 2. Contenders Table ── */
  contendersSection: {
    width: '100%',
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
    paddingTop: 8,
    marginTop: 2,
  },
  contendersHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 6,
    paddingHorizontal: 2,
    marginBottom: 4,
  },
  contendersHeaderTitleWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    flexShrink: 1,
  },
  contendersHeaderTitle: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 10.5,
    color: '#475569',
  },
  contendersHeaderCount: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9,
    color: '#94a3b8',
  },
  contendersHeaderCols: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 8.5,
    color: '#94a3b8',
  },
  contendersScroll: {
    maxHeight: 290,
    width: '100%',
  },
  contendersList: {
    gap: 4,
    paddingBottom: 4,
  },
  contenderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 5,
    paddingHorizontal: 8,
    borderRadius: 10,
    borderWidth: 1,
  },
  contenderRightSide: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flex: 1,
    minWidth: 0,
  },
  contenderRankBadge: {
    width: 22,
    height: 22,
    borderRadius: 6,
    backgroundColor: '#f1f5f9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  contenderRankBadgeMe: {
    backgroundColor: '#10b981',
  },
  contenderRankText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
    color: '#475569',
  },
  contenderRankTextMe: {
    color: '#ffffff',
  },
  contenderAvatarWrap: {
    width: 28,
    height: 28,
    borderRadius: 14,
    overflow: 'hidden',
  },
  contenderAvatarImg: {
    width: 28,
    height: 28,
    borderRadius: 14,
  },
  contenderName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10.5,
    lineHeight: 14,
    flexShrink: 1,
  },
  mePillSmall: {
    backgroundColor: '#10b981',
    paddingHorizontal: 4,
    paddingVertical: 1,
    borderRadius: 6,
  },
  mePillSmallText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 7.5,
    color: '#ffffff',
  },
  contenderLeftSide: {
    flexDirection: 'row',
    alignItems: 'center',
    flexShrink: 0,
  },
  contenderActualBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
    backgroundColor: '#fef3c7',
    borderWidth: 1,
    borderColor: '#fde68a',
    paddingHorizontal: 5,
    paddingVertical: 1.5,
    borderRadius: 6,
  },
  contenderActualStar: {
    fontSize: 8.5,
  },
  contenderActualNum: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 10,
    color: '#92400e',
  },
  contenderActualLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 8,
    color: '#b45309',
  },
  contenderZeroBadge: {
    backgroundColor: '#f1f5f9',
    borderWidth: 1,
    borderColor: '#cbd5e1',
    paddingHorizontal: 5,
    paddingVertical: 1.5,
    borderRadius: 6,
  },
  contenderZeroText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 8.5,
    color: '#64748b',
  },
});
