import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, silaTheme } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { fetchPersonsSearchList } from '@/lib/api/familyTreeApi';
import type {
  LeaderboardData,
  LeaderboardPeriod,
  MemberChallengeSettings,
  WalkingGroupDetail,
  WalkingGroupItem,
  WalkingGroupMember,
} from '@/lib/api/walkingApi';
import {
  reactivateWalkingGroup,
  updateMemberChallengeSettings,
  deleteWalkingGroup,
} from '@/lib/api/walkingApi';
import { Ionicons } from '@expo/vector-icons';
import { AnonymousAvatar } from './AnonymousAvatar';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActivityIndicator,
  I18nManager,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  Share,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

import { ChallengePrivacySettingsModal } from './ChallengePrivacySettingsModal';
import { ChallengeInvitePickerModal } from './ChallengeInvitePickerModal';
import {
  formatGroupDates,
  formatSteps,
  formatStreakDays,
  getStepMilestoneLegend,
  getStepTierConfig,
  toEnglishDigits,
} from '@/lib/walking/formatters';
import { EditGroupModal } from './EditGroupModal';
import { AnimatedCounter } from './AnimatedCounter';
import { groupDetailsStyles as styles } from './group-details/groupDetailsStyles';
import { GroupActionsSheet } from './group-details/GroupActionsSheet';
import {
  GROUP_PERIODS,
  computeChallengeLeaderboardEntries,
  computeDisplayMembers,
  computeMyStanding,
  computePointsLeaderboardEntries,
  computeTodayScoringMap,
  computeTodayStepsByPersonId,
  computeTotalGroupMembersCount,
  type SelectedPerson,
} from './group-details/groupLeaderboardEngine';

type Props = {
  visible: boolean;
  selectedGroupId?: number | null;
  initialGroup?: WalkingGroupItem | null;
  initialTab?: 'leaderboard' | 'members' | 'chat';
  onJoinGroup?: (groupId: number) => Promise<{ success: boolean; message?: string }>;
  group: WalkingGroupDetail | null;
  leaderboard: LeaderboardData | null;
  todaySteps?: number;
  onSyncNow?: () => Promise<boolean>;
  onClose: () => void;
  onInviteMember: (groupId: number, personIds: number | number[]) => Promise<{ success: boolean; message?: string }>;
  onLeaveGroup: (groupId: number, personId: number) => Promise<{ success: boolean; message?: string }>;
  onLoadLeaderboardPeriod?: (groupId: number, period: LeaderboardPeriod) => Promise<void>;
  onGroupUpdated?: (group: WalkingGroupItem) => void;
  onDeleteGroup?: (groupId: number) => Promise<{ success: boolean; message?: string }>;
};

export function GroupDetailsModal({
  visible,
  selectedGroupId,
  initialGroup,
  initialTab,
  onJoinGroup,
  group: rawGroup,
  leaderboard: rawLeaderboard,
  todaySteps,
  onSyncNow,
  onClose,
  onInviteMember,
  onLeaveGroup,
  onLoadLeaderboardPeriod,
  onGroupUpdated,
  onDeleteGroup,
}: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';
  const { user } = useAuth();
  const router = useRouter();

  const handlePersonPress = (personId: number) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}
    onClose();
    setTimeout(() => {
      router.push(`/(app)/person/${personId}` as any);
    }, 120);
  };

  // منع تسريب بيانات أي مجموعة قديمة بشكل قاطع مع الاحتفاظ ببيانات المجموعة الحالية أثناء التحديث الخلفي
  const targetGroupId = selectedGroupId ?? initialGroup?.id ?? rawGroup?.id ?? null;
  const isMatchingDetail = Boolean(rawGroup && targetGroupId && rawGroup.id === targetGroupId);
  const [localUpdatedGroup, setLocalUpdatedGroup] = useState<WalkingGroupItem | null>(null);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedPeriod, setSelectedPeriod] = useState<LeaderboardPeriod>('today');
  const [leaderboardMetric, setLeaderboardMetric] = useState<'points' | 'steps'>('points');

  useEffect(() => {
    if (visible) {
      setSelectedPeriod('today');
      setLeaderboardMetric('points');
    }
  }, [visible, targetGroupId]);

  const [cachedGroup, setCachedGroup] = useState<WalkingGroupDetail | null>(null);
  const [periodLeaderboardCache, setPeriodLeaderboardCache] = useState<
    Partial<Record<LeaderboardPeriod, LeaderboardData>>
  >({});

  useEffect(() => {
    if (!visible) {
      setCachedGroup(null);
      setPeriodLeaderboardCache({});
      return;
    }
    if (rawGroup && targetGroupId && rawGroup.id === targetGroupId) {
      setCachedGroup(rawGroup);
    }
    if (rawLeaderboard && targetGroupId && isMatchingDetail) {
      const periodKey = rawLeaderboard.period || selectedPeriod;
      setPeriodLeaderboardCache((prev) => ({
        ...prev,
        [periodKey]: rawLeaderboard,
      }));
    }
  }, [visible, rawGroup, targetGroupId, rawLeaderboard, isMatchingDetail, selectedPeriod]);

  const baseGroup = isMatchingDetail
    ? rawGroup
    : (cachedGroup && cachedGroup.id === targetGroupId
      ? cachedGroup
      : (initialGroup && targetGroupId && initialGroup.id === targetGroupId
        ? initialGroup
        : (rawGroup && targetGroupId && rawGroup.id === targetGroupId ? rawGroup : null)));

  const group = useMemo(() => {
    if (!baseGroup) return null;
    if (localUpdatedGroup && localUpdatedGroup.id === baseGroup.id) {
      return {
        ...baseGroup,
        ...localUpdatedGroup,
      };
    }
    return baseGroup;
  }, [baseGroup, localUpdatedGroup]);

  const hasDetailOrCache = Boolean(baseGroup && targetGroupId && baseGroup.id === targetGroupId);
  const isLoadingDetails = !hasDetailOrCache;
  const leaderboard =
    rawLeaderboard && isMatchingDetail && (rawLeaderboard.period === selectedPeriod || !rawLeaderboard.period)
      ? rawLeaderboard
      : (periodLeaderboardCache[selectedPeriod] ?? null);

  const [activeTab, setActiveTab] = useState<'leaderboard' | 'members'>('leaderboard');

  useEffect(() => {
    if (visible && initialTab === 'chat' && group?.id) {
      onClose();
      router.push({
        pathname: '/(app)/walking-chat/[id]',
        params: {
          id: String(group.id),
          title: group.name,
        },
      });
    } else if (visible && (initialTab === 'leaderboard' || initialTab === 'members')) {
      setActiveTab(initialTab);
    }
  }, [visible, initialTab, group?.id, router, onClose]);
  const [loadingPeriod, setLoadingPeriod] = useState(false);
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [familyMembers, setFamilyMembers] = useState<any[]>([]);
  const [, setLoadingFamily] = useState(false);
  const [, setSearchQuery] = useState('');
  const [selectedInvitees, setSelectedInvitees] = useState<SelectedPerson[]>([]);
  const [isInviting, setIsInviting] = useState(false);
  const [removingMemberId, setRemovingMemberId] = useState<number | null>(null);
  const [isManualSyncing, setIsManualSyncing] = useState(false);
  const [, setKeyboardVisible] = useState(false);
  const [showPrivacyModal, setShowPrivacyModal] = useState(false);
  const [showActionsMenu, setShowActionsMenu] = useState(false);
  const [isReactivating, setIsReactivating] = useState(false);
  const [memberSettings, setMemberSettings] = useState<MemberChallengeSettings | null>(
    (group as any)?.current_member_settings ?? null
  );

  useEffect(() => {
    if ((group as any)?.current_member_settings) {
      setMemberSettings((group as any).current_member_settings);
    }
  }, [group]);

  const handleSavePrivacySettings = async (
    gId: number,
    settings: MemberChallengeSettings
  ) => {
    setMemberSettings(settings);
    const res = await updateMemberChallengeSettings(gId, settings);
    if (res?.success) {
      const savedSettings = res.data || settings;
      setMemberSettings(savedSettings);

      // تحديث كائن المجموعة والأعضاء فوراً في الذاكرة لتفادي بقاء الحالة السابقة
      if (group) {
        const updatedMembers = Array.isArray((group as any).members)
          ? (group as any).members.map((m: any) => {
              const mPersonId = Number(m.person_id || m.person?.id || 0);
              const isSelf = user?.id && mPersonId === Number(user.id);
              if (isSelf) {
                return {
                  ...m,
                  is_anonymous: savedSettings.is_anonymous,
                  share_details: savedSettings.share_details,
                  notifications_enabled: savedSettings.notifications_enabled,
                };
              }
              return m;
            })
          : [];

        const updatedGroupObj = {
          ...group,
          current_member_settings: savedSettings,
          members: updatedMembers,
        } as WalkingGroupDetail;

        setLocalUpdatedGroup(updatedGroupObj);
        if (onGroupUpdated) {
          onGroupUpdated(updatedGroupObj);
        }
      }

      if (onLoadLeaderboardPeriod) {
        await onLoadLeaderboardPeriod(gId, selectedPeriod);
      }
      return { success: true, message: res.message || 'تم حفظ إعدادات الخصوصية بنجاح.' };
    }
    return {
      success: false,
      message: res?.message || 'تعذر تأكيد الحفظ على الخادم، يرجى التحقق من الاتصال.',
    };
  };

  const handleReactivateGroup = async () => {
    if (!group) return;
    CustomAlert.alert(
      'إعادة تنشيط التحدي ⚡',
      `هل ترغب في إعادة تنشيط تحدي «${group.name}» الآن وإشعار جميع الأعضاء؟`,
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'تنشيط الآن ⚡',
          style: 'default',
          onPress: async () => {
            setIsReactivating(true);
            try {
              const res = await reactivateWalkingGroup(group.id);
              if (res?.success && res.data) {
                setLocalUpdatedGroup(res.data);
                if (onGroupUpdated) {
                  onGroupUpdated(res.data);
                }
                CustomAlert.alert('🎉 تم التنشيط بنجاح!', 'عاد التحدي للعمل وأصبح نشطاً لكافة المتسابقين.');
              } else {
                CustomAlert.alert('تنبيه', res?.message || 'تعذر إعادة تنشيط التحدي.');
              }
            } finally {
              setIsReactivating(false);
            }
          },
        },
      ]
    );
  };

  // Keyboard visibility listener to handle dismiss accessory for elderly users
  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const showSub = Keyboard.addListener(showEvent, () => setKeyboardVisible(true));
    const hideSub = Keyboard.addListener(hideEvent, () => setKeyboardVisible(false));
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);

  // Load family members when invite modal opens
  useEffect(() => {
    if (showInviteModal && familyMembers.length === 0) {
      setLoadingFamily(true);
      fetchPersonsSearchList()
        .then((res) => {
          const list = res?.persons || res?.data || (Array.isArray(res) ? res : []);
          setFamilyMembers(list);
        })
        .catch(() => { })
        .finally(() => setLoadingFamily(false));
    }
  }, [showInviteModal, familyMembers.length]);

  // Computed display members sorted descending by total_steps_in_challenge then joined_at
  const displayMembers: WalkingGroupMember[] = useMemo(
    () => computeDisplayMembers({ isMatchingDetail, group, leaderboard }),
    [isMatchingDetail, group, leaderboard]
  );

  // Set of person_ids already in this group
  const existingPersonIds = useMemo(() => {
    const ids = new Set<number>();
    if (displayMembers.length > 0) {
      displayMembers.forEach((m) => {
        if (m.person_id) ids.add(m.person_id);
        if (m.person?.id) ids.add(m.person.id);
      });
    }
    return ids;
  }, [displayMembers]);

  // Unified Leaderboard entries: merges server top results with all display members so 0-step members always show
  const challengeLeaderboardEntries = useMemo(
    () =>
      computeChallengeLeaderboardEntries({
        leaderboard,
        displayMembers,
        user,
        todaySteps,
        isAnonymousSetting: memberSettings?.is_anonymous,
        selectedPeriod,
        group,
      }),
    [leaderboard, displayMembers, user, todaySteps, memberSettings?.is_anonymous, selectedPeriod, group]
  );

  const maxSteps = useMemo(() => {
    return challengeLeaderboardEntries[0]?.total_steps || 1;
  }, [challengeLeaderboardEntries]);

  const totalChallengeSteps = useMemo(() => {
    const sumFromMembers = displayMembers.reduce((acc, m) => {
      const steps = typeof m.total_steps_in_challenge === 'number' ? m.total_steps_in_challenge : 0;
      return acc + steps;
    }, 0);
    if (sumFromMembers > 0) return sumFromMembers;
    return challengeLeaderboardEntries.reduce((acc, item) => acc + item.total_steps, 0);
  }, [displayMembers, challengeLeaderboardEntries]);

  const goalPercent = useMemo(() => {
    if (!group?.target_steps || group.target_steps <= 0) return 0;
    return Math.min(100, Math.round((totalChallengeSteps / group.target_steps) * 100));
  }, [group, totalChallengeSteps]);

  const topThree = useMemo(() => {
    return challengeLeaderboardEntries
      .filter((e) => e.rank >= 1 && e.total_steps > 0)
      .slice(0, 3);
  }, [challengeLeaderboardEntries]);

  const remainingEntries = useMemo(() => {
    if (topThree.length === 0) return challengeLeaderboardEntries;
    return challengeLeaderboardEntries.slice(topThree.length);
  }, [challengeLeaderboardEntries, topThree]);

  // ── Engine: Daily Steps Map & Tonight's Expected Points ──
  const todayStepsByPersonId = useMemo(
    () =>
      computeTodayStepsByPersonId({
        todayLb: periodLeaderboardCache['today'] || (selectedPeriod === 'today' ? leaderboard : null),
        selectedPeriod,
        challengeLeaderboardEntries,
        userId: user?.id,
        todaySteps,
      }),
    [periodLeaderboardCache, leaderboard, selectedPeriod, challengeLeaderboardEntries, user?.id, todaySteps]
  );

  const totalGroupMembersCount = useMemo(
    () =>
      computeTotalGroupMembersCount({
        displayMembers,
        userId: user?.id,
        group,
      }),
    [displayMembers, user?.id, group]
  );

  const todayScoringMap = useMemo(
    () =>
      computeTodayScoringMap({
        displayMembers,
        userId: user?.id,
        group,
        todayStepsByPersonId,
        totalGroupMembersCount,
      }),
    [displayMembers, user?.id, group, todayStepsByPersonId, totalGroupMembersCount]
  );

  const pointsLeaderboardEntries = useMemo(
    () =>
      computePointsLeaderboardEntries({
        displayMembers,
        leaderboard,
        user,
        isAnonymousSetting: memberSettings?.is_anonymous,
        group,
        todayScoringMap,
        todaySteps,
      }),
    [displayMembers, leaderboard, user, memberSettings?.is_anonymous, group, todayScoringMap, todaySteps]
  );

  const myStanding = useMemo(
    () =>
      computeMyStanding({
        userId: user?.id,
        group,
        displayMembers,
        todayScoringMap,
        todaySteps,
        pointsLeaderboardEntries,
      }),
    [user?.id, group, displayMembers, todayScoringMap, todaySteps, pointsLeaderboardEntries]
  );

  const topThreePoints = useMemo(() => {
    return pointsLeaderboardEntries
      .filter((e) => e.rank >= 1 && (e.total_points > 0 || e.expected_points > 0))
      .slice(0, 3);
  }, [pointsLeaderboardEntries]);

  const remainingPointsEntries = useMemo(() => {
    if (topThreePoints.length === 0) return pointsLeaderboardEntries;
    return pointsLeaderboardEntries.slice(topThreePoints.length);
  }, [pointsLeaderboardEntries, topThreePoints]);

  const activeTopThree = leaderboardMetric === 'points' ? topThreePoints : topThree;
  const activeRemainingEntries = leaderboardMetric === 'points' ? remainingPointsEntries : remainingEntries;
  const activeLeaderboardEntries = leaderboardMetric === 'points' ? pointsLeaderboardEntries : challengeLeaderboardEntries;

  if (!visible) return null;

  if (!group) {
    return (
      <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
        <View style={styles.overlay}>
          <View style={[styles.content, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={styles.header}>
              <TouchableOpacity onPress={onClose} style={styles.closeBtn}>
                <IconSymbol name="xmark" size={20} color={colors.icon} />
              </TouchableOpacity>
              <ThemedText style={styles.headerTitle}>تفاصيل التحدي</ThemedText>
              <View style={{ width: 32 }} />
            </View>
            <View style={styles.modalLoadingState}>
              <ActivityIndicator size="large" color={silaTheme.primary} />
              <ThemedText style={[styles.loadingMainText, { color: colors.icon }]}>
                جاري استرجاع بيانات التحدي...
              </ThemedText>
            </View>
          </View>
        </View>
      </Modal>
    );
  }

  const currentMember = displayMembers.find((m) => m.person_id === user?.id || m.person?.id === user?.id);
  const isAdmin = currentMember?.role === 'admin' || group.is_admin;
  const isCreatorOrAdmin =
    isAdmin ||
    Boolean((group as any).is_creator) ||
    Boolean(user?.id && (group as any).created_by_person_id === user.id);
  const isMember = !!currentMember || !!(group as any).is_member;

  const handleRemoveMemberPress = (memberPersonId: number, memberName: string) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch { }

    CustomAlert.alert(
      'استبعاد العضو من التحدي',
      `هل أنت متأكد من رغبتك في استبعاد «${memberName}» من هذا التحدي؟`,
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'نعم، استبعاد',
          style: 'destructive',
          onPress: async () => {
            setRemovingMemberId(memberPersonId);
            try {
              const res = await onLeaveGroup(group.id, memberPersonId);
              if (res.success) {
                setTimeout(() => {
                  CustomAlert.alert('تم بنجاح', res.message || 'تم استبعاد العضو من التحدي.');
                }, 350);
              } else {
                setTimeout(() => {
                  CustomAlert.alert('تنبيه', res.message || 'تعذر استبعاد العضو من المجموعة.');
                }, 350);
              }
            } finally {
              setRemovingMemberId(null);
            }
          },
        },
      ]
    );
  };

  const handlePeriodChange = async (p: LeaderboardPeriod) => {
    setSelectedPeriod(p);
    try {
      Haptics.selectionAsync();
    } catch { }

    if (onLoadLeaderboardPeriod && group) {
      // إذا كانت بيانات الفترة مخزنة في الذاكرة المؤقتة، يتم التبديل فوراً دون حجب الواجهة بمؤشر تحميل (Zero Lag)
      const isCached = Boolean(periodLeaderboardCache[p]);
      if (!isCached) {
        setLoadingPeriod(true);
      }
      try {
        await onLoadLeaderboardPeriod(group.id, p);
      } finally {
        setLoadingPeriod(false);
      }
    }
  };

  const handleManualSync = async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch { }
    setIsManualSyncing(true);
    try {
      if (onSyncNow) {
        await onSyncNow();
      }
      if (group && onLoadLeaderboardPeriod) {
        await onLoadLeaderboardPeriod(group.id, selectedPeriod);
      }
    } finally {
      setIsManualSyncing(false);
    }
  };

  const handleShareCode = async () => {
    if (!group.invite_code) return;
    try {
      try {
        Haptics.selectionAsync();
      } catch { }
      await Share.share({
        message: `انضم معي إلى تحدي «${group.name}» في تطبيق عائلة السريّع! كود الانضمام: ${group.invite_code}`,
      });
    } catch { }
  };

  const handleToggleInvitee = (person: any) => {
    try {
      Haptics.selectionAsync();
    } catch { }

    const personId = person.id;
    const personName =
      person.name ||
      person.full_name ||
      [person.first_name, person.last_name].filter(Boolean).join(' ') ||
      person.first_name ||
      'عضو';
    const personAvatar = person.avatar || person.photo_url || null;

    const isAlready = selectedInvitees.some((m) => m.id === personId);
    if (isAlready) {
      setSelectedInvitees((prev) => prev.filter((m) => m.id !== personId));
    } else {
      setSelectedInvitees((prev) => [
        ...prev,
        { id: personId, name: personName, avatar: personAvatar },
      ]);
    }
  };

  const handleRemoveInvitee = (personId: number) => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch { }
    setSelectedInvitees((prev) => prev.filter((m) => m.id !== personId));
  };

  const handleSendInvitations = async () => {
    if (!isCreatorOrAdmin) {
      CustomAlert.alert('تنبيه', 'فقط منشئ أو مدير التحدي يمكنه إضافة أعضاء للتحدي.');
      return;
    }

    if (selectedInvitees.length === 0) {
      CustomAlert.alert('تنبيه', 'يرجى اختيار عضو واحد على الأقل لإضافته للمجموعة');
      return;
    }

    setIsInviting(true);
    try {
      const ids = selectedInvitees.map((m) => m.id);
      const res = await onInviteMember(group.id, ids);
      if (res.success) {
        setSelectedInvitees([]);
        setSearchQuery('');
        setShowInviteModal(false);
        setTimeout(() => {
          CustomAlert.alert('نجاح', res.message || 'تمت إضافة الأعضاء للمجموعة بنجاح!');
        }, 350);
      } else {
        CustomAlert.alert('تنبيه', res.message || 'تعذر إضافة الأعضاء للمجموعة');
      }
    } finally {
      setIsInviting(false);
    }
  };

  const handleDeleteFromDetails = () => {
    if (!group?.id) return;
    CustomAlert.alert(
      'حذف التحدي (سلة المحذوفات)',
      'هل ترغب في حذف هذا التحدي؟ سيتم إخفاؤه ونقله لسلة المحذوفات مع بقاء بياناته محفوظة في لوحة التحكم.',
      [
        { text: 'إلغاء', style: 'cancel' },
        {
          text: 'نعم، احذف',
          style: 'destructive',
          onPress: async () => {
            if (onDeleteGroup) {
              const res = await onDeleteGroup(group.id);
              if (res.success) {
                onClose();
                setTimeout(() => {
                  CustomAlert.alert('تم الحذف', 'تم حذف التحدي بنجاح.');
                }, 350);
              }
            } else {
              const res = await deleteWalkingGroup(group.id);
              if (res?.success) {
                onClose();
                setTimeout(() => {
                  CustomAlert.alert('تم الحذف', 'تم حذف التحدي بنجاح.');
                }, 350);
              }
            }
          },
        },
      ]
    );
  };

  const handleLeave = () => {
    CustomAlert.alert('تأكيد', 'هل أنت متأكد من رغبتك في مغادرة هذا التحدي؟', [
      { text: 'إلغاء', style: 'cancel' },
      {
        text: 'مغادرة',
        style: 'destructive',
        onPress: async () => {
          if (user?.id) {
            const res = await onLeaveGroup(group.id, user.id);
            if (res.success) onClose();
          }
        },
      },
    ]);
  };

  const handleOpenChat = () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch { }
    if (group?.id) {
      onClose();
      router.push({
        pathname: '/(app)/walking-chat/[id]',
        params: {
          id: String(group.id),
          title: group.name,
        },
      });
    }
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={{ flex: 1, justifyContent: 'flex-end' }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 10 : 0}>
        <View style={styles.overlay}>
          <View style={[styles.content, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {/* Header (Senior UI/UX Redesign: Zero Congestion, Inline Meta & Dedicated Actions Menu) */}
            <View style={styles.header}>
              <TouchableOpacity
                onPress={onClose}
                activeOpacity={0.7}
                hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                style={[
                  styles.closeBtn,
                  { backgroundColor: isDark ? '#27272A' : '#F1F5F9' },
                ]}>
                <IconSymbol name="xmark" size={18} color={colors.text} />
              </TouchableOpacity>

              <View style={styles.headerTitleWrap}>
                <View style={styles.headerTitleRow}>
                  <ThemedText numberOfLines={1} style={styles.headerTitle}>
                    {group.name}
                  </ThemedText>
                  <View
                    style={[
                      styles.headerScopeBadge,
                      {
                        backgroundColor: group.is_public
                          ? isDark
                            ? 'rgba(37, 99, 235, 0.15)'
                            : '#EFF6FF'
                          : isDark
                          ? 'rgba(217, 119, 6, 0.15)'
                          : '#FEF3C7',
                        borderColor: group.is_public
                          ? isDark
                            ? '#3B82F6'
                            : '#BFDBFE'
                          : isDark
                          ? '#D97706'
                          : '#FDE68A',
                      },
                    ]}>
                    <Ionicons
                      name={group.is_public ? 'globe-outline' : 'lock-closed'}
                      size={10}
                      color={
                        group.is_public
                          ? isDark
                            ? '#60A5FA'
                            : '#2563EB'
                          : isDark
                          ? '#FBBF24'
                          : '#D97706'
                      }
                    />
                    <ThemedText
                      style={[
                        styles.headerScopeBadgeText,
                        {
                          color: group.is_public
                            ? isDark
                              ? '#93C5FD'
                              : '#1D4ED8'
                            : isDark
                            ? '#FDE68A'
                            : '#B45309',
                        },
                      ]}>
                      {group.is_public ? 'عام' : 'خاص'}
                    </ThemedText>
                  </View>
                </View>
              </View>

              <View style={styles.headerRightActions}>
                {Boolean(isMember || group.is_public) && (
                  <TouchableOpacity
                    activeOpacity={0.75}
                    onPress={handleOpenChat}
                    style={[
                      styles.headerActionIconBtn,
                      {
                        backgroundColor: isDark ? 'rgba(55,160,92,0.18)' : '#ECFDF5',
                        borderColor: isDark ? 'rgba(55,160,92,0.35)' : '#A7F3D0',
                      },
                    ]}>
                    <Ionicons name="chatbubbles-outline" size={17} color={silaTheme.primary} />
                  </TouchableOpacity>
                )}

                <TouchableOpacity
                  activeOpacity={0.75}
                  onPress={() => {
                    try {
                      Haptics.selectionAsync();
                    } catch { }
                    setShowActionsMenu(true);
                  }}
                  style={[
                    styles.headerActionIconBtn,
                    {
                      backgroundColor: isDark ? '#27272A' : '#F1F5F9',
                      borderColor: isDark ? '#3F3F46' : '#E2E8F0',
                    },
                  ]}>
                  <Ionicons
                    name="ellipsis-horizontal"
                    size={17}
                    color={isDark ? '#E4E4E7' : '#334155'}
                  />
                </TouchableOpacity>
              </View>
            </View>

            {/* Sub-tabs switcher (مثبتة أسفل رأس المودال لسهولة التنقل السريع) */}
            <View style={[styles.tabsRow, { backgroundColor: isDark ? '#202A25' : '#F3F4F6' }]}>
              <TouchableOpacity
                onPress={() => {
                  try {
                    Haptics.selectionAsync();
                  } catch { }
                  setActiveTab('leaderboard');
                }}
                style={[
                  styles.tabBtn,
                  activeTab === 'leaderboard' && [
                    styles.tabBtnActive,
                    { backgroundColor: isDark ? '#2B3832' : '#FFFFFF' },
                  ],
                ]}>
                <ThemedText
                  style={[
                    styles.tabBtnText,
                    { color: activeTab === 'leaderboard' ? silaTheme.primary : colors.icon },
                    activeTab === 'leaderboard' && styles.tabBtnTextActive,
                  ]}>
                  🏆 لوحة الصدارة
                </ThemedText>
              </TouchableOpacity>

              <TouchableOpacity
                onPress={() => {
                  try {
                    Haptics.selectionAsync();
                  } catch { }
                  setActiveTab('members');
                }}
                style={[
                  styles.tabBtn,
                  activeTab === 'members' && [
                    styles.tabBtnActive,
                    { backgroundColor: isDark ? '#2B3832' : '#FFFFFF' },
                  ],
                ]}>
                <ThemedText
                  style={[
                    styles.tabBtnText,
                    { color: activeTab === 'members' ? silaTheme.primary : colors.icon },
                    activeTab === 'members' && styles.tabBtnTextActive,
                  ]}>
                  👥 الأعضاء ({displayMembers.length > 0 ? displayMembers.length : (group.member_count || 1)})
                </ThemedText>
              </TouchableOpacity>
            </View>

            <ScrollView
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
              keyboardDismissMode="interactive"
              automaticallyAdjustKeyboardInsets={Platform.OS === 'ios'}
              contentContainerStyle={styles.scroll}>
              {/* Dormant / Archived Challenge Banner */}
              {group.status === 'archived' && (
                <View
                  style={[
                    styles.archivedBanner,
                    {
                      backgroundColor: isDark ? 'rgba(217, 119, 6, 0.15)' : '#FEF3C7',
                      borderColor: isDark ? '#B45309' : '#FDE68A',
                    },
                  ]}>
                  <View style={styles.archivedBannerTop}>
                    <Ionicons name="archive-outline" size={22} color={isDark ? '#FCD34D' : '#92400E'} />
                    <View style={{ flex: 1, gap: 2 }}>
                      <ThemedText style={[styles.archivedBannerTitle, { color: isDark ? '#FCD34D' : '#92400E' }]}>
                        تحدي مؤرشف وغير نشط
                      </ThemedText>
                      <ThemedText style={[styles.archivedBannerDesc, { color: isDark ? '#FDE68A' : '#78350F' }]}>
                        {group.archived_reason === 'inactivity_auto'
                          ? 'تمت أرشفة هذا التحدي تلقائياً بسبب خمول النشاط وتسجيل 0 خطوة لعدة أيام متتالية.'
                          : 'هذا التحدي مؤرشف حالياً.'}
                      </ThemedText>
                    </View>
                  </View>

                  {isCreatorOrAdmin && (
                    <TouchableOpacity
                      activeOpacity={0.85}
                      disabled={isReactivating}
                      onPress={handleReactivateGroup}
                      style={[styles.reactivateBtn, { backgroundColor: silaTheme.primary }]}>
                      {isReactivating ? (
                        <ActivityIndicator color="#FFFFFF" size="small" />
                      ) : (
                        <ThemedText style={styles.reactivateBtnText}>
                          إعادة تنشيط التحدي الآن ⚡
                        </ThemedText>
                      )}
                    </TouchableOpacity>
                  )}
                </View>
              )}
              {/* Group Banner Info */}
              <LinearGradient
                colors={isDark ? ['#134E4A', '#042F2E'] : ['#ECFDF5', '#D1FAE5']}
                style={styles.infoBanner}>
                <View style={styles.bannerTopRow}>
                  <View
                    style={[
                      styles.typePill,
                      { backgroundColor: isDark ? '#064E3B' : '#059669' },
                    ]}>
                    <ThemedText style={styles.typePillText}>
                      {group.status === 'completed'
                        ? 'مكتمل 🏁'
                        : group.status === 'upcoming'
                          ? 'قريباً ⏳'
                          : 'تحدي عائلي نشط ⚡'}
                    </ThemedText>
                  </View>

                  {group.invite_code && isMember ? (
                    <TouchableOpacity
                      activeOpacity={0.75}
                      onPress={handleShareCode}
                      style={[
                        styles.codeBadge,
                        {
                          backgroundColor: isDark ? 'rgba(6, 78, 59, 0.95)' : '#DCFCE7',
                          borderColor: isDark ? '#10B981' : '#86EFAC',
                        },
                      ]}>
                      <IconSymbol
                        name="square.and.arrow.up"
                        size={12}
                        color={isDark ? '#6EE7B7' : '#047857'}
                      />
                      <ThemedText style={[styles.codeLabel, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                        كود الدعوة:{' '}
                      </ThemedText>
                      <ThemedText style={[styles.codeVal, { color: isDark ? '#FFFFFF' : '#064E3B' }]}>
                        {group.invite_code}
                      </ThemedText>
                    </TouchableOpacity>
                  ) : null}
                </View>

                {group.description && (
                  <ThemedText style={[styles.groupDesc, { color: isDark ? '#CCFBF1' : '#065F46' }]}>
                    {group.description}
                  </ThemedText>
                )}

                {/* Challenge Target Progress (if set) */}
                {group.target_steps && group.target_steps > 0 ? (
                  <View style={styles.targetProgressBox}>
                    <View style={styles.targetProgressHeader}>
                      <ThemedText style={[styles.targetProgressTitle, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                        إجمالي خطوات التحدي: {formatSteps(totalChallengeSteps)} / {formatSteps(group.target_steps)}
                      </ThemedText>
                      <ThemedText style={[styles.targetProgressPercent, { color: isDark ? '#34D399' : '#059669' }]}>
                        {goalPercent}%
                      </ThemedText>
                    </View>
                    <View style={styles.targetProgressBarBg}>
                      <View style={[styles.targetProgressBarFill, { width: `${goalPercent}%` }]} />
                    </View>
                  </View>
                ) : (
                  <View style={styles.targetProgressBox}>
                    <View style={styles.targetProgressHeader}>
                      <ThemedText style={[styles.targetProgressTitle, { color: isDark ? '#A7F3D0' : '#065F46' }]}>
                        إجمالي خطوات التحدي: {formatSteps(totalChallengeSteps)} خطوة
                      </ThemedText>
                      <ThemedText style={[styles.targetProgressPercent, { color: isDark ? '#34D399' : '#059669' }]}>
                        ⚡ صدارة مفتوحة
                      </ThemedText>
                    </View>
                  </View>
                )}

                {/* Celebratory Goal Reached Banner */}
                {Boolean(group.target_steps && group.target_steps > 0 && goalPercent >= 100) && (
                  <View
                    style={[
                      styles.goalReachedCelebrationCard,
                      {
                        backgroundColor: isDark ? '#064E3B66' : '#ECFDF5',
                        borderColor: isDark ? '#10B981' : '#34D399',
                      },
                    ]}>
                    <ThemedText style={styles.celebrationIcon}>🏆</ThemedText>
                    <View style={{ flex: 1 }}>
                      <ThemedText style={[styles.goalReachedTitle, { color: isDark ? '#34D399' : '#065F46' }]}>
                        تهانينا لفرسان التحدي! تم إنجاز الهدف بنجاح 🎉
                      </ThemedText>
                      <ThemedText style={[styles.goalReachedSubtitle, { color: isDark ? '#A7F3D0' : '#047857' }]}>
                        تم الوصول إلى {formatSteps(group.target_steps)} خطوة بجدارة وهمة عالية!
                      </ThemedText>
                    </View>
                  </View>
                )}

                {/* Celebratory Goal Reached by Current User */}
                {Boolean(currentMember?.is_goal_reached || (group.target_steps && typeof (currentMember as any)?.total_steps_in_challenge === 'number' && (currentMember as any).total_steps_in_challenge >= group.target_steps)) && (
                  <View
                    style={[
                      styles.goalReachedCelebrationCard,
                      {
                        backgroundColor: isDark ? '#14532D' : '#DCFCE7',
                        borderColor: isDark ? '#22C55E' : '#16A34A',
                        marginTop: 10,
                      },
                    ]}>
                    <ThemedText style={styles.celebrationIcon}>🥇</ThemedText>
                    <View style={{ flex: 1 }}>
                      <ThemedText style={[styles.goalReachedTitle, { color: isDark ? '#4ADE80' : '#15803D' }]}>
                        مبارك يا بطل! أتممت هدف التحدي 🏆
                      </ThemedText>
                      <ThemedText style={[styles.goalReachedSubtitle, { color: isDark ? '#BBF7D0' : '#166534' }]}>
                        {currentMember?.completion_duration_text
                          ? `حققت هدف التحدي ${currentMember.completion_duration_text} بنجاح!`
                          : `حققت هدف التحدي (${formatSteps(group.target_steps || 0)} خطوة) بجدارة عالية!`}
                      </ThemedText>
                    </View>
                  </View>
                )}

                <View style={styles.statsRow}>
                  <View style={styles.statCol}>
                    <ThemedText style={styles.statLabel}>هدف التحدي</ThemedText>
                    <ThemedText style={styles.statNum}>
                      {group.target_steps && group.target_steps > 0
                        ? `${formatSteps(group.target_steps)}`
                        : 'مفتوح ⚡'}
                    </ThemedText>
                  </View>
                  <View style={styles.statDivider} />
                  <View style={styles.statCol}>
                    <ThemedText style={styles.statLabel}>المشاركون</ThemedText>
                    <ThemedText style={styles.statNum}>
                      {displayMembers.length > 0 ? displayMembers.length : (group.member_count || 1)}
                    </ThemedText>
                  </View>
                  <View style={styles.statDivider} />
                  <View style={styles.statCol}>
                    <ThemedText style={styles.statLabel}>الفترة</ThemedText>
                    <ThemedText numberOfLines={1} style={styles.statNum}>
                      {formatGroupDates(group.start_date, group.end_date)}
                    </ThemedText>
                  </View>
                </View>
              </LinearGradient>

              {/* Tab 1: Group Leaderboard */}
              {activeTab === 'leaderboard' && (
                <View style={styles.tabContent}>
                  {/* Segmented Metric Switcher: [ 🏆 إجمالي النقاط ] | [ 👟 الخطوات ] */}
                  <View style={[styles.metricSwitcherContainer, { backgroundColor: isDark ? '#27272A' : '#F3F4F6', borderColor: colors.border }]}>
                    <TouchableOpacity
                      activeOpacity={0.8}
                      onPress={() => {
                        try { Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); } catch {}
                        setLeaderboardMetric('points');
                      }}
                      style={[
                        styles.metricSwitchBtn,
                        leaderboardMetric === 'points' && [
                          styles.metricSwitchBtnActive,
                          { backgroundColor: silaTheme.primary },
                        ],
                      ]}
                    >
                      <ThemedText style={{ fontSize: 13 }}>🏆</ThemedText>
                      <ThemedText
                        style={[
                          styles.metricSwitchBtnText,
                          { color: leaderboardMetric === 'points' ? '#FFFFFF' : colors.icon },
                          leaderboardMetric === 'points' && styles.metricSwitchBtnTextActive,
                        ]}
                      >
                        إجمالي النقاط
                      </ThemedText>
                    </TouchableOpacity>

                    <TouchableOpacity
                      activeOpacity={0.8}
                      onPress={() => {
                        try { Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); } catch {}
                        setLeaderboardMetric('steps');
                      }}
                      style={[
                        styles.metricSwitchBtn,
                        leaderboardMetric === 'steps' && [
                          styles.metricSwitchBtnActive,
                          { backgroundColor: silaTheme.primary },
                        ],
                      ]}
                    >
                      <ThemedText style={{ fontSize: 13 }}>👟</ThemedText>
                      <ThemedText
                        style={[
                          styles.metricSwitchBtnText,
                          { color: leaderboardMetric === 'steps' ? '#FFFFFF' : colors.icon },
                          leaderboardMetric === 'steps' && styles.metricSwitchBtnTextActive,
                        ]}
                      >
                        الخطوات
                      </ThemedText>
                    </TouchableOpacity>
                  </View>

                  {/* Points Standing & Tonight's Projections Card */}
                  {leaderboardMetric === 'points' && (
                    myStanding ? (
                      <View
                        style={[
                          styles.myStatusCard,
                          {
                            backgroundColor: isDark ? 'rgba(255,255,255,0.04)' : '#FFFFFF',
                            borderColor: isDark ? 'rgba(245, 158, 11, 0.25)' : 'rgba(245, 158, 11, 0.35)',
                          },
                        ]}
                      >
                        <View style={styles.myStatusHeader}>
                          <View style={styles.myStatusHeaderRight}>
                            <ThemedText style={{ fontSize: 13 }}>⚡</ThemedText>
                            <ThemedText style={[styles.myStatusTitle, { color: colors.text }]}>
                              موقفك اليوم وتوقعات الصدارة
                            </ThemedText>
                          </View>

                          {myStanding.today_rank > 0 ? (
                            <View
                              style={[
                                styles.myStatusRankPill,
                                {
                                  backgroundColor: isDark ? 'rgba(16, 185, 129, 0.15)' : '#ECFDF5',
                                  borderColor: isDark ? 'rgba(16, 185, 129, 0.35)' : '#A7F3D0',
                                },
                              ]}
                            >
                              <ThemedText style={[styles.myStatusRankText, { color: isDark ? '#34D399' : '#059669' }]}>
                                المركز #{toEnglishDigits(myStanding.today_rank)} اليوم 🏃
                              </ThemedText>
                            </View>
                          ) : (
                            <View
                              style={[
                                styles.myStatusRankPill,
                                {
                                  backgroundColor: isDark ? '#27272A' : '#F3F4F6',
                                  borderColor: colors.border,
                                },
                              ]}
                            >
                              <ThemedText style={[styles.myStatusRankText, { color: colors.icon }]}>
                                لم تسجل خطوات اليوم ⏳
                              </ThemedText>
                            </View>
                          )}
                        </View>

                        <View style={styles.myStatusMetricsRow}>
                          {/* Metric 1: Today Steps */}
                          <View
                            style={[
                              styles.myStatusMetricBox,
                              {
                                backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                                borderColor: isDark ? '#3F3F46' : '#F3F4F6',
                              },
                            ]}
                          >
                            <View style={styles.myStatusMetricIconWrap}>
                              <ThemedText style={{ fontSize: 13 }}>👟</ThemedText>
                            </View>
                            <ThemedText style={[styles.myStatusMetricValue, { color: colors.text }]}>
                              {formatSteps(myStanding.today_steps)}
                            </ThemedText>
                            <ThemedText style={[styles.myStatusMetricLabel, { color: colors.icon }]}>
                              خطواتك اليوم
                            </ThemedText>
                          </View>

                          {/* Metric 2: Current Points */}
                          <View
                            style={[
                              styles.myStatusMetricBox,
                              {
                                backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                                borderColor: isDark ? '#3F3F46' : '#F3F4F6',
                              },
                            ]}
                          >
                            <View style={styles.myStatusMetricIconWrap}>
                              <ThemedText style={{ fontSize: 13 }}>⭐</ThemedText>
                            </View>
                            <ThemedText style={[styles.myStatusMetricValue, { color: isDark ? '#FCD34D' : '#D97706' }]}>
                              {toEnglishDigits(myStanding.current_points)}
                            </ThemedText>
                            <ThemedText style={[styles.myStatusMetricLabel, { color: colors.icon }]}>
                              نقاطك الحالية
                            </ThemedText>
                          </View>

                          {/* Metric 3: Expected Tonight */}
                          <View
                            style={[
                              styles.myStatusMetricBox,
                              {
                                backgroundColor: isDark ? 'rgba(59, 130, 246, 0.12)' : '#EFF6FF',
                                borderColor: isDark ? 'rgba(59, 130, 246, 0.28)' : '#DBEAFE',
                              },
                            ]}
                          >
                            <View style={styles.myStatusMetricIconWrap}>
                              <ThemedText style={{ fontSize: 13 }}>🌙</ThemedText>
                            </View>
                            <ThemedText style={[styles.myStatusMetricValue, { color: isDark ? '#60A5FA' : '#1D4ED8' }]}>
                              +{toEnglishDigits(myStanding.expected_points)}
                            </ThemedText>
                            <ThemedText style={[styles.myStatusMetricLabel, { color: isDark ? '#93C5FD' : '#2563EB' }]}>
                              المتوقع الليلة
                            </ThemedText>
                          </View>
                        </View>
                      </View>
                    ) : null
                  )}

                  {/* Period Selector Tabs & Sync Button (Only in Steps Mode) */}
                  {leaderboardMetric === 'steps' && (
                    <View style={styles.syncToolbarRow}>
                      <View style={[styles.groupPeriodTabs, { flex: 1, backgroundColor: isDark ? '#27272A' : '#F3F4F6' }]}>
                        {GROUP_PERIODS.map((periodTab) => {
                          const isSelected = selectedPeriod === periodTab.key;
                          return (
                            <TouchableOpacity
                              key={periodTab.key}
                              activeOpacity={0.8}
                              onPress={() => handlePeriodChange(periodTab.key)}
                              style={[
                                styles.groupPeriodTab,
                                isSelected && [
                                  styles.groupPeriodTabActive,
                                  { backgroundColor: isDark ? '#3F3F46' : '#FFFFFF' },
                                ],
                              ]}>
                              <ThemedText
                                style={[
                                  styles.groupPeriodTabText,
                                  { color: isSelected ? silaTheme.primary : colors.icon },
                                  isSelected && styles.groupPeriodTabTextActive,
                                ]}>
                                {periodTab.label}
                              </ThemedText>
                            </TouchableOpacity>
                          );
                        })}
                      </View>

                      <TouchableOpacity
                        activeOpacity={0.8}
                        disabled={isManualSyncing}
                        onPress={handleManualSync}
                        style={[
                          styles.syncNowBtn,
                          {
                            backgroundColor: isDark ? '#064E3B44' : '#ECFDF5',
                            borderColor: silaTheme.primary,
                          },
                        ]}>
                        {isManualSyncing ? (
                          <ActivityIndicator size="small" color={silaTheme.primary} />
                        ) : (
                          <>
                            <IconSymbol name="arrow.clockwise" size={13} color={silaTheme.primary} />
                            <ThemedText style={[styles.syncNowBtnText, { color: silaTheme.primary }]}>
                              مزامنة
                            </ThemedText>
                          </>
                        )}
                      </TouchableOpacity>
                    </View>
                  )}

                  {isLoadingDetails ? (
                    <View style={styles.detailsLoadingContainer}>
                      <ActivityIndicator size="small" color={silaTheme.primary} />
                      <ThemedText style={[styles.loadingText, { color: colors.icon, marginTop: 8, marginBottom: 12 }]}>
                        جاري تحميل لوحة صدارة التحدي...
                      </ThemedText>
                      {[1, 2, 3].map((k) => (
                        <View
                          key={k}
                          style={[
                            styles.skeletonCard,
                            {
                              backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                              borderColor: colors.border,
                            },
                          ]}>
                          <View style={[styles.skeletonAvatar, { backgroundColor: isDark ? '#3F3F46' : '#E5E7EB' }]} />
                          <View style={{ flex: 1, gap: 6 }}>
                            <View
                              style={[
                                styles.skeletonBar,
                                { width: '45%', height: 13, backgroundColor: isDark ? '#3F3F46' : '#E5E7EB' },
                              ]}
                            />
                            <View
                              style={[
                                styles.skeletonBar,
                                { width: '25%', height: 9, backgroundColor: isDark ? '#333A44' : '#F0F2F5' },
                              ]}
                            />
                          </View>
                          <View
                            style={[
                              styles.skeletonBar,
                              { width: 55, height: 14, backgroundColor: isDark ? '#3F3F46' : '#E5E7EB' },
                            ]}
                          />
                        </View>
                      ))}
                    </View>
                  ) : loadingPeriod && leaderboardMetric === 'steps' ? (
                    <View style={styles.periodLoadingBox}>
                      <ActivityIndicator size="small" color={silaTheme.primary} />
                      <ThemedText style={[styles.loadingText, { color: colors.icon }]}>
                        جاري تحديث صدارة التحدي...
                      </ThemedText>
                    </View>
                  ) : activeLeaderboardEntries.length > 0 ? (
                    <>
                      {/* Motivational note if steps are 0 in steps mode */}
                      {leaderboardMetric === 'steps' && totalChallengeSteps === 0 && (
                        <View
                          style={[
                            styles.noticeBanner,
                            {
                              backgroundColor: isDark ? '#1E3A5F' : '#EFF6FF',
                              borderColor: isDark ? '#3B82F6' : '#BFDBFE',
                            },
                          ]}>
                          <IconSymbol name="figure.run" size={18} color="#3B82F6" />
                          <ThemedText style={[styles.noticeText, { color: isDark ? '#BFDBFE' : '#1E40AF' }]}>
                            جميع الأعضاء مضافون للتحدي. ابدأ بالمشي وسجل خطواتك لتتصدر لوحة الشرف!
                          </ThemedText>
                        </View>
                      )}

                      {/* Step Milestone Color Tiers Legend (Only in Steps Mode) */}
                      {leaderboardMetric === 'steps' && (
                        <View style={[styles.tierLegendContainer, { backgroundColor: isDark ? '#1F2937' : '#F9FAFB', borderColor: colors.border }]}>
                          {getStepMilestoneLegend(isDark).map((item) => (
                            <View key={item.key} style={styles.tierLegendItem}>
                              <View style={[styles.tierLegendDot, { backgroundColor: item.dotColor }]} />
                              <ThemedText style={[styles.tierLegendLabel, { color: item.textColor }]}>
                                {item.label}
                              </ThemedText>
                            </View>
                          ))}
                        </View>
                      )}

                      {/* 1. TOP 3 STEPPED PODIUM (المراكز الثلاثة الأولى من الشمال لليمين) */}
                      {activeTopThree.length > 0 && (
                        <View style={styles.podiumWrapper}>
                          <View
                            style={[
                              styles.podiumContainer,
                              { flexDirection: I18nManager.isRTL ? 'row-reverse' : 'row' },
                            ]}
                          >
                            {activeTopThree.map((row) => {
                              const rank = row.rank;
                              const isViewer = Boolean(user?.id && (row.person_id === user.id || row.person?.id === user.id));
                              const isRowAnon = Boolean(group?.is_public && row.is_anonymous && !isViewer);
                              const targetPersonId = row.person?.id || row.person_id;
                              const displayName = isRowAnon ? 'متسابق متخفي' : (row.person?.name || 'عضو في التحدي');
                              const avatarUri = row.person?.avatar || null;

                              let pillarHeight = 84;
                              let avatarSize = 52;
                              let ringColor = '#f59e0b';
                              let ringWidth = 2.5;
                              let gradientColors: [string, string, ...string[]] = ['#fef3c7', '#f59e0b', '#d97706'];
                              let rankNumColor = '#78350f';
                              let rankFontSize = 22;
                              let rankBadgeIcon = '👑';

                              if (rank === 1) {
                                pillarHeight = 84;
                                avatarSize = 52;
                                ringColor = '#f59e0b';
                                ringWidth = 2.5;
                                gradientColors = ['#fef3c7', '#f59e0b', '#d97706'];
                                rankNumColor = '#78350f';
                                rankFontSize = 22;
                                rankBadgeIcon = '👑';
                              } else if (rank === 2) {
                                pillarHeight = 60;
                                avatarSize = 46;
                                ringColor = '#94a3b8';
                                ringWidth = 2;
                                gradientColors = ['#f1f5f9', '#cbd5e1', '#94a3b8'];
                                rankNumColor = '#1e293b';
                                rankFontSize = 18;
                                rankBadgeIcon = '🥈';
                              } else {
                                pillarHeight = 44;
                                avatarSize = 42;
                                ringColor = '#ea580c';
                                ringWidth = 2;
                                gradientColors = ['#ffedd5', '#fed7aa', '#ea580c'];
                                rankNumColor = '#7c2d12';
                                rankFontSize = 16;
                                rankBadgeIcon = '🥉';
                              }

                              const key = row.membership_id
                                ? `podium-m-${row.membership_id}`
                                : row.person_id
                                  ? `podium-p-${row.person_id}`
                                  : `podium-rank-${rank}`;

                              return (
                                <View key={key} style={styles.podiumColumn}>
                                  <TouchableOpacity
                                    activeOpacity={isRowAnon ? 1 : 0.75}
                                    disabled={isRowAnon || !targetPersonId}
                                    onPress={() => targetPersonId && handlePersonPress(targetPersonId)}
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
                                            borderColor: isViewer ? silaTheme.primary : ringColor,
                                            borderWidth: ringWidth,
                                          },
                                        ]}
                                      >
                                        {avatarUri ? (
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
                                        ) : isRowAnon ? (
                                          <AnonymousAvatar
                                            gender={row.person?.gender}
                                            size={avatarSize}
                                            backgroundColor={ringColor}
                                            iconColor="#FFF"
                                          />
                                        ) : (
                                          <View
                                            style={[
                                              styles.podiumAvatarPlaceholder,
                                              {
                                                width: avatarSize,
                                                height: avatarSize,
                                                borderRadius: avatarSize / 2,
                                                backgroundColor: ringColor,
                                              },
                                            ]}
                                          >
                                            <ThemedText style={styles.podiumAvatarInitial}>
                                              {displayName.slice(0, 1) || 'ع'}
                                            </ThemedText>
                                          </View>
                                        )}
                                      </View>

                                      {/* تاج أو أيقونة المركز */}
                                      <View style={styles.podiumCrownBadge}>
                                        <ThemedText style={styles.podiumCrownIcon}>{rankBadgeIcon}</ThemedText>
                                      </View>

                                      {isViewer && (
                                        <View style={styles.mePill}>
                                          <ThemedText style={styles.mePillText}>أنت</ThemedText>
                                        </View>
                                      )}
                                    </View>

                                    <ThemedText
                                      numberOfLines={1}
                                      style={[
                                        styles.podiumPersonName,
                                        { color: colors.text },
                                        rank === 1 && styles.podiumPersonNameFirst,
                                        isViewer && { color: silaTheme.primary, fontFamily: 'Tajawal-ExtraBold' },
                                      ]}
                                    >
                                      {displayName}
                                    </ThemedText>
                                  </TouchableOpacity>

                                  {/* شارة النقاط أو الخطوات */}
                                  {leaderboardMetric === 'points' ? (
                                    <View style={styles.podiumPointsCol}>
                                      <View style={styles.podiumPointsBadge}>
                                        <ThemedText style={styles.podiumPointsIcon}>⭐</ThemedText>
                                        <ThemedText style={styles.podiumPointsNum}>
                                          {toEnglishDigits((row as any).total_points ?? 0)}
                                        </ThemedText>
                                        <ThemedText style={styles.podiumPointsLabel}>نقاط</ThemedText>
                                      </View>

                                      {(row as any).expected_points > 0 ? (
                                        <View
                                          style={[
                                            styles.podiumExpectedPill,
                                            {
                                              backgroundColor: isDark ? 'rgba(59, 130, 246, 0.18)' : '#EFF6FF',
                                              borderColor: isDark ? 'rgba(59, 130, 246, 0.35)' : '#BFDBFE',
                                            },
                                          ]}
                                        >
                                          <ThemedText style={[styles.podiumExpectedText, { color: isDark ? '#93C5FD' : '#1D4ED8' }]}>
                                            +{toEnglishDigits((row as any).expected_points)} متوقعة 🌙
                                          </ThemedText>
                                        </View>
                                      ) : null}

                                      {(row as any).today_steps > 0 ? (
                                        <ThemedText style={[styles.podiumTodayStepsText, { color: colors.icon }]}>
                                          👟 {formatSteps((row as any).today_steps)}
                                        </ThemedText>
                                      ) : null}
                                    </View>
                                  ) : (
                                    <View style={styles.podiumStepsBadge}>
                                      <ThemedText style={styles.podiumStepsIcon}>👟</ThemedText>
                                      <AnimatedCounter
                                        value={row.total_steps}
                                        style={styles.podiumStepsNum}
                                      />
                                      <ThemedText style={styles.podiumStepsLabel}>خطوة</ThemedText>
                                    </View>
                                  )}

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
                        </View>
                      )}

                      {/* 2. CONTENDERS TABLE (المنافسون على الصدارة - الترتيب المتتالي Dense Ranking) */}
                      {activeRemainingEntries.length > 0 && (
                        <View style={styles.contendersSection}>
                          <View style={styles.contendersHeader}>
                            <View style={styles.contendersHeaderTitleWrap}>
                              <IconSymbol name="list.number" size={13} color={silaTheme.primary} />
                              <ThemedText style={styles.contendersHeaderTitle}>المنافسون على الصدارة</ThemedText>
                              <ThemedText style={styles.contendersHeaderCount}>
                                ({toEnglishDigits(activeRemainingEntries.length)})
                              </ThemedText>
                            </View>
                            <ThemedText style={styles.contendersHeaderCols}>
                              {leaderboardMetric === 'points' ? 'النقاط' : 'الخطوات'}
                            </ThemedText>
                          </View>

                          <ScrollView
                            style={styles.contendersScroll}
                            contentContainerStyle={styles.contendersList}
                            nestedScrollEnabled
                            showsVerticalScrollIndicator
                            scrollEventThrottle={16}
                          >
                            {activeRemainingEntries.map((c, cIdx) => {
                              const cRank = c.rank;
                              const cIsViewer = Boolean(user?.id && (c.person_id === user.id || c.person?.id === user.id));
                              const cIsAnon = Boolean(group?.is_public && c.is_anonymous && !cIsViewer);
                              const cTargetId = c.person?.id || c.person_id;
                              const cDisplayName = cIsAnon ? 'متسابق متخفي' : (c.person?.name || 'عضو في التحدي');
                              const cAvatarUri = c.person?.avatar || null;
                              const tierConfig = getStepTierConfig(c.total_steps, isDark);
                              const hasSteps = c.total_steps > 0;
                              const streak = (c as any).streak_10k ?? 0;
                              const isGoalDone = Boolean(
                                (c as any).is_goal_reached ||
                                (group?.target_steps && c.total_steps >= group.target_steps)
                              );

                              const cKey = c.membership_id
                                ? `contender-m-${c.membership_id}`
                                : c.person_id
                                  ? `contender-p-${c.person_id}`
                                  : `contender-idx-${cIdx}`;

                              return (
                                <View
                                  key={cKey}
                                  style={[
                                    styles.contenderRow,
                                    {
                                      backgroundColor: cIsViewer
                                        ? isDark
                                          ? 'rgba(16, 185, 129, 0.12)'
                                          : '#ECFDF5'
                                        : isDark
                                          ? 'rgba(255,255,255,0.03)'
                                          : 'rgba(0,0,0,0.02)',
                                      borderColor: cIsViewer
                                        ? silaTheme.primary
                                        : isDark
                                          ? 'rgba(255,255,255,0.06)'
                                          : 'rgba(0,0,0,0.04)',
                                    },
                                  ]}
                                >
                                  {/* اليمين: الترتيب + الصورة + الاسم + شارة الستريك والهدف */}
                                  <TouchableOpacity
                                    activeOpacity={cIsAnon ? 1 : 0.7}
                                    disabled={cIsAnon || !cTargetId}
                                    onPress={() => cTargetId && handlePersonPress(cTargetId)}
                                    style={styles.contenderRightSide}
                                  >
                                    <View
                                      style={[
                                        styles.contenderRankBadge,
                                        cIsViewer && styles.contenderRankBadgeMe,
                                        cRank === 0 && { backgroundColor: isDark ? '#334155' : '#e2e8f0', width: 28 },
                                      ]}
                                    >
                                      <ThemedText
                                        style={[
                                          styles.contenderRankText,
                                          cIsViewer && styles.contenderRankTextMe,
                                          cRank === 0 && { fontSize: 8.5 },
                                        ]}
                                      >
                                        {cRank > 0 ? `#${toEnglishDigits(cRank)}` : '⏳ 0'}
                                      </ThemedText>
                                    </View>

                                    <View style={styles.contenderAvatarWrap}>
                                      {cAvatarUri ? (
                                        <Image
                                          source={{ uri: cAvatarUri }}
                                          style={styles.contenderAvatarImg}
                                          contentFit="cover"
                                          transition={200}
                                          cachePolicy="memory-disk"
                                        />
                                      ) : cIsAnon ? (
                                        <AnonymousAvatar
                                          gender={c.person?.gender}
                                          size={28}
                                          backgroundColor={tierConfig.barColor}
                                          iconColor="#FFF"
                                        />
                                      ) : (
                                        <View
                                          style={[
                                            styles.contenderAvatarPlaceholder,
                                            { backgroundColor: tierConfig.barColor },
                                          ]}
                                        >
                                          <ThemedText style={styles.contenderAvatarInitial}>
                                            {cDisplayName.slice(0, 1) || 'ع'}
                                          </ThemedText>
                                        </View>
                                      )}
                                    </View>

                                    <View style={styles.contenderNameBlock}>
                                      <View style={styles.contenderNameRow}>
                                        <ThemedText
                                          numberOfLines={1}
                                          style={[
                                            styles.contenderName,
                                            { color: colors.text },
                                            cIsViewer && { color: silaTheme.primary, fontFamily: 'Tajawal-ExtraBold' },
                                          ]}
                                        >
                                          {cDisplayName}
                                        </ThemedText>

                                        {cIsViewer && (
                                          <View style={styles.mePillSmall}>
                                            <ThemedText style={styles.mePillSmallText}>أنت</ThemedText>
                                          </View>
                                        )}

                                        {streak > 0 && (
                                          <View style={styles.contenderStreakPill}>
                                            <ThemedText style={styles.contenderStreakIcon}>🔥</ThemedText>
                                            <ThemedText style={styles.contenderStreakText}>
                                              {formatStreakDays(streak)}
                                            </ThemedText>
                                          </View>
                                        )}
                                      </View>

                                      {isGoalDone && (
                                        <View style={styles.contenderGoalBadge}>
                                          <ThemedText style={styles.contenderGoalBadgeText}>
                                            🏆 أتم الهدف {(c as any).completion_duration_text ? `(${(c as any).completion_duration_text})` : ''}
                                          </ThemedText>
                                        </View>
                                      )}
                                    </View>
                                  </TouchableOpacity>

                                  {/* اليسار: النقاط أو الخطوات */}
                                  <View style={styles.contenderLeftSide}>
                                    {leaderboardMetric === 'points' ? (
                                      <View style={styles.contenderPointsBlock}>
                                        <View style={styles.contenderPointsRow}>
                                          {(c as any).total_points > 0 ? (
                                            <View style={[styles.contenderPointsBadge, { backgroundColor: isDark ? 'rgba(245, 158, 11, 0.15)' : '#FEF3C7', borderColor: isDark ? '#B45309' : '#FDE68A' }]}>
                                              <ThemedText style={{ fontSize: 9 }}>⭐</ThemedText>
                                              <ThemedText style={[styles.contenderPointsNum, { color: isDark ? '#FCD34D' : '#92400E' }]}>
                                                {toEnglishDigits((c as any).total_points)}
                                              </ThemedText>
                                              <ThemedText style={[styles.contenderPointsLabel, { color: isDark ? '#FCD34D' : '#B45309' }]}>
                                                نقاط
                                              </ThemedText>
                                            </View>
                                          ) : (
                                            <View style={[styles.contenderZeroBadge, { backgroundColor: isDark ? '#1e293b' : '#f1f5f9', borderColor: colors.border }]}>
                                              <ThemedText style={[styles.contenderZeroText, { color: colors.icon }]}>0 نقطة</ThemedText>
                                            </View>
                                          )}

                                          {(c as any).expected_points > 0 && (
                                            <View
                                              style={[
                                                styles.contenderExpectedBadge,
                                                {
                                                  backgroundColor: isDark ? 'rgba(59, 130, 246, 0.16)' : '#EFF6FF',
                                                  borderColor: isDark ? 'rgba(59, 130, 246, 0.35)' : '#DBEAFE',
                                                },
                                              ]}
                                            >
                                              <ThemedText style={[styles.contenderExpectedText, { color: isDark ? '#93C5FD' : '#1D4ED8' }]}>
                                                +{toEnglishDigits((c as any).expected_points)} 🌙
                                              </ThemedText>
                                            </View>
                                          )}
                                        </View>

                                        {(c as any).today_steps > 0 && (
                                          <ThemedText style={[styles.contenderTodayStepsSub, { color: colors.icon }]}>
                                            👟 {formatSteps((c as any).today_steps)} اليوم
                                          </ThemedText>
                                        )}
                                      </View>
                                    ) : (
                                      hasSteps ? (
                                        <View style={[styles.contenderStepsBadge, { backgroundColor: isDark ? 'rgba(255,255,255,0.06)' : '#f8fafc', borderColor: tierConfig.borderColor }]}>
                                          <AnimatedCounter
                                            value={c.total_steps}
                                            style={[styles.contenderStepsNum, { color: tierConfig.textColor }]}
                                          />
                                          <ThemedText style={[styles.contenderStepsLabel, { color: colors.icon }]}>
                                            خطوة
                                          </ThemedText>
                                        </View>
                                      ) : (
                                        <View style={[styles.contenderZeroBadge, { backgroundColor: isDark ? '#1e293b' : '#f1f5f9', borderColor: colors.border }]}>
                                          <ThemedText style={[styles.contenderZeroText, { color: colors.icon }]}>0 خطوة</ThemedText>
                                        </View>
                                      )
                                    )}
                                  </View>
                                </View>
                              );
                            })}
                          </ScrollView>
                        </View>
                      )}
                    </>
                  ) : (
                    <View style={styles.emptyState}>
                      <ThemedText style={[styles.emptyText, { color: colors.icon }]}>
                        {leaderboardMetric === 'points'
                          ? 'لم يتم تسجيل نقاط في هذا التحدي بعد'
                          : 'لم يتم تسجيل خطوات في هذا التحدي بعد'}
                      </ThemedText>
                    </View>
                  )}
                </View>
              )}

              {/* Tab 2: Members List */}
              {activeTab === 'members' && (
                <View style={styles.tabContent}>
                  {/* Only creator/admin can directly add family members to the challenge */}
                  {isCreatorOrAdmin && (
                    <TouchableOpacity
                      activeOpacity={0.85}
                      onPress={() => setShowInviteModal(true)}
                      style={[styles.inviteBtn, { borderColor: silaTheme.primary }]}>
                      <IconSymbol name="plus.circle.fill" size={18} color={silaTheme.primary} />
                      <ThemedText style={styles.inviteBtnText}>+ إضافة أعضاء من العائلة للتحدي</ThemedText>
                    </TouchableOpacity>
                  )}

                  {isLoadingDetails ? (
                    <View style={styles.detailsLoadingContainer}>
                      <ActivityIndicator size="small" color={silaTheme.primary} />
                      <ThemedText style={[styles.loadingText, { color: colors.icon, marginVertical: 8 }]}>
                        جاري تحميل قائمة المشاركين...
                      </ThemedText>
                      {[1, 2, 3].map((k) => (
                        <View
                          key={k}
                          style={[
                            styles.skeletonCard,
                            {
                              backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                              borderColor: colors.border,
                            },
                          ]}>
                          <View style={[styles.skeletonAvatar, { backgroundColor: isDark ? '#3F3F46' : '#E5E7EB' }]} />
                          <View style={{ flex: 1, gap: 6 }}>
                            <View
                              style={[
                                styles.skeletonBar,
                                { width: '40%', height: 13, backgroundColor: isDark ? '#3F3F46' : '#E5E7EB' },
                              ]}
                            />
                            <View
                              style={[
                                styles.skeletonBar,
                                { width: '20%', height: 9, backgroundColor: isDark ? '#333A44' : '#F0F2F5' },
                              ]}
                            />
                          </View>
                        </View>
                      ))}
                    </View>
                  ) : displayMembers.length > 0 ? (
                    displayMembers.map((m, index) => {
                      const memberPersonId = Number(m.person_id || m.person?.id || 0);
                      const isSelf = user?.id && memberPersonId === Number(user.id);
                      const canKickMember = isCreatorOrAdmin && !isSelf && m.role !== 'admin' && memberPersonId > 0;
                      const rank = index + 1;
                      const memberSteps = Number(m.total_steps_in_challenge) || 0;

                      return (
                        <View
                          key={m.id || m.person_id || m.person?.id}
                          style={[
                            styles.memberRow,
                            {
                              backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                              borderColor: colors.border,
                            },
                          ]}>
                          {/* شارة الترتيب التنافسي */}
                          <View
                            style={[
                              styles.memberRankBadge,
                              rank === 1
                                ? (isDark ? styles.memberRankBadgeGoldDark : styles.memberRankBadgeGoldLight)
                                : rank === 2
                                ? (isDark ? styles.memberRankBadgeSilverDark : styles.memberRankBadgeSilverLight)
                                : rank === 3
                                ? (isDark ? styles.memberRankBadgeBronzeDark : styles.memberRankBadgeBronzeLight)
                                : (isDark ? styles.memberRankBadgeDefaultDark : styles.memberRankBadgeDefaultLight),
                            ]}>
                            <ThemedText
                              style={[
                                styles.memberRankText,
                                rank === 1
                                  ? { color: isDark ? '#FDE047' : '#B45309' }
                                  : rank === 2
                                  ? { color: isDark ? '#E2E8F0' : '#475569' }
                                  : rank === 3
                                  ? { color: isDark ? '#FDBA74' : '#C2410C' }
                                  : { color: isDark ? '#9CA3AF' : '#6B7280' },
                              ]}>
                              {rank === 1 ? '🥇 1' : rank === 2 ? '🥈 2' : rank === 3 ? '🥉 3' : `#${rank}`}
                            </ThemedText>
                          </View>

                          <TouchableOpacity
                            activeOpacity={(memberPersonId > 0 && !(m.is_anonymous && !isSelf && !isCreatorOrAdmin)) ? 0.75 : 1}
                            disabled={memberPersonId <= 0 || Boolean(m.is_anonymous && !isSelf && !isCreatorOrAdmin)}
                            onPress={() => {
                              if (memberPersonId > 0 && !(m.is_anonymous && !isSelf && !isCreatorOrAdmin)) {
                                handlePersonPress(memberPersonId);
                              }
                            }}
                            style={styles.avatarMini}>
                            {m.is_anonymous && !isSelf && !isCreatorOrAdmin ? (
                              <AnonymousAvatar gender={(m.person?.gender as any) || 'male'} size={38} />
                            ) : m.person?.avatar ? (
                              <Image source={{ uri: m.person.avatar }} style={styles.avatarImg} />
                            ) : (
                              <View style={[styles.avatarMiniPlaceholder, { backgroundColor: '#3B82F6' }]}>
                                <ThemedText style={{ color: '#FFF', fontFamily: 'Tajawal-Bold', fontSize: 12 }}>
                                  {m.person?.name?.slice(0, 1) || 'ع'}
                                </ThemedText>
                              </View>
                            )}
                          </TouchableOpacity>

                          <View style={{ flex: 1 }}>
                            <ThemedText numberOfLines={1} style={styles.memberName}>
                              {m.person?.name || 'عضو في التحدي'}
                            </ThemedText>
                            <ThemedText style={[styles.roleText, { color: colors.icon }]}>
                              {m.role === 'admin' ? '⭐ منشئ / مدير التحدي' : 'عضو مشارك'}
                            </ThemedText>
                            {Boolean(m.is_goal_reached || (group.target_steps && typeof m.total_steps_in_challenge === 'number' && m.total_steps_in_challenge >= group.target_steps)) && (
                              <View style={[styles.finisherBadge, { marginTop: 4 }]}>
                                <ThemedText style={styles.finisherBadgeText}>
                                  🏆 أتم الهدف {m.completion_duration_text ? `(${m.completion_duration_text})` : ''}
                                </ThemedText>
                              </View>
                            )}
                          </View>

                          <ThemedText
                            style={[
                              styles.memberSteps,
                              {
                                color: memberSteps > 0
                                  ? (isDark ? '#34D399' : '#059669')
                                  : (isDark ? '#71717A' : '#9CA3AF'),
                              },
                            ]}>
                            {formatSteps(memberSteps)} خطوة
                          </ThemedText>

                          {/* زر استبعاد/طرد العضو لمدير التحدي */}
                          {canKickMember && (
                            <TouchableOpacity
                              activeOpacity={0.75}
                              disabled={removingMemberId === memberPersonId}
                              onPress={() =>
                                handleRemoveMemberPress(memberPersonId, m.person?.name || 'العضو')
                              }
                              style={[
                                styles.kickMemberBtn,
                                {
                                  backgroundColor: isDark ? '#451A1A' : '#FEE2E2',
                                  borderColor: isDark ? '#7F1D1D' : '#FECACA',
                                },
                              ]}>
                              {removingMemberId === memberPersonId ? (
                                <ActivityIndicator size="small" color="#EF4444" />
                              ) : (
                                <>
                                  <IconSymbol name="xmark.circle.fill" size={14} color="#EF4444" />
                                  <ThemedText
                                    style={[
                                      styles.kickMemberText,
                                      { color: isDark ? '#FCA5A5' : '#DC2626' },
                                    ]}>
                                    استبعاد
                                  </ThemedText>
                                </>
                              )}
                            </TouchableOpacity>
                          )}
                        </View>
                      );
                    })
                  ) : (
                    <View style={styles.emptyState}>
                      <ThemedText style={[styles.emptyText, { color: colors.icon }]}>
                        لا يوجد أعضاء في هذه المجموعة بعد
                      </ThemedText>
                    </View>
                  )}
                </View>
              )}
              {/* Challenge Chat Shortcut */}
              {Boolean(isMember || group.is_public) && (
                <TouchableOpacity
                  activeOpacity={0.8}
                  onPress={handleOpenChat}
                  style={[
                    styles.chatShortcutBtn,
                    {
                      backgroundColor: isDark ? 'rgba(55,160,92,0.12)' : '#ECFDF5',
                      borderColor: isDark ? 'rgba(55,160,92,0.3)' : '#A7F3D0',
                    },
                  ]}>
                  <ThemedText style={{ fontSize: 20 }}>💬</ThemedText>
                  <View style={{ flex: 1, gap: 2 }}>
                    <ThemedText style={[styles.chatShortcutTitle, { color: isDark ? '#6EE7B7' : '#065F46' }]}>
                      غرفة دردشة التحدي المباشرة
                    </ThemedText>
                    <ThemedText style={[styles.chatShortcutSub, { color: isDark ? '#A7F3D0' : '#047857' }]}>
                      تواصل مع المشاركين وشارك إنجازاتك اليومية في التحدي
                    </ThemedText>
                  </View>
                  <IconSymbol name="chevron.backward" size={16} color={silaTheme.primary} />
                </TouchableOpacity>
              )}

              {/* Privacy & Challenge Settings Shortcut */}
              {isMember && (
                <TouchableOpacity
                  activeOpacity={0.8}
                  onPress={() => setShowPrivacyModal(true)}
                  style={[
                    styles.settingsShortcutBtn,
                    {
                      backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                      borderColor: isDark ? '#374151' : '#E5E7EB',
                    },
                  ]}>
                  <ThemedText style={{ fontSize: 20 }}>🛡️</ThemedText>
                  <View style={{ flex: 1, gap: 2 }}>
                    <ThemedText style={styles.settingsShortcutTitle}>
                      إعدادات الخصوصية والتحكم في التحدي
                    </ThemedText>
                    <ThemedText style={[styles.settingsShortcutSub, { color: colors.icon }]}>
                      {group?.is_public
                        ? (memberSettings?.is_anonymous ? 'وضع المتخفي مُفعّل' : 'الظهور معلن للجميع')
                        : 'الظهور معلن لجميع الأعضاء'} • إدارة المغادرة والإشعارات
                    </ThemedText>
                  </View>
                  <IconSymbol name="chevron.backward" size={16} color={colors.icon} />
                </TouchableOpacity>
              )}
            </ScrollView>

            {/* ==================================================== */}
            {/* مودال دعوة وإضافة أفراد العائلة للتحدي فوراً (شجرة صلة + البحث) */}
            {/* ==================================================== */}
            {isCreatorOrAdmin && showInviteModal && (
              <ChallengeInvitePickerModal
                visible={showInviteModal}
                onClose={() => {
                  Keyboard.dismiss();
                  setShowInviteModal(false);
                }}
                selectedMembers={selectedInvitees}
                onTogglePerson={handleToggleInvitee}
                onRemovePerson={handleRemoveInvitee}
                onClearAll={() => setSelectedInvitees([])}
                onConfirm={handleSendInvitations}
                confirmLabel={
                  selectedInvitees.length > 0
                    ? `إضافة (${selectedInvitees.length}) أعضاء للتحدي فوراً 🚀`
                    : 'اختر أفراداً من العائلة للإضافة 👆'
                }
                isSubmitting={isInviting}
                existingMemberIds={Array.from(existingPersonIds)}
                title="إضافة أفراد للتحدي 👥"
              />
            )}
          </View>
        </View>
      </KeyboardAvoidingView>

      {/* Edit Challenge Modal */}
      {group && (
        <EditGroupModal
          visible={showEditModal}
          group={group}
          onClose={() => setShowEditModal(false)}
          onSuccess={(updated) => {
            setLocalUpdatedGroup(updated);
            if (onGroupUpdated) {
              onGroupUpdated(updated);
            }
            if (selectedGroupId && onLoadLeaderboardPeriod) {
              void onLoadLeaderboardPeriod(selectedGroupId, selectedPeriod);
            }
          }}
          onDelete={(gId) => {
            setShowEditModal(false);
            onClose();
          }}
        />
      )}

      {/* Challenge Privacy & Settings Modal */}
      {group && (
        <ChallengePrivacySettingsModal
          visible={showPrivacyModal}
          groupId={group.id}
          groupName={group.name}
          isCreator={Boolean((group as any).is_creator || (user?.id && (group as any).created_by_person_id === user.id))}
          isPublic={Boolean(group.is_public)}
          initialSettings={memberSettings}
          onClose={() => setShowPrivacyModal(false)}
          onSaveSettings={handleSavePrivacySettings}
          onLeaveChallenge={() => {
            setShowPrivacyModal(false);
            handleLeave();
          }}
        />
      )}

      {/* Challenge Actions & Management Sheet */}
      {group && (
        <GroupActionsSheet
          visible={showActionsMenu}
          group={group}
          isDark={isDark}
          colors={colors}
          isCreatorOrAdmin={isCreatorOrAdmin}
          isMember={isMember}
          memberSettings={memberSettings}
          onClose={() => setShowActionsMenu(false)}
          onEdit={() => setShowEditModal(true)}
          onInvite={() => setShowInviteModal(true)}
          onShareCode={() => {
            void handleShareCode();
          }}
          onOpenPrivacy={() => setShowPrivacyModal(true)}
          onLeave={() => handleLeave()}
          onDelete={() => handleDeleteFromDetails()}
        />
      )}
    </Modal>
  );
}

