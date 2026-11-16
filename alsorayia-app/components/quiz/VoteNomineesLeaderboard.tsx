import React from 'react';
import { StyleSheet, View, TouchableOpacity, ScrollView, Platform } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { Image } from 'expo-image';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import type { QuizChoice, VoteResultsChoice } from '@/lib/api/quiz';
import { toEnglishDigits } from '@/lib/utils/numberFormat';

interface VoteNomineesLeaderboardProps {
  title?: string;
  results: VoteResultsChoice[];
  totalVotes: number;
  selectedIds?: number[];
  hasVoted?: boolean;
  colors: any;
  isDark?: boolean;
  choices?: QuizChoice[];
  isOpenTree?: boolean;
  onPressPerson?: (personId: number) => void;
}

export default function VoteNomineesLeaderboard({
  title,
  results,
  totalVotes,
  selectedIds = [],
  hasVoted = false,
  colors,
  isDark = false,
  choices = [],
  isOpenTree = false,
  onPressPerson,
}: VoteNomineesLeaderboardProps) {
  const router = useRouter();

  if (!results || results.length === 0) {
    return null;
  }

  // Ensure results are sorted descending by count
  const sortedResults = [...results]
    .sort((a, b) => (b.count || 0) - (a.count || 0))
    .map((item, idx) => ({
      ...item,
      rank: item.rank || idx + 1,
    }));

  const resolvePersonId = (choice: VoteResultsChoice): number | null => {
    // 1. Direct person_id or personId or member_id
    const raw =
      choice.person_id ??
      (choice as any).personId ??
      (choice as any).member_id ??
      (choice as any).person?.id;
    if (raw != null && !Number.isNaN(Number(raw)) && Number(raw) > 0) {
      return Number(raw);
    }
    // 2. Match choice.id in question choices to get a verified person_id
    // (Never treat choice.id itself as person_id, even in open_tree questions.)
    if (choices && choices.length > 0) {
      const matched = choices.find((c) => c.id === choice.id);
      const matchedPersonId =
        matched?.person_id ??
        (matched as any)?.personId ??
        (matched as any)?.member_id ??
        (matched as any)?.person?.id;
      if (
        matchedPersonId != null &&
        !Number.isNaN(Number(matchedPersonId)) &&
        Number(matchedPersonId) > 0
      ) {
        return Number(matchedPersonId);
      }
    }
    return null;
  };

  const handlePressPerson = (choice: VoteResultsChoice) => {
    const personId = resolvePersonId(choice);
    if (personId) {
      if (onPressPerson) {
        onPressPerson(personId);
      } else {
        router.push(`/(app)/person/${personId}` as any);
      }
    }
  };

  /**
   * Helper to format rank:
   * Returns numeric digit (1, 2, 3...) for all ranks to maximize horizontal space for nominee names.
   * Ranks 1 to 5 keep their distinct luxury podium badge colors.
   */
  const getRankInfo = (rank: number) => {
    const digit = toEnglishDigits(rank);
    switch (rank) {
      case 1:
        return {
          label: digit,
          badgeBg: isDark ? '#78350f35' : '#fef3c7',
          badgeBorder: isDark ? '#b45309' : '#f59e0b',
          textColor: isDark ? '#fbbf24' : '#92400e',
        };
      case 2:
        return {
          label: digit,
          badgeBg: isDark ? '#33415550' : '#f1f5f9',
          badgeBorder: isDark ? '#64748b' : '#94a3b8',
          textColor: isDark ? '#e2e8f0' : '#334155',
        };
      case 3:
        return {
          label: digit,
          badgeBg: isDark ? '#7c2d1235' : '#ffedd5',
          badgeBorder: isDark ? '#ea580c' : '#fdba74',
          textColor: isDark ? '#fb923c' : '#9a3412',
        };
      case 4:
        return {
          label: digit,
          badgeBg: isDark ? '#064e3b35' : '#ecfdf5',
          badgeBorder: isDark ? '#059669' : '#a7f3d0',
          textColor: isDark ? '#34d399' : '#047857',
        };
      case 5:
        return {
          label: digit,
          badgeBg: isDark ? '#134e4a35' : '#f0fdfa',
          badgeBorder: isDark ? '#0d9488' : '#99f6e4',
          textColor: isDark ? '#2dd4bf' : '#0f766e',
        };
      default:
        return {
          label: digit,
          badgeBg: isDark ? '#1e293b' : '#f8fafc',
          badgeBorder: isDark ? '#334155' : '#e2e8f0',
          textColor: isDark ? '#94a3b8' : '#64748b',
        };
    }
  };

  return (
    <View style={styles.container}>
      {/* ══════════════════════════════════════════════════════
           1. Header (RTL: Title on Right, Total Votes on Left)
           ══════════════════════════════════════════════════════ */}
      <View style={styles.header}>
        {Boolean(title?.trim()) ? (
          <View style={styles.titleRow}>
            <Ionicons name="podium-outline" size={17} color="#f59e0b" />
            <ThemedText style={[styles.title, { color: isDark ? '#f8fafc' : colors.text }]}>
              {title}
            </ThemedText>
          </View>
        ) : (
          <View />
        )}
        <View style={[styles.votesCountBadge, { backgroundColor: isDark ? '#064e3b40' : '#10b98115' }]}>
          <Ionicons name="people-outline" size={13} color="#10b981" />
          <ThemedText style={styles.votesCountText}>
            {toEnglishDigits(totalVotes)} {totalVotes === 1 ? 'صوت مسجل' : 'أصوات مسجلة'}
          </ThemedText>
        </View>
      </View>

      <ThemedText style={[styles.subtitle, { color: isDark ? '#94a3b8' : colors.icon }]}>
        المتوقعون الأكثر ترجيحاً للفوز حتى الآن:
      </ThemedText>

      {/* ══════════════════════════════════════════════════════
           2. Unified Nominees Table
           ══════════════════════════════════════════════════════ */}
      <View
        style={[
          styles.tableContainer,
          {
            backgroundColor: isDark ? '#0f172a80' : '#f8fafc',
            borderColor: isDark ? '#334155' : '#e2e8f0',
          },
        ]}
      >
        <ScrollView
          style={styles.tableScrollView}
          contentContainerStyle={styles.tableList}
          nestedScrollEnabled={true}
          showsVerticalScrollIndicator={true}
        >
          {sortedResults.map((choice) => {
            const rank = choice.rank || 1;
            const rankInfo = getRankInfo(rank);
            const personId = resolvePersonId(choice);
            const hasTreeAvatar = Boolean(choice.avatar_url || personId);

            // Determine if the current user voted/selected this candidate
            const isChosen =
              selectedIds.includes(choice.id) ||
              Boolean(personId && selectedIds.includes(personId));

            return (
              <View
                key={choice.id || `nominee-${rank}`}
                style={[
                  styles.tableRow,
                  {
                    backgroundColor: isChosen
                      ? isDark
                        ? '#064e3b30'
                        : '#f0fdf4'
                      : isDark
                      ? '#1e293b60'
                      : '#ffffff',
                    borderColor: isChosen
                      ? isDark
                        ? '#059669'
                        : '#10b981'
                      : rank === 1
                      ? isDark
                        ? '#b4530960'
                        : '#fef08a'
                      : isDark
                      ? '#334155'
                      : '#e9ecef',
                  },
                ]}
              >
                {/* ── Right Side: Rank Badge + Avatar + Nominee Name ── */}
                <TouchableOpacity
                  activeOpacity={personId ? 0.7 : 1}
                  disabled={!personId}
                  onPress={() => handlePressPerson(choice)}
                  style={styles.rowRightSide}
                >
                  {/* Rank Badge (Compact Numeric for All Ranks) */}
                  <View
                    style={[
                      styles.rankBadge,
                      {
                        backgroundColor: rankInfo.badgeBg,
                        borderColor: rankInfo.badgeBorder,
                      },
                    ]}
                  >
                    <ThemedText
                      style={[
                        styles.rankLabel,
                        { color: rankInfo.textColor },
                      ]}
                    >
                      {rankInfo.label}
                    </ThemedText>
                  </View>

                  {/* Avatar */}
                  <View style={styles.avatarHolder}>
                    {hasTreeAvatar ? (
                      <PersonAvatar
                        uri={choice.avatar_url || choice.image_url}
                        size={32}
                        mutedColor={colors.icon}
                        borderColor={rankInfo.badgeBorder}
                        name={choice.text}
                      />
                    ) : choice.image_url ? (
                      <Image
                        source={{ uri: choice.image_url }}
                        style={styles.avatarImg}
                        contentFit="cover"
                        transition={200}
                        cachePolicy="memory-disk"
                      />
                    ) : (
                      <View
                        style={[
                          styles.placeholderAvatar,
                          { backgroundColor: isDark ? '#334155' : '#f1f5f9' },
                        ]}
                      >
                        <Ionicons name="person" size={16} color={colors.icon} />
                      </View>
                    )}
                  </View>

                  {/* Candidate Name & User-Vote Tag */}
                  <View style={styles.nameContainer}>
                    <ThemedText
                      numberOfLines={1}
                      style={[
                        styles.nomineeName,
                        { color: isDark ? '#f8fafc' : colors.text },
                        rank === 1 && styles.nomineeNameFirst,
                      ]}
                    >
                      {choice.text}
                    </ThemedText>

                    {isChosen && (
                      <View
                        style={[
                          styles.chosenBadge,
                          {
                            backgroundColor: isDark ? '#064e3b' : '#dcfce7',
                            borderColor: isDark ? '#059669' : '#86efac',
                          },
                        ]}
                      >
                        <Ionicons name="checkmark-circle" size={10} color="#10b981" />
                        <ThemedText style={styles.chosenBadgeText}>
                          {hasVoted ? 'صوتك' : 'محدد'}
                        </ThemedText>
                      </View>
                    )}

                    {personId ? (
                      <Ionicons
                        name="chevron-back"
                        size={12}
                        color={isDark ? '#64748b' : '#94a3b8'}
                        style={{ marginHorizontal: 2 }}
                      />
                    ) : null}
                  </View>
                </TouchableOpacity>

                {/* ── Left Side: Votes Count + Percent + Quick Pick ── */}
                <View style={styles.rowLeftSide}>
                  <View style={styles.statsHolder}>
                    <ThemedText style={[styles.voteCount, { color: isDark ? '#34d399' : '#059669' }]}>
                      {toEnglishDigits(choice.count)}{' '}
                      <ThemedText style={[styles.voteUnit, { color: isDark ? '#94a3b8' : '#64748b' }]}>
                        صوت
                      </ThemedText>
                    </ThemedText>

                    <View
                      style={[
                        styles.percentBadge,
                        {
                          backgroundColor: isDark ? '#78350f30' : '#fef3c7',
                          borderColor: isDark ? '#b45309' : '#fde68a',
                        },
                      ]}
                    >
                      <ThemedText style={styles.percentText}>
                        %{toEnglishDigits(choice.percent)}
                      </ThemedText>
                    </View>
                  </View>
                </View>
              </View>
            );
          })}
        </ScrollView>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    width: '100%',
    padding: 0,
    marginVertical: 4,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
    width: '100%',
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  subtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    marginBottom: 8,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  votesCountBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
  },
  votesCountText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    color: '#10b981',
  },
  tableContainer: {
    width: '100%',
    borderRadius: 12,
    borderWidth: 1,
    padding: 6,
    overflow: 'hidden',
  },
  tableScrollView: {
    maxHeight: 280,
    width: '100%',
  },
  tableList: {
    gap: 5,
    paddingVertical: 2,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 7,
    paddingHorizontal: 9,
    borderRadius: 10,
    borderWidth: 1,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.04,
        shadowRadius: 2,
      },
      android: {
        elevation: 1,
      },
    }),
  },
  rowRightSide: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
    minWidth: 0,
  },
  rankBadge: {
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderRadius: 7,
    minWidth: 26,
    height: 24,
    paddingHorizontal: 4,
  },
  rankLabel: {
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    fontSize: 11.5,
    lineHeight: 15,
  },
  avatarHolder: {
    width: 32,
    height: 32,
    borderRadius: 16,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarImg: {
    width: 32,
    height: 32,
    borderRadius: 16,
  },
  placeholderAvatar: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  nameContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    flex: 1,
    minWidth: 0,
  },
  nomineeName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    flexShrink: 1,
  },
  nomineeNameFirst: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 12.5,
  },
  chosenBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: 6,
    borderWidth: 1,
  },
  chosenBadgeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9.5,
    color: '#10b981',
    lineHeight: 12,
  },
  rowLeftSide: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginLeft: 4,
  },
  statsHolder: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  voteCount: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 11,
  },
  voteUnit: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 9,
  },
  percentBadge: {
    borderWidth: 1,
    paddingHorizontal: 4.5,
    paddingVertical: 1,
    borderRadius: 5,
  },
  percentText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 9.5,
    color: '#92400e',
    lineHeight: 13,
  },
});
