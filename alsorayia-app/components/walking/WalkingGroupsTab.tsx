import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, silaTheme } from '@/constants/theme';
import { Ionicons } from '@expo/vector-icons';
import type {
  CreateGroupPayload,
  LeaderboardData,
  LeaderboardPeriod,
  WalkingGroupDetail,
  WalkingGroupItem,
} from '@/lib/api/walkingApi';
import * as Haptics from 'expo-haptics';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

import { CreateGroupModal } from './CreateGroupModal';
import { EditGroupModal } from './EditGroupModal';
import { GroupDetailsModal } from './GroupDetailsModal';
import { formatGroupDates, formatSteps } from '@/lib/walking/formatters';

type Props = {
  groups: WalkingGroupItem[];
  isLoading: boolean;
  activeGroupDetail: WalkingGroupDetail | null;
  groupLeaderboard: LeaderboardData | null;
  todaySteps?: number;
  initialGroupId?: number | null;
  openChat?: boolean;
  onSyncNow?: () => Promise<boolean>;
  onLoadGroupDetails: (groupId: number, period?: LeaderboardPeriod) => Promise<void>;
  onLoadGroupLeaderboardPeriod?: (groupId: number, period: LeaderboardPeriod) => Promise<void>;
  onCreateGroup: (payload: CreateGroupPayload) => Promise<{ success: boolean; message?: string }>;
  onJoinGroup: (groupId: number) => Promise<{ success: boolean; message?: string }>;
  onJoinGroupByCode?: (inviteCode: string) => Promise<{ success: boolean; message?: string }>;
  onInviteMember: (groupId: number, personIds: number | number[]) => Promise<{ success: boolean; message?: string }>;
  onLeaveGroup: (groupId: number, personId: number) => Promise<{ success: boolean; message?: string }>;
  onDeleteGroup?: (groupId: number) => Promise<{ success: boolean; message?: string }>;
  onRefreshGroups?: () => void | Promise<void>;
};

function JoinGroupByCodeModal({
  visible,
  onClose,
  onSubmit,
}: {
  visible: boolean;
  onClose: () => void;
  onSubmit?: (code: string) => Promise<{ success: boolean; message?: string }>;
}) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const handleJoin = async () => {
    const clean = code.trim().toUpperCase();
    if (!clean) {
      setErrorMsg('يرجى إدخال رمز كود التحدي أولاً');
      return;
    }
    if (!onSubmit) return;
    setLoading(true);
    setErrorMsg(null);
    try {
      const res = await onSubmit(clean);
      if (res.success) {
        setCode('');
        onClose();
        setTimeout(() => {
          CustomAlert.alert('🎉 مبروك!', res.message || 'تم انضمامك للتحدي بنجاح!');
        }, 350);
      } else {
        setErrorMsg(res.message || 'رمز التحدي غير صحيح أو انتهت صلاحيته');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal visible={visible} animationType="fade" transparent onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 20 : 0}>
        <View style={styles.modalOverlay}>
          <View style={[styles.modalCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={styles.modalHeader}>
              <TouchableOpacity onPress={onClose} style={styles.modalCloseBtn}>
                <IconSymbol name="xmark" size={18} color={colors.icon} />
              </TouchableOpacity>
              <ThemedText style={styles.modalTitle}>الانضمام إلى تحدي 🔑</ThemedText>
              <View style={{ width: 32 }} />
            </View>

            <ThemedText style={[styles.modalSub, { color: colors.icon }]}>
              أدخل كود التحدي المكون من حروف وأرقام للانضمام فوراً للمجموعة والتنافس مع أفراد العائلة.
            </ThemedText>

            <View style={[styles.inputBox, { backgroundColor: isDark ? '#27272A' : '#F9FAFB', borderColor: isDark ? '#3F3F46' : '#E5E7EB' }]}>
              <TextInput
                value={code}
                onChangeText={(txt) => {
                  setCode(txt.toUpperCase());
                  if (errorMsg) setErrorMsg(null);
                }}
                placeholder="مثال: WALK-8921"
                placeholderTextColor={colors.icon}
                autoCapitalize="characters"
                autoCorrect={false}
                style={[styles.codeInput, { color: colors.text }]}
              />
            </View>

            {errorMsg ? (
              <ThemedText style={styles.errorText}>⚠️ {errorMsg}</ThemedText>
            ) : null}

            <TouchableOpacity
              activeOpacity={0.85}
              disabled={loading}
              onPress={handleJoin}
              style={[styles.submitBtn, { backgroundColor: silaTheme.primary }]}>
              {loading ? (
                <ActivityIndicator color="#FFFFFF" />
              ) : (
                <ThemedText style={styles.submitBtnText}>انضمام للتحدي الآن ⚡</ThemedText>
              )}
            </TouchableOpacity>
          </View>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

export function WalkingGroupsTab({
  groups,
  isLoading,
  activeGroupDetail,
  groupLeaderboard,
  todaySteps,
  initialGroupId,
  openChat,
  onSyncNow,
  onLoadGroupDetails,
  onLoadGroupLeaderboardPeriod,
  onCreateGroup,
  onJoinGroup,
  onJoinGroupByCode,
  onInviteMember,
  onLeaveGroup,
  onDeleteGroup,
  onRefreshGroups,
}: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';
  const router = useRouter();

  const [localGroups, setLocalGroups] = useState<WalkingGroupItem[]>(groups);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showJoinCodeModal, setShowJoinCodeModal] = useState(false);
  const [selectedGroupId, setSelectedGroupId] = useState<number | null>(null);
  const [modalInitialTab, setModalInitialTab] = useState<'leaderboard' | 'members'>('leaderboard');
  const [editingCardGroup, setEditingCardGroup] = useState<WalkingGroupItem | null>(null);
  const [filterType, setFilterType] = useState<'all' | 'my' | 'completed' | 'archived'>('all');
  const handledInitialGroupIdRef = React.useRef<number | null>(null);

  React.useEffect(() => {
    setLocalGroups(groups);
  }, [groups]);

  React.useEffect(() => {
    if (initialGroupId && Number(initialGroupId) > 0) {
      const gId = Number(initialGroupId);
      if (handledInitialGroupIdRef.current === gId) return;
      handledInitialGroupIdRef.current = gId;

      if (openChat) {
        const found = groups.find((g) => g.id === gId);
        router.push({
          pathname: '/(app)/walking-chat/[id]',
          params: { id: String(gId), title: found?.name || 'دردشة التحدي' },
        });
        return;
      }
      setSelectedGroupId(gId);
      setModalInitialTab('leaderboard');
      void onLoadGroupDetails(gId, 'today');
    }
  }, [initialGroupId, openChat, onLoadGroupDetails, groups, router]);

  const todayStr = useMemo(() => new Date().toISOString().split('T')[0], []);

  const isGroupActive = React.useCallback((g: WalkingGroupItem) => {
    return g.status === 'active' && (!g.end_date || g.end_date >= todayStr);
  }, [todayStr]);

  const isGroupCompleted = React.useCallback((g: WalkingGroupItem) => {
    return g.status === 'completed' || (g.end_date != null && g.end_date < todayStr);
  }, [todayStr]);

  const counts = useMemo(() => {
    return {
      all: localGroups.filter(isGroupActive).length,
      my: localGroups.filter((g) => (g.is_member || g.is_creator) && isGroupActive(g)).length,
      completed: localGroups.filter(isGroupCompleted).length,
      archived: localGroups.filter((g) => g.status === 'archived').length,
    };
  }, [localGroups, isGroupActive, isGroupCompleted]);

  const filteredGroups = useMemo(() => {
    if (filterType === 'archived') {
      return localGroups.filter((g) => g.status === 'archived');
    }
    if (filterType === 'completed') {
      return localGroups.filter(isGroupCompleted);
    }
    if (filterType === 'my') {
      return localGroups.filter((g) => (g.is_member || g.is_creator) && isGroupActive(g));
    }
    return localGroups.filter(isGroupActive);
  }, [localGroups, filterType, isGroupActive, isGroupCompleted]);

  const filterTabs: {
    key: 'all' | 'my' | 'completed' | 'archived';
    label: string;
    icon: string;
    count: number;
  }[] = [
    { key: 'all', label: 'النشطة والجارية', icon: 'figure.run', count: counts.all },
    { key: 'my', label: 'تحدياتي', icon: 'person.crop.circle.fill', count: counts.my },
    { key: 'completed', label: 'المكتملة والسابقة', icon: 'flag.checkered', count: counts.completed },
    { key: 'archived', label: 'المؤرشفة والخاملة', icon: 'archivebox.fill', count: counts.archived },
  ];

  const handleGroupEdited = (updated: WalkingGroupItem) => {
    setLocalGroups((prev) => prev.map((g) => (g.id === updated.id ? { ...g, ...updated } : g)));
    if (onRefreshGroups) {
      void onRefreshGroups();
    }
  };

  const selectedGroupItem = useMemo(() => {
    if (!selectedGroupId) return null;
    return localGroups.find((g) => g.id === selectedGroupId) || null;
  }, [localGroups, selectedGroupId]);

  const handleOpenChat = (group: WalkingGroupItem) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    router.push({
      pathname: '/(app)/walking-chat/[id]',
      params: { id: String(group.id), title: group.name },
    });
  };
  const handleOpenGroup = (group: WalkingGroupItem) => {
    setModalInitialTab('leaderboard');
    try {
      Haptics.selectionAsync();
    } catch { }
    setSelectedGroupId(group.id);
    void onLoadGroupDetails(group.id, 'today');
    // مزامنة هادئة للخطوات في الخلفية دون تأخير فتح التحدي
    if (onSyncNow) {
      void onSyncNow().catch(() => {});
    }
  };

  return (
    <View style={styles.container}>
      {/* Top Dual Action Bar */}
      <View style={styles.topActionsRow}>
        <TouchableOpacity
          activeOpacity={0.88}
          onPress={() => {
            try {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            } catch { }
            setShowCreateModal(true);
          }}
          style={styles.actionBtnHalf}>
          <LinearGradient
            colors={isDark ? ['#065F46', '#047857'] : ['#059669', '#10B981']}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 0 }}
            style={styles.actionGradient}>
            <IconSymbol name="plus.circle.fill" size={18} color="#FFFFFF" />
            <ThemedText style={styles.actionBtnText}>إنشاء تحدي جديد</ThemedText>
          </LinearGradient>
        </TouchableOpacity>

        <TouchableOpacity
          activeOpacity={0.88}
          onPress={() => {
            try {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            } catch { }
            setShowJoinCodeModal(true);
          }}
          style={[
            styles.actionBtnHalf,
            styles.actionBtnSecondary,
            {
              backgroundColor: isDark ? '#1F2937' : '#FFFFFF',
              borderColor: isDark ? '#374151' : '#E5E7EB',
            },
          ]}>
          <IconSymbol name="key.fill" size={16} color={silaTheme.primary} />
          <ThemedText style={[styles.actionBtnSecondaryText, { color: colors.text }]}>
            الانضمام بكود
          </ThemedText>
        </TouchableOpacity>
      </View>

      {/* Smart Segmented Filter Bar */}
      {groups.length > 0 && (
        <View style={styles.filterScrollWrap}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.filterBarContainer}>
            {filterTabs.map((tab) => {
              const isActive = filterType === tab.key;
              return (
                <TouchableOpacity
                  key={tab.key}
                  activeOpacity={0.82}
                  onPress={() => {
                    try {
                      Haptics.selectionAsync();
                    } catch { }
                    setFilterType(tab.key);
                  }}
                  style={[
                    styles.filterPill,
                    {
                      backgroundColor: isActive
                        ? silaTheme.primary
                        : isDark
                          ? '#27272A'
                          : '#F3F4F6',
                      borderColor: isActive
                        ? silaTheme.primary
                        : isDark
                          ? '#3F3F46'
                          : '#E5E7EB',
                    },
                  ]}>
                  <IconSymbol
                    name={tab.icon as any}
                    size={13}
                    color={isActive ? '#FFFFFF' : colors.icon}
                  />
                  <ThemedText
                    style={[
                      styles.filterPillText,
                      { color: isActive ? '#FFFFFF' : colors.text },
                      isActive && styles.filterPillTextActive,
                    ]}>
                    {tab.label}
                  </ThemedText>
                  <View
                    style={[
                      styles.filterBadge,
                      {
                        backgroundColor: isActive
                          ? 'rgba(255,255,255,0.25)'
                          : isDark
                            ? '#18181B'
                            : '#E5E7EB',
                      },
                    ]}>
                    <ThemedText
                      style={[
                        styles.filterBadgeText,
                        { color: isActive ? '#FFFFFF' : colors.icon },
                      ]}>
                      {tab.count}
                    </ThemedText>
                  </View>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>
      )}

      {/* Loading state */}
      {isLoading && groups.length === 0 ? (
        <View style={styles.loadingBox}>
          <ActivityIndicator size="large" color={silaTheme.primary} />
          <ThemedText style={[styles.loadingText, { color: colors.icon }]}>
            جاري استرجاع المجموعات والتحديات...
          </ThemedText>
        </View>
      ) : groups.length === 0 ? (
        /* Empty State */
        <View
          style={[
            styles.emptyCard,
            {
              backgroundColor: isDark ? '#18181B' : '#F9FAFB',
              borderColor: colors.border,
            },
          ]}>
          <ThemedText style={{ fontSize: 36, marginBottom: 8 }}>🏃‍♂️</ThemedText>
          <ThemedText style={styles.emptyTitle}>لا توجد تحديات نشطة حالياً</ThemedText>
          <ThemedText style={[styles.emptySubtitle, { color: colors.icon }]}>
            كن أول من يطلق تحدي المشي بين أفراد العائلة أو انضم بكود الدعوة واكسب صدارة التحدي!
          </ThemedText>
        </View>
      ) : filteredGroups.length === 0 ? (
        /* Filter Empty State */
        <View
          style={[
            styles.emptyCard,
            {
              backgroundColor: isDark ? '#18181B' : '#F9FAFB',
              borderColor: colors.border,
              marginVertical: 12,
            },
          ]}>
          <ThemedText style={{ fontSize: 32, marginBottom: 8 }}>
            {filterType === 'archived' ? '📦' : filterType === 'completed' ? '🏁' : filterType === 'my' ? '🎯' : '🏃‍♂️'}
          </ThemedText>
          <ThemedText style={styles.emptyTitle}>
            {filterType === 'archived'
              ? 'لا توجد تحديات مؤرشفة أو خاملة'
              : filterType === 'completed'
              ? 'لا توجد تحديات مكتملة حتى الآن'
              : filterType === 'my'
              ? 'لم تنضم لأي تحدي بعد'
              : 'لا توجد تحديات عائلية حالياً'}
          </ThemedText>
          <ThemedText style={[styles.emptySubtitle, { color: colors.icon }]}>
            {filterType === 'archived'
              ? 'تظهر هنا التحديات المؤرشفة تلقائياً بسبب الخمول لتتمكن من إعادة تنشيطها.'
              : filterType === 'completed'
              ? 'تظهر هنا التحديات السابقة التي اكتملت مدتها أو حققت هدفها لاستعراض الفائزين والنتائج.'
              : filterType === 'my'
              ? 'تصفح التحديات المتاحة وانضم إليها، أو انضم بكود دعوة خاص من أفراد العائلة!'
              : 'كن أول من يطلق تحدياً رياضياً لأفراد العائلة!'}
          </ThemedText>
        </View>
      ) : (
        /* Groups List */
        <View style={styles.list}>
          {filteredGroups.map((item) => {
            const isMember = item.is_member;
            const isCreator = item.is_creator;
            const canEdit = item.is_creator || item.is_admin;
            const isPublic = Boolean(item.is_public);

            return (
              <TouchableOpacity
                key={item.id}
                activeOpacity={0.88}
                onPress={() => handleOpenGroup(item)}
                style={[
                  styles.card,
                  {
                    backgroundColor: isDark ? '#18181B' : '#FFFFFF',
                    borderColor: isMember ? silaTheme.primary : (isPublic ? (isDark ? '#1E3A8A' : '#BFDBFE') : colors.border),
                    borderRightWidth: 4.5,
                    borderRightColor: isPublic
                      ? (isDark ? '#3B82F6' : '#2563EB')
                      : (isDark ? '#F59E0B' : '#D97706'),
                  },
                  isPublic && styles.publicCardGlow,
                ]}>
                {/* Header row: Title + Privacy on start, Single Smart Badge on end */}
                <View style={styles.cardHeader}>
                  <View style={styles.titleWithIcon}>
                    {!isPublic && (
                      <View
                        style={[
                          styles.privacyIconWrap,
                          {
                            backgroundColor: isDark ? 'rgba(245, 158, 11, 0.16)' : '#FEF3C7',
                          },
                        ]}>
                        <Ionicons name="lock-closed" size={11} color={isDark ? '#FBBF24' : '#D97706'} />
                      </View>
                    )}
                    <ThemedText numberOfLines={1} style={styles.groupName}>
                      {item.name}
                    </ThemedText>
                  </View>

                  {/* Smart Single Role / Status Badge */}
                  {item.status === 'archived' ? (
                    <View style={[styles.smartBadge, { backgroundColor: isDark ? 'rgba(217, 119, 6, 0.2)' : '#FEF3C7' }]}>
                      <ThemedText style={[styles.smartBadgeText, { color: '#D97706' }]}>
                        {item.archived_reason === 'inactivity_auto' ? 'خامل 📦' : 'مؤرشف 📦'}
                      </ThemedText>
                    </View>
                  ) : item.status === 'upcoming' ? (
                    <View style={[styles.smartBadge, { backgroundColor: isDark ? 'rgba(217, 119, 6, 0.2)' : '#FEF3C7' }]}>
                      <ThemedText style={[styles.smartBadgeText, { color: '#D97706' }]}>
                        قريباً ⏳
                      </ThemedText>
                    </View>
                  ) : item.status === 'completed' ? (
                    <View style={[styles.smartBadge, { backgroundColor: isDark ? '#27272A' : '#F3F4F6' }]}>
                      <ThemedText style={[styles.smartBadgeText, { color: colors.icon }]}>
                        مكتمل 🏁
                      </ThemedText>
                    </View>
                  ) : isCreator ? (
                    <View
                      style={[
                        styles.smartBadge,
                        {
                          backgroundColor: isDark ? 'rgba(124, 58, 237, 0.16)' : '#F3E8FF',
                          borderColor: isDark ? 'rgba(124, 58, 237, 0.35)' : '#DDD6FE',
                          borderWidth: 1,
                        },
                      ]}>
                      <ThemedText style={[styles.smartBadgeText, { color: isDark ? '#C4B5FD' : '#7C3AED' }]}>
                        👑 المنشئ
                      </ThemedText>
                    </View>
                  ) : isMember ? (
                    <View
                      style={[
                        styles.smartBadge,
                        {
                          backgroundColor: isDark ? 'rgba(5, 150, 105, 0.16)' : '#ECFDF5',
                          borderColor: isDark ? 'rgba(5, 150, 105, 0.35)' : '#A7F3D0',
                          borderWidth: 1,
                        },
                      ]}>
                      <ThemedText style={[styles.smartBadgeText, { color: isDark ? '#6EE7B7' : '#059669' }]}>
                        ✓ منضم
                      </ThemedText>
                    </View>
                  ) : null}
                </View>

                {/* Description (if present) */}
                {item.description ? (
                  <ThemedText numberOfLines={2} style={[styles.groupDesc, { color: colors.icon }]}>
                    {item.description}
                  </ThemedText>
                ) : null}

                {/* Metrics Bar: Integrated stats row */}
                <View
                  style={[
                    styles.metaBar,
                    {
                      backgroundColor: isDark ? '#202024' : '#F8FAFC',
                      borderColor: isDark ? '#2E2E33' : '#E2E8F0',
                    },
                  ]}>
                  <View style={styles.metaItem}>
                    <Ionicons name="people-outline" size={14} color={colors.icon} />
                    <ThemedText style={[styles.metaText, { color: colors.text }]}>
                      {item.member_count} مشارك
                    </ThemedText>
                  </View>

                  <View style={[styles.metaDivider, { backgroundColor: isDark ? '#333338' : '#E2E8F0' }]} />

                  <View style={styles.metaItem}>
                    <IconSymbol name="figure.run" size={14} color={silaTheme.primary} />
                    <ThemedText style={[styles.metaText, { color: colors.text }]}>
                      {item.target_steps ? `${formatSteps(item.target_steps)} خطوة` : 'صدارة مفتوحة'}
                    </ThemedText>
                  </View>

                  <View style={[styles.metaDivider, { backgroundColor: isDark ? '#333338' : '#E2E8F0' }]} />

                  <View style={[styles.metaItem, { flexShrink: 1 }]}>
                    <Ionicons name="calendar-outline" size={13} color={colors.icon} />
                    <ThemedText numberOfLines={1} style={[styles.metaText, { color: colors.icon }]}>
                      {formatGroupDates(item.start_date, item.end_date)}
                    </ThemedText>
                  </View>
                </View>

                {/* Footer Buttons */}
                <View style={styles.cardFooter}>
                  <TouchableOpacity
                    activeOpacity={0.8}
                    onPress={() => handleOpenGroup(item)}
                    style={[
                      styles.viewBtn,
                      {
                        backgroundColor: isMember
                          ? (isDark ? 'rgba(5, 150, 105, 0.15)' : '#ECFDF5')
                          : (isDark ? '#27272A' : '#F1F5F9'),
                        borderColor: isMember
                          ? (isDark ? '#059669' : '#A7F3D0')
                          : (isDark ? '#3F3F46' : '#E2E8F0'),
                      },
                    ]}>
                    <ThemedText
                      style={[
                        styles.viewBtnText,
                        {
                          color: isMember ? (isDark ? '#6EE7B7' : '#059669') : colors.text,
                          fontFamily: isMember ? 'Tajawal-Bold' : 'Tajawal-Medium',
                        },
                      ]}>
                      {isMember ? 'لوحة صدارة التحدي 🏆' : 'تفاصيل التحدي والانضمام ←'}
                    </ThemedText>
                  </TouchableOpacity>

                  {Boolean(isMember || item.is_public) && (
                    <TouchableOpacity
                      activeOpacity={0.8}
                      onPress={(e) => {
                        e.stopPropagation();
                        handleOpenChat(item);
                      }}
                      style={[
                        styles.cardChatBtn,
                        {
                          backgroundColor: isDark ? 'rgba(55,160,92,0.15)' : '#DCF2DD',
                          borderColor: isDark ? 'rgba(55,160,92,0.3)' : '#C1E8C5',
                        },
                      ]}>
                      <Ionicons name="chatbubbles-outline" size={15} color={silaTheme.primary} />
                      <ThemedText style={[styles.cardChatBtnText, { color: silaTheme.primary }]}>
                        الدردشة
                      </ThemedText>
                    </TouchableOpacity>
                  )}

                  {canEdit && (
                    <TouchableOpacity
                      activeOpacity={0.8}
                      onPress={(e) => {
                        e.stopPropagation();
                        try {
                          Haptics.selectionAsync();
                        } catch { }
                        setEditingCardGroup(item);
                      }}
                      style={[
                        styles.cardEditBtn,
                        {
                          backgroundColor: isDark ? '#064E3B44' : '#ECFDF5',
                          borderColor: silaTheme.primary,
                        },
                      ]}>
                      <Ionicons name="pencil" size={13} color={silaTheme.primary} />
                      <ThemedText style={[styles.cardEditBtnText, { color: silaTheme.primary }]}>
                        تعديل
                      </ThemedText>
                    </TouchableOpacity>
                  )}
                </View>
              </TouchableOpacity>
            );
          })}
        </View>
      )}

      {/* Create Modal */}
      <CreateGroupModal
        visible={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        onSubmit={onCreateGroup}
      />

      {/* Join By Code Modal */}
      <JoinGroupByCodeModal
        visible={showJoinCodeModal}
        onClose={() => setShowJoinCodeModal(false)}
        onSubmit={onJoinGroupByCode}
      />

      {/* Details & Leaderboard Modal */}
      <GroupDetailsModal
        visible={selectedGroupId !== null}
        selectedGroupId={selectedGroupId}
        initialGroup={selectedGroupItem}
        initialTab={modalInitialTab}
        onJoinGroup={onJoinGroup}
        group={activeGroupDetail}
        leaderboard={groupLeaderboard}
        todaySteps={todaySteps}
        onSyncNow={onSyncNow}
        onClose={() => setSelectedGroupId(null)}
        onInviteMember={onInviteMember}
        onLeaveGroup={onLeaveGroup}
        onLoadLeaderboardPeriod={onLoadGroupLeaderboardPeriod}
        onGroupUpdated={handleGroupEdited}
        onDeleteGroup={async (gId) => {
          if (onDeleteGroup) {
            const res = await onDeleteGroup(gId);
            if (res.success) {
              setLocalGroups((prev) => prev.filter((g) => g.id !== gId));
              setSelectedGroupId(null);
            }
            return res;
          }
          setLocalGroups((prev) => prev.filter((g) => g.id !== gId));
          setSelectedGroupId(null);
          return { success: true };
        }}
      />

      {/* Direct Card Edit Modal */}
      {editingCardGroup && (
        <EditGroupModal
          visible={editingCardGroup !== null}
          group={editingCardGroup}
          onClose={() => setEditingCardGroup(null)}
          onSuccess={handleGroupEdited}
          onDelete={(gId) => {
            setLocalGroups((prev) => prev.filter((g) => g.id !== gId));
            setEditingCardGroup(null);
            if (onRefreshGroups) {
              void onRefreshGroups();
            }
          }}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginVertical: 10,
  },
  topActionsRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 16,
  },
  actionBtnHalf: {
    flex: 1,
    borderRadius: 16,
    overflow: 'hidden',
    minHeight: 50,
  },
  actionGradient: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 10,
    gap: 8,
    minHeight: 50,
  },
  actionBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
    color: '#FFFFFF',
    lineHeight: 22,
    includeFontPadding: false,
  },
  actionBtnSecondary: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    paddingVertical: 12,
    paddingHorizontal: 10,
    gap: 8,
    borderRadius: 16,
    minHeight: 50,
  },
  actionBtnSecondaryText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
    lineHeight: 22,
    includeFontPadding: false,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalCard: {
    width: '100%',
    maxWidth: 400,
    borderRadius: 24,
    borderWidth: 1,
    padding: 20,
    gap: 14,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.2,
        shadowRadius: 10,
      },
      android: { elevation: 8 },
    }),
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  modalCloseBtn: {
    padding: 6,
  },
  modalTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
  },
  modalSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 20,
    textAlign: 'center',
  },
  inputBox: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 4,
  },
  codeInput: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    textAlign: 'center',
    letterSpacing: 2,
    height: 46,
  },
  errorText: {
    color: '#EF4444',
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    textAlign: 'center',
  },
  submitBtn: {
    paddingVertical: 13,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
  },
  submitBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  loadingBox: {
    paddingVertical: 40,
    alignItems: 'center',
    gap: 10,
  },
  loadingText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
  },
  emptyCard: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 24,
    alignItems: 'center',
    textAlign: 'center',
  },
  emptyTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    marginBottom: 4,
  },
  emptySubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    textAlign: 'center',
    lineHeight: 20,
  },
  list: {
    gap: 12,
  },
  card: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 16,
    gap: 10,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.05,
        shadowRadius: 6,
      },
      android: { elevation: 2 },
    }),
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 8,
  },
  titleWithIcon: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  privacyIconWrap: {
    width: 22,
    height: 22,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  smartBadge: {
    paddingHorizontal: 9,
    paddingVertical: 3.5,
    borderRadius: 8,
  },
  smartBadgeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  publicCardGlow: {
    ...Platform.select({
      ios: {
        shadowColor: '#2563EB',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15,
        shadowRadius: 8,
      },
      android: {
        elevation: 3,
      },
    }),
  },
  groupName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15.5,
    flexShrink: 1,
  },
  groupDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 18,
  },
  metaBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1,
    marginTop: 2,
  },
  metaItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  metaText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  metaDivider: {
    width: 1,
    height: 12,
    marginHorizontal: 2,
  },
  cardFooter: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
  },
  joinBtn: {
    flex: 1,
    paddingVertical: 9,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  joinBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  viewBtn: {
    flex: 1,
    paddingVertical: 9,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  chatShortcutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 9,
    borderRadius: 10,
  },
  chatShortcutBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  cardChatBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingVertical: 9,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  cardChatBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
  },
  viewBtnText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  cardEditBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 9,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  cardEditBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  filterScrollWrap: {
    marginBottom: 4,
  },
  filterBarContainer: {
    flexDirection: 'row',
    gap: 8,
    paddingVertical: 4,
    paddingHorizontal: 2,
  },
  filterPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 20,
    borderWidth: 1,
  },
  filterPillText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  filterPillTextActive: {
    fontFamily: 'Tajawal-Bold',
  },
  filterBadge: {
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: 10,
    minWidth: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  filterBadgeText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
  },
});
