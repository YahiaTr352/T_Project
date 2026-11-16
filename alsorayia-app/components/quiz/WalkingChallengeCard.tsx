import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { WalkingRaceLauncher } from '@/components/quiz/WalkingRaceModal';
import { WEB_BASE_URL } from '@/lib/api/alsorayiaClient';
import type { QuizQuestion, WalkingChallengeData, WalkingStandingRow } from '@/lib/api/quiz';
import { formatSteps, toEnglishDigits } from '@/lib/walking/formatters';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React from 'react';
import { Platform, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

function resolveAvatarUri(raw?: string | null, gender?: string | null, isAnonymous?: boolean): string {
  const isFemale = gender === 'female';
  const defaultUrl = isAnonymous
    ? `${WEB_BASE_URL}/assets/img/${isFemale ? 'avatar-anonymous-female.png' : 'avatar-anonymous-male.png'}`
    : `${WEB_BASE_URL}/assets/img/${isFemale ? 'avatar-female-hejap-1.png' : 'avatar-male.png'}`;

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

interface WalkingChallengeCardProps {
  question: QuizQuestion;
  colors: any;
  onRefresh?: () => void;
}

export default function WalkingChallengeCard({
  question,
  colors,
  onRefresh,
}: WalkingChallengeCardProps) {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  const walkingData: WalkingChallengeData | undefined | null = question.walking_data;
  const settings = walkingData?.settings;
  const topStandings = walkingData?.top_standings ?? [];

  const scoringMethod = walkingData?.scoring_method || settings?.scoring_method || 'daily_points';
  const isDailyPoints = scoringMethod === 'daily_rank_points' || scoringMethod === 'daily_points';
  // المنصة تعرض أعلى 5 مراكز دائماً (مستقل عن عدد الفائزين)، والباقي في الجدول.
  const podiumCount = Math.max(1, walkingData?.podium_top_count ?? 5);
  const tableRowsCount = Math.max(1, walkingData?.table_rows_count ?? 25);

  // Separate top winners for the stepped pyramid podium from contenders (vertical list under)
  const topWinners = topStandings.filter((r) => r.rank <= podiumCount);
  // عرض جميع المتنافسين خلف المنصة الحاصلين على نقاط (محتسبة أو متوقعة > 0) دون أي سقف عددي
  const contenders = topStandings.filter((r) => {
    if (r.rank <= podiumCount) return false;
    const actual = Number((r as any).total_points ?? (r as any).actual_points ?? (r as any).points ?? 0);
    const proj = Number((r as any).projected_points ?? 0);
    return actual + proj > 0;
  });

  const handlePressParticipant = (personId?: number, isAnon?: boolean) => {
    if (isAnon || !personId) return;
    router.push(`/(app)/person/${personId}` as any);
  };

  // Order from Left to Right: 1 on extreme, 2, 3...
  const podiumRanks: number[] = Array.from({ length: podiumCount }, (_, i) => i + 1);

  const winnerByRank = new Map<number, WalkingStandingRow>(
    topWinners.map((w) => [w.rank, w])
  );

  const hasRaceEnabled = Boolean(walkingData?.walking_race_config?.enabled);

  return (
    <View style={styles.container}>
      {/* Leaderboard Section */}
      <View style={styles.leaderboardSection}>
        {(onRefresh || hasRaceEnabled) && (
          <View style={styles.refreshRow}>
            {hasRaceEnabled ? (
              <WalkingRaceLauncher
                questionId={question.id}
                raceConfig={walkingData?.walking_race_config}
                compact
              />
            ) : (
              <View />
            )}
            {onRefresh && (
              <TouchableOpacity onPress={onRefresh} style={styles.refreshBtn} activeOpacity={0.7}>
                <IconSymbol name="arrow.clockwise" size={11} color="#059669" />
                <ThemedText style={styles.refreshBtnText}>تحديث الترتيب</ThemedText>
              </TouchableOpacity>
            )}
          </View>
        )}

        {topWinners.length === 0 ? (
          <View style={[styles.emptyList, { borderColor: colors.border }]}>
            <ThemedText style={[styles.emptyListText, { color: colors.icon }]}>
              المنافسة مشتعلة وبانتظار المتصدرين! سجل خطواتك لتتصدر المراكز الأولى 🏆
            </ThemedText>
          </View>
        ) : (
          <View style={styles.podiumWrapper}>
            {/* ══════════════════════════════════════════════════════
                 2. منصة التتويج الهرمية للمتصدرين
                 المركز الأول في الارتفاع الأعلى ونقاطه المحتسبة والمتوقعة
                 ══════════════════════════════════════════════════════ */}
            <View style={styles.podiumContainer}>
              {podiumRanks.map((r) => {
                const row = winnerByRank.get(r);
                if (!row) return null;

                const rank = row.rank;
                const isMe = Boolean(row.is_current_user);
                const isAnon = Boolean(row.is_anonymous && !isMe);
                const todaySteps = Number((row as any).today_steps ?? (row as any).steps ?? row.total_steps ?? 0);
                const actualPts = Number((row as any).actual_points ?? (row as any).daily_points ?? (row as any).actual_score ?? 0);
                const projPts = Number((row as any).projected_points ?? (row as any).projected_score ?? 0);
                const avatarUri = resolveAvatarUri((row as any).photo_url || (row as any).avatar, row.gender, isAnon);

                // Stepped pillar height, avatar size and colors per rank
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
                } else if (rank === 3) {
                  pillarHeight = 44;
                  avatarSize = 42;
                  ringColor = '#ea580c';
                  ringWidth = 2;
                  gradientColors = ['#ffedd5', '#fed7aa', '#ea580c'];
                  rankNumColor = '#7c2d12';
                  rankFontSize = 16;
                } else if (rank === 4) {
                  pillarHeight = 32;
                  avatarSize = 36;
                  ringColor = '#10b981';
                  ringWidth = 1.5;
                  gradientColors = ['#d1fae5', '#6ee7b7', '#059669'];
                  rankNumColor = '#064e3b';
                  rankFontSize = 14;
                } else {
                  pillarHeight = 24;
                  avatarSize = 34;
                  ringColor = '#14b8a6';
                  ringWidth = 1.5;
                  gradientColors = ['#ccfbf1', '#5eead4', '#0d9488'];
                  rankNumColor = '#134e4a';
                  rankFontSize = 13;
                }

                const displayName = isAnon ? 'متخفي' : (row.name || (row as any).full_name || 'متسابق');

                return (
                  <View key={row.person_id || `podium-${rank}`} style={styles.podiumColumn}>
                    {/* Clickable Avatar + Name to View Member Profile */}
                    <TouchableOpacity
                      activeOpacity={isAnon ? 1 : 0.75}
                      disabled={isAnon}
                      onPress={() => handlePressParticipant(row.person_id, isAnon)}
                      style={styles.podiumUserClickable}
                    >
                      {/* Avatar without card/box border */}
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

                      {/* Name */}
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

                    {/* Today's Steps */}
                    <View style={styles.podiumStepsRow}>
                      <ThemedText
                        numberOfLines={1}
                        style={[
                          styles.podiumStepsText,
                          rank === 1 && styles.podiumStepsTextFirst,
                        ]}
                      >
                        {formatSteps(todaySteps)}
                      </ThemedText>
                      <ThemedText style={styles.podiumStepsUnit}>اليوم</ThemedText>
                    </View>

                    {/* Points Distinction: Actual vs Projected Bonus */}
                    {isDailyPoints ? (
                      <View style={styles.podiumPointsCol}>
                        {/* Actual Earned Points */}
                        <View style={styles.podiumActualBadge}>
                          <ThemedText style={styles.podiumActualStar}>⭐</ThemedText>
                          <ThemedText style={styles.podiumActualNum}>{toEnglishDigits(actualPts)}</ThemedText>
                          <ThemedText style={styles.podiumActualLabel}>نقاط</ThemedText>
                        </View>

                        {/* Projected Bonus Points (Rolled at midnight) */}
                        {projPts > 0 ? (
                          <View style={styles.podiumProjBadge}>
                            <ThemedText style={styles.podiumProjNum}>+{toEnglishDigits(projPts)}</ThemedText>
                            <ThemedText style={styles.podiumProjLabel}>متوقعة 🌙</ThemedText>
                          </View>
                        ) : null}
                      </View>
                    ) : Boolean(row.prize) ? (
                      <ThemedText numberOfLines={1} style={styles.podiumPrizeText}>
                        🎁 {toEnglishDigits(row.prize)}
                      </ThemedText>
                    ) : null}

                    {/* Stepped Pillar Base */}
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

            {/* ══════════════════════════════════════════════════════
                 3. جدول المنافسين على الصدارة (25 متسابقاً بعد المنصة)
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
                  <ThemedText style={styles.contendersHeaderCols}>خطوات اليوم / النقاط</ThemedText>
                </View>

                <ScrollView
                  style={styles.contendersScroll}
                  contentContainerStyle={styles.contendersList}
                  nestedScrollEnabled
                  showsVerticalScrollIndicator
                  scrollEventThrottle={16}
                >
                  {contenders.map((c: WalkingStandingRow) => {
                    const cRank = c.rank;
                    const cIsMe = Boolean(c.is_current_user);
                    const cIsAnon = Boolean(c.is_anonymous && !cIsMe);
                    const cTodaySteps = Number((c as any).today_steps ?? (c as any).steps ?? c.total_steps ?? 0);
                    const cActualPts = Number((c as any).actual_points ?? (c as any).daily_points ?? (c as any).actual_score ?? 0);
                    const cProjPts = Number((c as any).projected_points ?? (c as any).projected_score ?? 0);
                    const cAvatarUri = resolveAvatarUri((c as any).photo_url || (c as any).avatar, c.gender, cIsAnon);

                    const cDisplayName = cIsAnon ? 'متسابق متخفي' : (c.name || (c as any).full_name || 'متسابق');

                    return (
                      <View
                        key={c.person_id || `contender-${cRank}`}
                        style={[
                          styles.contenderRow,
                          {
                            backgroundColor: cIsMe ? 'rgba(16, 185, 129, 0.08)' : (isDark ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.02)'),
                            borderColor: cIsMe ? '#10b981' : (isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)'),
                          },
                        ]}
                      >
                        {/* Right: Rank + Avatar + Name (Clickable to profile) */}
                        <TouchableOpacity
                          activeOpacity={cIsAnon ? 1 : 0.7}
                          disabled={cIsAnon}
                          onPress={() => handlePressParticipant(c.person_id, cIsAnon)}
                          style={styles.contenderRightSide}
                        >
                          <View
                            style={[
                              styles.contenderRankBadge,
                              cIsMe && styles.contenderRankBadgeMe,
                            ]}
                          >
                            <ThemedText
                              style={[
                                styles.contenderRankText,
                                cIsMe && styles.contenderRankTextMe,
                              ]}
                            >
                              {toEnglishDigits(cRank)}
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

                        {/* Left: Steps + Points */}
                        <View style={styles.contenderLeftSide}>
                          <View style={styles.contenderStepsWrap}>
                            <ThemedText style={[styles.contenderSteps, cIsMe && { color: '#059669' }]}>
                              {formatSteps(cTodaySteps)}
                            </ThemedText>
                            <ThemedText style={styles.contenderStepsUnit}>اليوم</ThemedText>
                          </View>

                          {isDailyPoints ? (
                            <View style={styles.contenderPointsGroup}>
                              <View style={styles.contenderActualBadge}>
                                <ThemedText style={styles.contenderActualStar}>⭐</ThemedText>
                                <ThemedText style={styles.contenderActualNum}>{toEnglishDigits(cActualPts)}</ThemedText>
                                <ThemedText style={styles.contenderActualLabel}>نقاط</ThemedText>
                              </View>

                              {cProjPts > 0 ? (
                                <View style={styles.contenderProjBadge}>
                                  <ThemedText style={styles.contenderProjNum}>+{toEnglishDigits(cProjPts)}</ThemedText>
                                  <ThemedText style={styles.contenderProjLabel}>متوقعة</ThemedText>
                                </View>
                              ) : null}
                            </View>
                          ) : (
                            Boolean(c.prize) && (
                              <ThemedText numberOfLines={1} style={styles.contenderPrize}>
                                🎁 {toEnglishDigits(c.prize)}
                              </ThemedText>
                            )
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
    width: '100%',
  },
  leaderboardSection: {
    width: '100%',
  },
  refreshRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
    width: '100%',
  },
  refreshBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 4,
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 6,
    backgroundColor: '#ecfdf5',
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
  podiumWrapper: {
    width: '100%',
  },
  podiumContainer: {
    flexDirection: 'row-reverse',
    alignItems: 'flex-end',
    justifyContent: 'center',
    gap: 4,
    width: '100%',
    paddingTop: 8,
    marginBottom: 10,
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
    marginBottom: 1,
  },
  podiumPersonNameFirst: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 10.5,
    lineHeight: 13.5,
  },
  podiumStepsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
    marginBottom: 2,
  },
  podiumStepsText: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 10,
    color: '#059669',
    textAlign: 'center',
  },
  podiumStepsTextFirst: {
    fontSize: 11,
    color: '#047857',
  },
  podiumStepsUnit: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 8,
    color: '#94a3b8',
  },
  podiumUserClickable: {
    alignItems: 'center',
    width: '100%',
  },
  podiumPointsCol: {
    alignItems: 'center',
    gap: 2,
    marginTop: 1,
    marginBottom: 2,
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
  podiumProjBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
    backgroundColor: '#ecfdf5',
    borderWidth: 1,
    borderColor: '#a7f3d0',
    paddingHorizontal: 4,
    paddingVertical: 1,
    borderRadius: 6,
  },
  podiumProjNum: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 9.5,
    color: '#047857',
    lineHeight: 12,
  },
  podiumProjLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 7.5,
    color: '#065f46',
    lineHeight: 11,
  },
  podiumPrizeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 8.5,
    color: '#b45309',
    textAlign: 'center',
    marginBottom: 2,
    maxWidth: '94%',
  },
  podiumBase: {
    width: '100%',
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 3,
  },
  podiumRankNumber: {
    fontFamily: 'Tajawal-ExtraBold',
    lineHeight: 24,
  },
  contendersSection: {
    width: '100%',
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
    paddingTop: 8,
    marginTop: 4,
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
    maxHeight: 400,
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
    width: 20,
    height: 20,
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
    fontSize: 11,
    lineHeight: 14.5,
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
    fontSize: 8,
    color: '#ffffff',
  },
  contenderLeftSide: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  contenderStepsWrap: {
    alignItems: 'flex-end',
  },
  contenderSteps: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 11,
    color: '#334155',
  },
  contenderStepsUnit: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 8,
    color: '#94a3b8',
  },
  contenderPointsGroup: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  contenderActualBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
    backgroundColor: '#fef3c7',
    borderWidth: 1,
    borderColor: '#fde68a',
    paddingHorizontal: 5,
    paddingVertical: 2,
    borderRadius: 7,
  },
  contenderActualStar: {
    fontSize: 8.5,
  },
  contenderActualNum: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 10.5,
    color: '#92400e',
    lineHeight: 13,
  },
  contenderActualLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 8,
    color: '#b45309',
    lineHeight: 12,
  },
  contenderProjBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 1.5,
    backgroundColor: '#ecfdf5',
    borderWidth: 1,
    borderColor: '#a7f3d0',
    paddingHorizontal: 4,
    paddingVertical: 2,
    borderRadius: 6,
  },
  contenderProjNum: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 9.5,
    color: '#047857',
    lineHeight: 12,
  },
  contenderProjLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 7.5,
    color: '#065f46',
    lineHeight: 11,
  },
  contenderPrize: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9,
    color: '#b45309',
    maxWidth: 80,
  },

});
