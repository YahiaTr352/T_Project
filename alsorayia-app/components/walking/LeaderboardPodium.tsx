import { ThemedText } from '@/components/themed-text';
import { Colors } from '@/constants/theme';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React from 'react';
import { Platform, StyleSheet, TouchableOpacity, View, useColorScheme, useWindowDimensions } from 'react-native';

import { AnonymousAvatar } from './AnonymousAvatar';
import { formatSteps, formatStreakDays } from '@/lib/walking/formatters';
import { AnimatedCounter } from './AnimatedCounter';

export interface PodiumParticipant {
  rank: number;
  person_id: number | null;
  membership_id?: number | null;
  total_steps: number;
  streak_days?: number;
  streak_10k?: number;
  is_today_completed?: boolean;
  person: {
    id?: number;
    name?: string;
    avatar?: string | null;
    gender?: string | null;
  };
  in_top?: boolean;
  role?: string;
  is_anonymous?: boolean;
  is_self?: boolean;
}

type Props = {
  topThree: PodiumParticipant[];
  onPressPerson?: (personId: number) => void;
  mode?: 'steps' | 'streak';
};

export function LeaderboardPodium({ topThree, onPressPerson, mode = 'steps' }: Props) {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';
  const { width: windowWidth } = useWindowDimensions();
  const isCompact = windowWidth < 380;

  // أول 3 بالترتيب (مواضع المنصة)، وليس بالبحث عن rank === 1/2/3
  // لأن المراكز كثيفة (Dense) وقد تتكرر مع التعادل (مثال: 1,1,3) فكان
  // .find يلتقط أول متعادل فقط ويخفي الباقين، وقد يغيب rank 2 تماماً.
  // شارة الرقم تعرض المركز الحقيقي (#rank) والميدالية تبقى حسب الموضع.
  const [first, second, third] = topThree;

  if (!first) return null;

  const isSecondAnon = Boolean(second?.is_anonymous && !second?.is_self);
  const isFirstAnon = Boolean(first?.is_anonymous && !first?.is_self);
  const isThirdAnon = Boolean(third?.is_anonymous && !third?.is_self);

  const handlePress = (participant?: PodiumParticipant) => {
    if (!participant) return;
    const isAnon = Boolean(participant.is_anonymous && !participant.is_self);
    if (isAnon) return;
    const personId = participant.person?.id || participant.person_id;
    if (!personId) return;

    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}

    if (onPressPerson) {
      onPressPerson(personId);
    } else {
      router.push(`/(app)/person/${personId}` as any);
    }
  };

  return (
    <View style={[styles.container, isCompact && styles.containerCompact]}>
      {/* 2nd Place (Silver) */}
      {second && (
        <View style={[styles.podiumColumn, { marginTop: 32 }]}>
          <TouchableOpacity
            activeOpacity={isSecondAnon ? 1 : 0.75}
            disabled={isSecondAnon}
            onPress={() => handlePress(second)}
            style={styles.touchableAvatarBlock}>
            <View style={styles.avatarWrapper}>
              <View style={[styles.medalRing, styles.silverRing]}>
                {second.person.avatar ? (
                  <Image source={{ uri: second.person.avatar }} style={[styles.avatar, isCompact && styles.avatarCompact]} />
                ) : isSecondAnon ? (
                  <AnonymousAvatar
                    gender={second.person.gender}
                    size={isCompact ? 48 : 56}
                    backgroundColor={isDark ? '#334155' : '#E2E8F0'}
                    iconColor={isDark ? '#CBD5E1' : '#475569'}
                  />
                ) : (
                  <View style={[styles.avatarPlaceholder, isCompact && styles.avatarCompact, { backgroundColor: isDark ? '#334155' : '#E2E8F0' }]}>
                    <ThemedText style={[styles.placeholderText, { color: isDark ? '#CBD5E1' : '#475569' }]}>
                      {second.person.name?.slice(0, 1) || 'ع'}
                    </ThemedText>
                  </View>
                )}
              </View>
              <View style={[styles.rankBadge, styles.rankBadgeSilver]}>
                <ThemedText style={styles.rankBadgeText}>{second.rank ?? 2}</ThemedText>
              </View>
            </View>

            <ThemedText numberOfLines={2} style={[styles.personName, { color: colors.text }]}>
              {isSecondAnon ? 'متخفي' : (second.person.name || 'عضو')}
            </ThemedText>
          </TouchableOpacity>

          {mode === 'steps' ? (
            <AnimatedCounter
              value={second.total_steps}
              style={[styles.stepsText, { color: '#64748B' }]}
            />
          ) : (
            <View style={styles.streakPodiumInfoWrap}>
              <View
                style={[
                  styles.podiumStreakPrimaryBadge,
                  {
                    backgroundColor: isDark ? 'rgba(234, 88, 12, 0.22)' : '#FFF7ED',
                    borderColor: isDark ? 'rgba(251, 146, 60, 0.45)' : '#FED7AA',
                  },
                ]}>
                <ThemedText style={styles.podiumStreakIcon}>🔥</ThemedText>
                <ThemedText
                  numberOfLines={1}
                  adjustsFontSizeToFit
                  minimumFontScale={0.8}
                  style={[styles.podiumStreakPrimaryText, { color: isDark ? '#FB923C' : '#EA580C' }]}>
                  {formatStreakDays(second.streak_days ?? second.streak_10k)}
                </ThemedText>
              </View>
              <ThemedText numberOfLines={1} style={[styles.podiumStepsSubText, { color: isDark ? '#94A3B8' : '#64748B' }]}>
                👟 {formatSteps(second.total_steps)}
              </ThemedText>
            </View>
          )}

          <LinearGradient
            colors={isDark ? ['#334155', '#1E293B'] : ['#E2E8F0', '#CBD5E1']}
            style={[styles.podiumBase, { height: isCompact ? 65 : 75 }]}>
            <ThemedText style={[styles.podiumNumber, { color: isDark ? '#94A3B8' : '#64748B' }]}>
              🥈
            </ThemedText>
          </LinearGradient>
        </View>
      )}

      {/* 1st Place (Gold) */}
      <View style={styles.podiumColumn}>
        <TouchableOpacity
          activeOpacity={isFirstAnon ? 1 : 0.75}
          disabled={isFirstAnon}
          onPress={() => handlePress(first)}
          style={styles.touchableAvatarBlock}>
          <View style={styles.avatarWrapper}>
            <ThemedText style={styles.crownIcon}>👑</ThemedText>
              <View style={[styles.medalRing, styles.goldRing]}>
                {first.person.avatar ? (
                  <Image source={{ uri: first.person.avatar }} style={[styles.avatarLarge, isCompact && styles.avatarLargeCompact]} />
                ) : isFirstAnon ? (
                  <AnonymousAvatar
                    gender={first.person.gender}
                    size={isCompact ? 60 : 70}
                    backgroundColor={isDark ? '#78350F' : '#FEF3C7'}
                    iconColor="#F59E0B"
                  />
                ) : (
                  <View style={[styles.avatarPlaceholderLarge, isCompact && styles.avatarLargeCompact, { backgroundColor: isDark ? '#78350F' : '#FEF3C7' }]}>
                    <ThemedText style={[styles.placeholderTextLarge, { color: '#F59E0B' }]}>
                      {first.person.name?.slice(0, 1) || 'ع'}
                    </ThemedText>
                  </View>
                )}
              </View>
            <View style={[styles.rankBadge, styles.rankBadgeGold]}>
              <ThemedText style={[styles.rankBadgeText, styles.rankBadgeGoldText]}>{first.rank ?? 1}</ThemedText>
            </View>
          </View>

          <ThemedText numberOfLines={2} style={[styles.personName, styles.firstName, { color: colors.text }]}>
              {isFirstAnon ? 'متخفي' : (first.person.name || 'المتصدر')}
          </ThemedText>
        </TouchableOpacity>

        {mode === 'steps' ? (
          <View style={{ flexDirection: 'row-reverse', alignItems: 'center', gap: 4 }}>
            <AnimatedCounter
              value={first.total_steps}
              style={[styles.stepsText, styles.firstStepsText]}
            />
            <ThemedText style={[styles.stepsText, styles.firstStepsText]}>
              خطوة
            </ThemedText>
          </View>
        ) : (
          <View style={styles.streakPodiumInfoWrap}>
            <View
              style={[
                styles.podiumStreakPrimaryBadge,
                styles.podiumStreakPrimaryBadgeFirst,
                {
                  backgroundColor: isDark ? 'rgba(234, 88, 12, 0.32)' : '#FFEDD5',
                  borderColor: isDark ? '#F97316' : '#FB923C',
                },
              ]}>
              <ThemedText style={styles.podiumStreakIconLarge}>🔥</ThemedText>
              <ThemedText
                numberOfLines={1}
                adjustsFontSizeToFit
                minimumFontScale={0.8}
                style={[styles.podiumStreakPrimaryTextLarge, { color: isDark ? '#FDBA74' : '#C2410C' }]}>
                {formatStreakDays(first.streak_days ?? first.streak_10k)}
              </ThemedText>
            </View>
            <ThemedText numberOfLines={1} style={[styles.podiumStepsSubText, { color: isDark ? '#FB923C' : '#D97706', fontFamily: 'Tajawal-Bold' }]}>
              👟 {formatSteps(first.total_steps)}
            </ThemedText>
          </View>
        )}

        <LinearGradient
          colors={isDark ? ['#B45309', '#78350F'] : ['#FDE68A', '#F59E0B']}
          style={[styles.podiumBase, { height: isCompact ? 95 : 105 }]}>
          <ThemedText style={styles.podiumNumber}>🥇</ThemedText>
        </LinearGradient>
      </View>

      {/* 3rd Place (Bronze) */}
      {third && (
        <View style={[styles.podiumColumn, { marginTop: 44 }]}>
          <TouchableOpacity
            activeOpacity={isThirdAnon ? 1 : 0.75}
            disabled={isThirdAnon}
            onPress={() => handlePress(third)}
            style={styles.touchableAvatarBlock}>
            <View style={styles.avatarWrapper}>
              <View style={[styles.medalRing, styles.bronzeRing, { borderColor: isDark ? '#EA580C' : '#C2410C' }]}>
                {third.person.avatar ? (
                  <Image source={{ uri: third.person.avatar }} style={[styles.avatar, isCompact && styles.avatarCompact]} />
                ) : isThirdAnon ? (
                  <AnonymousAvatar
                    gender={third.person.gender}
                    size={isCompact ? 48 : 56}
                    backgroundColor={isDark ? '#431407' : '#FFEDD5'}
                    iconColor={isDark ? '#FB923C' : '#9A3412'}
                  />
                ) : (
                  <View style={[styles.avatarPlaceholder, isCompact && styles.avatarCompact, { backgroundColor: isDark ? '#431407' : '#FFEDD5' }]}>
                    <ThemedText style={[styles.placeholderText, { color: isDark ? '#FB923C' : '#9A3412' }]}>
                      {third.person.name?.slice(0, 1) || 'ع'}
                    </ThemedText>
                  </View>
                )}
              </View>
              <View style={[styles.rankBadge, styles.rankBadgeBronze, { backgroundColor: isDark ? '#EA580C' : '#C2410C' }]}>
                <ThemedText style={styles.rankBadgeText}>{third.rank ?? 3}</ThemedText>
              </View>
            </View>

            <ThemedText numberOfLines={2} style={[styles.personName, { color: colors.text }]}>
              {isThirdAnon ? 'متخفي' : (third.person.name || 'عضو')}
            </ThemedText>
          </TouchableOpacity>

          {mode === 'steps' ? (
            <AnimatedCounter
              value={third.total_steps}
              style={[styles.stepsText, { color: isDark ? '#FB923C' : '#7C2D12' }]}
            />
          ) : (
            <View style={styles.streakPodiumInfoWrap}>
              <View
                style={[
                  styles.podiumStreakPrimaryBadge,
                  {
                    backgroundColor: isDark ? 'rgba(234, 88, 12, 0.22)' : '#FFF7ED',
                    borderColor: isDark ? 'rgba(251, 146, 60, 0.45)' : '#FED7AA',
                  },
                ]}>
                <ThemedText style={styles.podiumStreakIcon}>🔥</ThemedText>
                <ThemedText
                  numberOfLines={1}
                  adjustsFontSizeToFit
                  minimumFontScale={0.8}
                  style={[styles.podiumStreakPrimaryText, { color: isDark ? '#FB923C' : '#EA580C' }]}>
                  {formatStreakDays(third.streak_days ?? third.streak_10k)}
                </ThemedText>
              </View>
              <ThemedText numberOfLines={1} style={[styles.podiumStepsSubText, { color: isDark ? '#FB923C' : '#7C2D12' }]}>
                👟 {formatSteps(third.total_steps)}
              </ThemedText>
            </View>
          )}

          <LinearGradient
            colors={isDark ? ['#431407', '#290E04'] : ['#FED7AA', '#EA580C']}
            style={[styles.podiumBase, { height: isCompact ? 52 : 60 }]}>
            <ThemedText style={styles.podiumNumber}>🥉</ThemedText>
          </LinearGradient>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'center',
    gap: 8,
    marginVertical: 14,
    paddingHorizontal: 4,
    width: '100%',
  },
  containerCompact: {
    gap: 4,
    paddingHorizontal: 2,
    marginVertical: 10,
  },
  podiumColumn: {
    flex: 1,
    alignItems: 'center',
    minWidth: 0,
  },
  touchableAvatarBlock: {
    alignItems: 'center',
    width: '100%',
  },
  avatarWrapper: {
    position: 'relative',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  crownIcon: {
    fontSize: 22,
    position: 'absolute',
    top: -22,
    zIndex: 10,
  },
  medalRing: {
    borderRadius: 999,
    padding: 2.5,
    borderWidth: 2.5,
  },
  goldRing: {
    borderColor: '#F59E0B',
    borderWidth: 3,
    ...Platform.select({
      ios: {
        shadowColor: '#F59E0B',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.5,
        shadowRadius: 8,
      },
      android: { elevation: 6 },
    }),
  },
  silverRing: {
    borderColor: '#94A3B8',
    ...Platform.select({
      ios: {
        shadowColor: '#94A3B8',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.35,
        shadowRadius: 5,
      },
      android: { elevation: 4 },
    }),
  },
  bronzeRing: {
    ...Platform.select({
      ios: {
        shadowColor: '#EA580C',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.35,
        shadowRadius: 5,
      },
      android: { elevation: 4 },
    }),
  },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
  },
  avatarCompact: {
    width: 48,
    height: 48,
    borderRadius: 24,
  },
  avatarLarge: {
    width: 70,
    height: 70,
    borderRadius: 35,
  },
  avatarLargeCompact: {
    width: 60,
    height: 60,
    borderRadius: 30,
  },
  avatarPlaceholder: {
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarPlaceholderLarge: {
    width: 70,
    height: 70,
    borderRadius: 35,
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
  },
  placeholderTextLarge: {
    fontFamily: 'Tajawal-ExtraBold',
    fontSize: 26,
  },
  rankBadge: {
    position: 'absolute',
    bottom: -9,
    width: 19,
    height: 19,
    borderRadius: 9.5,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1.5,
    borderColor: '#FFFFFF',
    zIndex: 10,
  },
  rankBadgeGold: {
    backgroundColor: '#F59E0B',
  },
  rankBadgeSilver: {
    backgroundColor: '#94A3B8',
  },
  rankBadgeBronze: {
    backgroundColor: '#EA580C',
  },
  rankBadgeText: {
    color: '#FFFFFF',
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 12,
  },
  rankBadgeGoldText: {
    color: '#78350F',
  },
  personName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    lineHeight: 14.5,
    textAlign: 'center',
    marginBottom: 4,
    maxWidth: '96%',
  },
  firstName: {
    fontSize: 12,
    lineHeight: 15.5,
    fontFamily: 'Tajawal-Bold',
  },
  stepsText: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    marginBottom: 8,
  },
  firstStepsText: {
    color: '#D97706',
    fontSize: 12.5,
  },
  streakPodiumInfoWrap: {
    alignItems: 'center',
    marginBottom: 8,
    width: '100%',
  },
  podiumStreakPrimaryBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 12,
    borderWidth: 1,
    maxWidth: '98%',
  },
  podiumStreakPrimaryBadgeFirst: {
    paddingHorizontal: 9,
    paddingVertical: 3.5,
    borderRadius: 14,
    borderWidth: 1.2,
  },
  podiumStreakIcon: {
    fontSize: 11,
    flexShrink: 0,
  },
  podiumStreakIconLarge: {
    fontSize: 12.5,
    flexShrink: 0,
  },
  podiumStreakPrimaryText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
    flexShrink: 1,
  },
  podiumStreakPrimaryTextLarge: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
    flexShrink: 1,
  },
  podiumStepsSubText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10.5,
    marginTop: 2,
    textAlign: 'center',
  },
  podiumStreakSubText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 9.5,
    marginTop: 2,
    textAlign: 'center',
  },
  podiumBase: {
    width: '100%',
    borderTopLeftRadius: 10,
    borderTopRightRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  podiumNumber: {
    fontSize: 22,
  },
});
