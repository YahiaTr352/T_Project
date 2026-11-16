import React, { useEffect, useState } from 'react';
import { StyleSheet, View, Animated, Dimensions, TouchableOpacity } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { Image } from 'expo-image';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import type { VoteResultsChoice } from '@/lib/api/quiz';
import { toEnglishDigits } from '@/lib/utils/numberFormat';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

interface PollResultsProps {
  results: VoteResultsChoice[];
  totalVotes: number;
  colors: any;
}

export default function PollResults({ results, totalVotes, colors }: PollResultsProps) {
  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <ThemedText style={[styles.title, { color: colors.text }]}>
          نتائج التصويت الحالية ({toEnglishDigits(totalVotes)} صوت)
        </ThemedText>
      </View>

      <View style={styles.resultsList}>
        {results.map((choice, index) => (
          <ResultRow key={choice.id || index} choice={choice} rank={choice.rank || (index + 1)} colors={colors} />
        ))}
      </View>
    </View>
  );
}

function ResultRow({ choice, rank, colors }: { choice: VoteResultsChoice; rank: number; colors: any }) {
  const router = useRouter();
  const [animatedWidth] = useState(() => new Animated.Value(0));

  useEffect(() => {
    Animated.timing(animatedWidth, {
      toValue: choice.percent,
      duration: 1000,
      useNativeDriver: false,
    }).start();
  }, [choice.percent]);

  const widthInterpolation = animatedWidth.interpolate({
    inputRange: [0, 100],
    outputRange: ['0%', '100%'],
  });

  const getRankBadge = (pos: number) => {
    if (pos === 1) return { icon: '🥇', label: 'المركز الأول', color: '#f59e0b' };
    if (pos === 2) return { icon: '🥈', label: 'المركز الثاني', color: '#94a3b8' };
    if (pos === 3) return { icon: '🥉', label: 'المركز الثالث', color: '#d97706' };
    return { icon: `#${toEnglishDigits(pos)}`, label: `المركز ${toEnglishDigits(pos)}`, color: colors.icon };
  };

  const rankInfo = getRankBadge(rank);
  const hasTreeAvatar = Boolean(choice.avatar_url || choice.person_id);

  return (
    <View style={[styles.rowCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <View style={styles.rowHeader}>
        <TouchableOpacity
          activeOpacity={choice.person_id ? 0.7 : 1}
          disabled={!choice.person_id}
          onPress={() => {
            if (choice.person_id) {
              router.push(`/(app)/person/${choice.person_id}` as any);
            }
          }}
          style={styles.rowMeta}
        >
          {hasTreeAvatar ? (
            <PersonAvatar
              uri={choice.avatar_url || choice.image_url}
              size={44}
              mutedColor={colors.icon}
              borderColor={rank === 1 ? '#f59e0b' : rank === 2 ? '#94a3b8' : rank === 3 ? '#d97706' : colors.border}
              name={choice.text}
            />
          ) : choice.image_url ? (
            <Image source={{ uri: choice.image_url }} style={styles.thumbnail} contentFit="cover" />
          ) : null}

          <View style={styles.nameContainer}>
            <ThemedText style={[styles.choiceText, { color: colors.text }]} numberOfLines={1}>
              {choice.text}
            </ThemedText>
          </View>
        </TouchableOpacity>

        <View style={styles.statsContainer}>
          <View style={styles.rankPill}>
            <ThemedText style={[styles.rankText, { color: rankInfo.color }]}>
              {rankInfo.icon}
            </ThemedText>
          </View>
          <View style={styles.voteNumbers}>
            <ThemedText style={styles.percentText}>{toEnglishDigits(choice.percent)}%</ThemedText>
            <ThemedText style={[styles.countText, { color: colors.icon }]}>
              ({toEnglishDigits(choice.count)} صوت)
            </ThemedText>
          </View>
        </View>
      </View>

      <View style={[styles.progressBarBg, { backgroundColor: colors.border }]}>
        <Animated.View
          style={[
            styles.progressBarFill,
            {
              width: widthInterpolation,
              backgroundColor: rank === 1 ? '#37a05c' : '#10b981',
            },
          ]}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    width: '100%',
    paddingVertical: 8,
  },
  header: {
    marginBottom: 12,
    alignItems: 'flex-start',
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  resultsList: {
    gap: 12,
  },
  rowCard: {
    width: '100%',
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
  },
  rowHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  rowMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  thumbnail: {
    width: 44,
    height: 44,
    borderRadius: 22,
  },
  nameContainer: {
    flex: 1,
    alignItems: 'flex-start',
  },
  choiceText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    textAlign: 'left',
  },
  statsContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  rankPill: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rankText: {
    fontSize: 15,
  },
  voteNumbers: {
    alignItems: 'flex-end',
  },
  percentText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    color: '#10b981',
  },
  countText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 10,
  },
  progressBarBg: {
    width: '100%',
    height: 8,
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBarFill: {
    height: '100%',
    borderRadius: 4,
  },
});
