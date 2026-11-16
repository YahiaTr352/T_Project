import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { WEB_BASE_URL } from '@/lib/api/alsorayiaClient';
import { WalkingRaceLauncher } from '@/components/quiz/WalkingRaceModal';
import type { GroupWalkingStandingRow, QuizQuestion, ViewerGroupStanding, WalkingChallengeData } from '@/lib/api/quiz';
import { formatSteps } from '@/lib/walking/formatters';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import { StyleSheet, TouchableOpacity, View } from 'react-native';

function resolveImageUri(raw?: string | null): string {
  if (!raw || typeof raw !== 'string' || !raw.trim()) {
    return `${WEB_BASE_URL}/assets/img/default-group-cover.png`;
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

interface WalkingGroupBattleCardProps {
  question: QuizQuestion;
  colors: any;
  onRefresh?: () => void;
}

export default function WalkingGroupBattleCard({
  question,
  colors,
  onRefresh,
}: WalkingGroupBattleCardProps) {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const [showMembers, setShowMembers] = useState(false);

  const walkingData: WalkingChallengeData | undefined | null = question.walking_data;
  const standings: GroupWalkingStandingRow[] = walkingData?.groups_standings ?? [];
  const battleMeta = walkingData?.battle_meta;
  const viewerGroup: ViewerGroupStanding | null = walkingData?.viewer_group ?? null;
  const scoringMode = walkingData?.scoring_method || 'total_steps';
  const scoringLabel = walkingData?.scoring_mode_label || 'إجمالي خطوات المجموعة';

  const isHeadToHead = Boolean(battleMeta?.is_head_to_head) && standings.length === 2;
  const groupA = standings[0];
  const groupB = standings.length > 1 ? standings[1] : null;

  const pctA = battleMeta?.group_a_pct ?? 50;
  const pctB = battleMeta?.group_b_pct ?? 50;
  const isALeader = groupA && groupB && groupA.score >= groupB.score;
  const isBLeader = groupA && groupB && groupB.score > groupA.score;

  const navigateToGroup = (groupId: number, openChat = false) => {
    router.push({
      pathname: '/(tabs)/walking',
      params: {
        tab: 'groups',
        groupId: String(groupId),
        openChat: openChat ? '1' : '0',
      },
    } as any);
  };

  if (standings.length === 0) {
    return (
      <View style={[styles.card, { backgroundColor: isDark ? '#0f172a' : '#ffffff', borderColor: colors.border }]}>
        <ThemedText style={styles.emptyText}>لم يتم العثور على مجموعات مشاركة في هذا التحدي بعد.</ThemedText>
      </View>
    );
  }

  return (
    <View style={[styles.card, { backgroundColor: isDark ? '#0f172a' : '#ffffff', borderColor: colors.border }]}>
      {/* ── Header ────────────────────────────────────────────── */}
      <View style={styles.headerRow}>
        <View style={styles.titleBadge}>
          <ThemedText style={styles.titleIcon}>⚔️</ThemedText>
          <View>
            <ThemedText style={styles.titleText}>تحدي المجموعات والفرق</ThemedText>
            <ThemedText style={styles.modeSubText}>{scoringLabel}</ThemedText>
          </View>
        </View>

        {onRefresh && (
          <TouchableOpacity onPress={onRefresh} style={styles.refreshBtn} activeOpacity={0.7}>
            <IconSymbol name="arrow.clockwise" size={13} color="#059669" />
            <ThemedText style={styles.refreshBtnText}>تحديث</ThemedText>
          </TouchableOpacity>
        )}
      </View>

      {walkingData?.walking_race_config?.enabled && (
        <View style={{ marginBottom: 10, alignItems: 'flex-start' }}>
          <WalkingRaceLauncher
            questionId={question.id}
            raceConfig={walkingData.walking_race_config}
            compact
          />
        </View>
      )}

      {/* ── Lead Banner ───────────────────────────────────────── */}
      {Boolean(battleMeta?.lead_text) && (
        <View style={styles.leadBanner}>
          <View style={styles.pulseDot} />
          <ThemedText style={styles.leadBannerText}>{battleMeta?.lead_text}</ThemedText>
        </View>
      )}

      {/* ── Head to Head Arena (2 Groups) ───────────────────────── */}
      {isHeadToHead && groupA && groupB ? (
        <LinearGradient
          colors={['#0f172a', '#064e3b', '#0f172a']}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.arenaContainer}
        >
          <View style={styles.clashRow}>
            {/* Team A */}
            <TouchableOpacity
              style={[styles.teamBox, isALeader && styles.leadingTeamBox]}
              onPress={() => navigateToGroup(groupA.group_id)}
              activeOpacity={0.8}
            >
              <View style={styles.teamCoverWrapper}>
                <Image source={{ uri: resolveImageUri(groupA.cover_image) }} style={styles.teamCover} />
                {isALeader && (
                  <View style={styles.leaderCrownBadge}>
                    <ThemedText style={styles.leaderCrownText}>👑 متصدر</ThemedText>
                  </View>
                )}
              </View>
              <ThemedText style={styles.teamName} numberOfLines={1}>{groupA.name}</ThemedText>
              <ThemedText style={styles.teamMembers}>{groupA.total_members_count} عضو ({groupA.active_members_count} نشط)</ThemedText>
              <View style={styles.scorePill}>
                <ThemedText style={styles.scoreNumber}>{formatSteps(groupA.score)}</ThemedText>
                <ThemedText style={styles.scoreLabel}>
                  {scoringMode === 'daily_group_points' || scoringMode === 'member_matchups' ? 'نقطة' : 'خطوة'}
                </ThemedText>
              </View>
            </TouchableOpacity>

            {/* VS Badge */}
            <View style={styles.vsContainer}>
              <LinearGradient colors={['#f59e0b', '#10b981']} style={styles.vsCircle}>
                <ThemedText style={styles.vsText}>VS</ThemedText>
              </LinearGradient>
            </View>

            {/* Team B */}
            <TouchableOpacity
              style={[styles.teamBox, isBLeader && styles.leadingTeamBox]}
              onPress={() => navigateToGroup(groupB.group_id)}
              activeOpacity={0.8}
            >
              <View style={styles.teamCoverWrapper}>
                <Image source={{ uri: resolveImageUri(groupB.cover_image) }} style={styles.teamCover} />
                {isBLeader && (
                  <View style={styles.leaderCrownBadge}>
                    <ThemedText style={styles.leaderCrownText}>👑 متصدر</ThemedText>
                  </View>
                )}
              </View>
              <ThemedText style={styles.teamName} numberOfLines={1}>{groupB.name}</ThemedText>
              <ThemedText style={styles.teamMembers}>{groupB.total_members_count} عضو ({groupB.active_members_count} نشط)</ThemedText>
              <View style={styles.scorePill}>
                <ThemedText style={styles.scoreNumber}>{formatSteps(groupB.score)}</ThemedText>
                <ThemedText style={styles.scoreLabel}>
                  {scoringMode === 'daily_group_points' || scoringMode === 'member_matchups' ? 'نقطة' : 'خطوة'}
                </ThemedText>
              </View>
            </TouchableOpacity>
          </View>

          {/* Tug of war bar */}
          <View style={styles.tugContainer}>
            <View style={styles.tugLabelsRow}>
              <ThemedText style={styles.tugLabelA}>{groupA.name}: {pctA}%</ThemedText>
              <ThemedText style={styles.tugLabelMid}>مؤشر التقدم</ThemedText>
              <ThemedText style={styles.tugLabelB}>{groupB.name}: {pctB}%</ThemedText>
            </View>
            <View style={styles.tugTrack}>
              <View style={[styles.tugFillA, { width: `${pctA}%` }]} />
              <View style={[styles.tugFillB, { width: `${pctB}%` }]} />
            </View>
          </View>

          {/* MVPs Spotlights */}
          <View style={styles.mvpRow}>
            {groupA.top_member && (
              <View style={styles.mvpItem}>
                <Image source={{ uri: resolveImageUri(groupA.top_member.photo_url) }} style={styles.mvpAvatar} />
                <View style={styles.mvpInfo}>
                  <ThemedText style={styles.mvpBadge}>⭐ نجم {groupA.name}</ThemedText>
                  <ThemedText style={styles.mvpName} numberOfLines={2}>{groupA.top_member.name}</ThemedText>
                </View>
                <ThemedText style={styles.mvpSteps}>{formatSteps(groupA.top_member.steps)}</ThemedText>
              </View>
            )}

            {groupB.top_member && (
              <View style={styles.mvpItem}>
                <Image source={{ uri: resolveImageUri(groupB.top_member.photo_url) }} style={styles.mvpAvatar} />
                <View style={styles.mvpInfo}>
                  <ThemedText style={styles.mvpBadge}>⭐ نجم {groupB.name}</ThemedText>
                  <ThemedText style={styles.mvpName} numberOfLines={2}>{groupB.top_member.name}</ThemedText>
                </View>
                <ThemedText style={styles.mvpSteps}>{formatSteps(groupB.top_member.steps)}</ThemedText>
              </View>
            )}
          </View>
        </LinearGradient>
      ) : (
        /* Multi-Group Leaderboard (3+ Groups) */
        <View style={styles.multiGroupList}>
          {standings.map((grp) => {
            const isLeading = grp.rank === 1;
            return (
              <TouchableOpacity
                key={grp.group_id}
                style={[styles.multiGroupCard, isLeading && styles.multiGroupCardLeader]}
                onPress={() => navigateToGroup(grp.group_id)}
                activeOpacity={0.8}
              >
                <View style={styles.multiGroupLeft}>
                  <View style={[styles.multiRankBadge, isLeading && styles.multiRankBadgeLeader]}>
                    <ThemedText style={[styles.multiRankText, isLeading && styles.multiRankTextLeader]}>
                      {grp.rank}
                    </ThemedText>
                  </View>
                  <Image source={{ uri: resolveImageUri(grp.cover_image) }} style={styles.multiGroupCover} />
                  <View style={styles.multiGroupInfo}>
                    <ThemedText style={styles.multiGroupName} numberOfLines={1}>{grp.name}</ThemedText>
                    <ThemedText style={styles.multiGroupSub}>{grp.total_members_count} عضو</ThemedText>
                  </View>
                </View>

                <View style={styles.multiGroupRight}>
                  <ThemedText style={styles.multiScoreNumber}>{formatSteps(grp.score)}</ThemedText>
                  <ThemedText style={styles.multiScoreLabel}>
                    {scoringMode === 'daily_group_points' || scoringMode === 'member_matchups' ? 'نقطة' : 'خطوة'}
                  </ThemedText>
                </View>
              </TouchableOpacity>
            );
          })}
        </View>
      )}

      {/* ── Viewer Status Banner ───────────────────────────────── */}
      {viewerGroup ? (
        <LinearGradient
          colors={['#059669', '#0d9488']}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={styles.viewerBanner}
        >
          <View style={styles.viewerBannerContent}>
            <ThemedText style={styles.viewerBannerShield}>🛡️</ThemedText>
            <View style={styles.viewerBannerTextGroup}>
              <ThemedText style={styles.viewerBannerTitle}>
                أنت تمثل فريق {viewerGroup.group_name} (المركز {viewerGroup.group_rank})
              </ThemedText>
              <ThemedText style={styles.viewerBannerSub}>
                مساهمتك: {formatSteps(viewerGroup.user_steps)} خطوة ({viewerGroup.contribution_pct}%) • ترتيبك {viewerGroup.user_rank_in_group} في فريقك
              </ThemedText>
            </View>
          </View>

          <TouchableOpacity
            style={styles.viewerChatBtn}
            onPress={() => navigateToGroup(viewerGroup.group_id, true)}
            activeOpacity={0.8}
          >
            <ThemedText style={styles.viewerChatBtnText}>شات الفريق 💬</ThemedText>
          </TouchableOpacity>
        </LinearGradient>
      ) : (
        <View style={styles.notInGroupAlert}>
          <IconSymbol name="info.circle.fill" size={13} color="#059669" />
          <ThemedText style={styles.notInGroupAlertText}>
            هذا التحدي يجمع أعضاء الجروبات المتنافسة! انضم إلى أحد الجروبات للمشاركة في التحدي القادم 👥
          </ThemedText>
        </View>
      )}

      {/* ── Member Contributions Collapsible ───────────────────── */}
      <TouchableOpacity
        style={styles.expandBtn}
        onPress={() => setShowMembers(!showMembers)}
        activeOpacity={0.7}
      >
        <ThemedText style={styles.expandBtnText}>
          {showMembers ? 'إخفاء مساهمات أعضاء الفرق ▲' : 'عرض تفاصيل مساهمات أعضاء الفرق ▼'}
        </ThemedText>
      </TouchableOpacity>

      {showMembers && (
        <View style={styles.membersBreakdownContainer}>
          {standings.map((team) => (
            <View key={`breakdown-${team.group_id}`} style={styles.teamBreakdownBox}>
              <ThemedText style={styles.teamBreakdownTitle}>أبرز مساهمي {team.name}</ThemedText>
              <View style={styles.breakdownList}>
                {(team.members_breakdown ?? []).map((mb: any, idx: number) => (
                  <View
                    key={`mb-${team.group_id}-${mb.person_id}`}
                    style={[styles.breakdownItem, mb.is_viewer && styles.breakdownItemViewer]}
                  >
                    <ThemedText style={styles.breakdownRank}>#{idx + 1}</ThemedText>
                    <Image source={{ uri: resolveImageUri(mb.photo_url) }} style={styles.breakdownAvatar} />
                    <ThemedText style={styles.breakdownName} numberOfLines={2}>
                      {mb.name} {mb.is_viewer ? '(أنت)' : ''}
                    </ThemedText>
                    <ThemedText style={styles.breakdownSteps}>{formatSteps(mb.steps)}</ThemedText>
                  </View>
                ))}
              </View>
            </View>
          ))}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 14,
    marginBottom: 16,
  },
  emptyText: {
    fontSize: 13,
    color: '#64748b',
    textAlign: 'center',
    paddingVertical: 16,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  titleBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  titleIcon: {
    fontSize: 22,
  },
  titleText: {
    fontSize: 15,
    fontWeight: '800',
  },
  modeSubText: {
    fontSize: 11,
    color: '#059669',
    fontWeight: '700',
  },
  refreshBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#ecfdf5',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  refreshBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#059669',
  },
  leadBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#ecfdf5',
    borderWidth: 1,
    borderColor: '#a7f3d0',
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 6,
    marginBottom: 12,
  },
  pulseDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#10b981',
  },
  leadBannerText: {
    fontSize: 12,
    fontWeight: '800',
    color: '#065f46',
  },
  arenaContainer: {
    borderRadius: 18,
    padding: 14,
    marginBottom: 12,
  },
  clashRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  teamBox: {
    flex: 1,
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 14,
    padding: 10,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
  },
  leadingTeamBox: {
    backgroundColor: 'rgba(245, 158, 11, 0.12)',
    borderColor: '#f59e0b',
  },
  teamCoverWrapper: {
    position: 'relative',
    marginBottom: 6,
  },
  teamCover: {
    width: 58,
    height: 58,
    borderRadius: 14,
  },
  leaderCrownBadge: {
    position: 'absolute',
    top: -6,
    right: -6,
    backgroundColor: '#f59e0b',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 8,
  },
  leaderCrownText: {
    fontSize: 9,
    fontWeight: '900',
    color: '#78350f',
  },
  teamName: {
    fontSize: 13,
    fontWeight: '800',
    color: '#ffffff',
    marginBottom: 2,
  },
  teamMembers: {
    fontSize: 10,
    color: '#94a3b8',
    marginBottom: 6,
  },
  scorePill: {
    backgroundColor: 'rgba(0,0,0,0.3)',
    borderRadius: 10,
    paddingHorizontal: 8,
    paddingVertical: 4,
    alignItems: 'center',
    width: '100%',
  },
  scoreNumber: {
    fontSize: 15,
    fontWeight: '900',
    color: '#fef08a',
  },
  scoreLabel: {
    fontSize: 9,
    color: '#cbd5e1',
    fontWeight: '600',
  },
  vsContainer: {
    width: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  vsCircle: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#10b981',
    shadowOpacity: 0.5,
    shadowRadius: 6,
    elevation: 3,
  },
  vsText: {
    fontSize: 11,
    fontWeight: '900',
    color: '#0f172a',
  },
  tugContainer: {
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255,255,255,0.1)',
  },
  tugLabelsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  tugLabelA: {
    fontSize: 11,
    fontWeight: '800',
    color: '#34d399',
  },
  tugLabelMid: {
    fontSize: 9,
    fontWeight: '700',
    color: '#94a3b8',
  },
  tugLabelB: {
    fontSize: 11,
    fontWeight: '800',
    color: '#2dd4bf',
  },
  tugTrack: {
    height: 10,
    borderRadius: 5,
    backgroundColor: 'rgba(255,255,255,0.15)',
    flexDirection: 'row',
    overflow: 'hidden',
  },
  tugFillA: {
    height: '100%',
    backgroundColor: '#10b981',
  },
  tugFillB: {
    height: '100%',
    backgroundColor: '#0d9488',
  },
  mvpRow: {
    marginTop: 10,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255,255,255,0.1)',
    gap: 6,
  },
  mvpItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.06)',
    padding: 6,
    borderRadius: 10,
    gap: 8,
  },
  mvpAvatar: {
    width: 26,
    height: 26,
    borderRadius: 13,
  },
  mvpInfo: {
    flex: 1,
  },
  mvpBadge: {
    fontSize: 9,
    fontWeight: '700',
    color: '#34d399',
  },
  mvpName: {
    fontSize: 10.5,
    lineHeight: 13.5,
    fontWeight: '800',
    color: '#ffffff',
  },
  mvpSteps: {
    fontSize: 12,
    fontWeight: '900',
    color: '#fef08a',
  },
  multiGroupList: {
    gap: 8,
    marginBottom: 12,
  },
  multiGroupCard: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 10,
    borderRadius: 14,
    backgroundColor: 'rgba(0,0,0,0.03)',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  multiGroupCardLeader: {
    backgroundColor: '#fefce8',
    borderColor: '#fde047',
  },
  multiGroupLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  multiRankBadge: {
    width: 24,
    height: 24,
    borderRadius: 6,
    backgroundColor: '#e2e8f0',
    alignItems: 'center',
    justifyContent: 'center',
  },
  multiRankBadgeLeader: {
    backgroundColor: '#f59e0b',
  },
  multiRankText: {
    fontSize: 12,
    fontWeight: '900',
    color: '#475569',
  },
  multiRankTextLeader: {
    color: '#ffffff',
  },
  multiGroupCover: {
    width: 38,
    height: 38,
    borderRadius: 10,
  },
  multiGroupInfo: {
    flex: 1,
  },
  multiGroupName: {
    fontSize: 13,
    fontWeight: '800',
  },
  multiGroupSub: {
    fontSize: 10,
    color: '#64748b',
  },
  multiGroupRight: {
    alignItems: 'flex-end',
  },
  multiScoreNumber: {
    fontSize: 14,
    fontWeight: '900',
    color: '#059669',
  },
  multiScoreLabel: {
    fontSize: 9,
    color: '#94a3b8',
  },
  viewerBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 12,
    borderRadius: 14,
    marginBottom: 10,
  },
  viewerBannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  viewerBannerShield: {
    fontSize: 20,
  },
  viewerBannerTextGroup: {
    flex: 1,
  },
  viewerBannerTitle: {
    fontSize: 12,
    fontWeight: '800',
    color: '#ffffff',
  },
  viewerBannerSub: {
    fontSize: 10,
    color: '#d1fae5',
    fontWeight: '600',
  },
  viewerChatBtn: {
    backgroundColor: '#ffffff',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  viewerChatBtnText: {
    fontSize: 11,
    fontWeight: '800',
    color: '#065f46',
  },
  notInGroupAlert: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f8fafc',
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginBottom: 10,
  },
  notInGroupAlertText: {
    fontSize: 11,
    color: '#64748b',
    flex: 1,
  },
  expandBtn: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  expandBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#059669',
  },
  membersBreakdownContainer: {
    gap: 10,
    marginTop: 8,
  },
  teamBreakdownBox: {
    backgroundColor: 'rgba(0,0,0,0.02)',
    padding: 8,
    borderRadius: 12,
  },
  teamBreakdownTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: '#334155',
    marginBottom: 6,
  },
  breakdownList: {
    gap: 4,
  },
  breakdownItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#ffffff',
    padding: 6,
    borderRadius: 8,
  },
  breakdownItemViewer: {
    borderWidth: 1,
    borderColor: '#10b981',
    backgroundColor: '#ecfdf5',
  },
  breakdownRank: {
    fontSize: 10,
    fontWeight: '800',
    color: '#94a3b8',
    width: 20,
  },
  breakdownAvatar: {
    width: 20,
    height: 20,
    borderRadius: 10,
  },
  breakdownName: {
    fontSize: 10.5,
    lineHeight: 14,
    fontWeight: '700',
    flex: 1,
  },
  breakdownSteps: {
    fontSize: 11,
    fontWeight: '800',
    color: '#059669',
  },
});
