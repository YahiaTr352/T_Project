import { GroupFormModal } from '@/components/invitations/GroupFormModal';
import { InvitationGradientButton, InvitationHeroBanner } from '@/components/invitations/InvitationPrimitives';
import {
  cardShadowStyle,
  getInvitationPalette,
  INVITATION_IMAGES,
} from '@/components/invitations/invitationTheme';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { ThemedText } from '@/components/themed-text';
import { CustomAlert } from '@/components/ui/AlertModal';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import type { InvitationGroup } from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import React, { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

interface GroupsTabProps {
  groups: InvitationGroup[];
  loading: boolean;
  refreshing: boolean;
  saving: boolean;
  error: string | null;
  onRefresh: () => void;
  onCreate: (input: {
    name: string;
    description: string | null;
    person_ids: number[];
  }) => Promise<{ ok: boolean; message: string }>;
  onUpdate: (
    id: number,
    input: { name?: string; description?: string | null; person_ids?: number[] }
  ) => Promise<{ ok: boolean; message: string }>;
  onDelete: (id: number) => Promise<{ ok: boolean; message: string }>;
  onDetach: (groupId: number, personId: number) => Promise<{ ok: boolean; message: string }>;
  colors: ThemeColors;
  isDark: boolean;
}

const AVATAR_TONES = ['#0B4D31', '#157347', '#269B5D'] as const;

function firstLetter(value?: string | null): string {
  const trimmed = (value ?? '').trim();
  return trimmed ? trimmed.charAt(0) : 'ع';
}

export function GroupsTab({
  groups,
  loading,
  refreshing,
  saving,
  error,
  onRefresh,
  onCreate,
  onUpdate,
  onDelete,
  onDetach,
  colors,
  isDark,
}: GroupsTabProps) {
  const palette = getInvitationPalette(isDark);
  const [formVisible, setFormVisible] = useState(false);
  const [editing, setEditing] = useState<InvitationGroup | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const openCreate = () => {
    setEditing(null);
    setFormVisible(true);
  };

  const openEdit = (group: InvitationGroup) => {
    setEditing(group);
    setFormVisible(true);
  };

  const confirmDelete = (group: InvitationGroup) => {
    CustomAlert.alert('حذف المجموعة', `هل تريد حذف «${group.name}»؟`, [
      { text: 'إلغاء', style: 'cancel' },
      {
        text: 'حذف',
        style: 'destructive',
        onPress: () => {
          void onDelete(group.id).then((res) => {
            if (!res.ok) CustomAlert.alert('المجموعات', res.message);
          });
        },
      },
    ]);
  };

  if (loading && groups.length === 0) {
    return (
      <View style={[styles.centered, styles.flex]}>
        <ActivityIndicator color={palette.primary} />
        <ThemedText style={[styles.muted, { color: palette.muted }]}>جاري تحميل المجموعات...</ThemedText>
      </View>
    );
  }

  return (
    <View style={styles.flex}>
      <ScrollView
        style={styles.flex}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={palette.primary} />
        }
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}>
        <InvitationHeroBanner
          title="مجموعات الدعوات"
          subtitle="أنشئ قوائم عائلية لإرسال الدعوات بسرعة"
          image={INVITATION_IMAGES.reminder}
          palette={palette}
        />

        <InvitationGradientButton
          label="مجموعة جديدة"
          icon="add"
          onPress={openCreate}
          palette={palette}
          style={{ marginBottom: 14 }}
        />

        {error ? (
          <View style={[styles.errorBanner, { backgroundColor: palette.dangerBg, borderColor: palette.dangerBorder }]}>
            <ThemedText style={[styles.error, { color: palette.dangerText }]}>{error}</ThemedText>
          </View>
        ) : null}

        {groups.length === 0 ? (
          <View style={[styles.emptyCard, cardShadowStyle(palette), { backgroundColor: palette.surface, borderColor: palette.border }]}>
            <View style={[styles.emptyIconWrap, { backgroundColor: palette.surfaceSoft }]}>
              <IconSymbol name="person.2.fill" size={32} color={palette.primary} />
            </View>
            <ThemedText style={[styles.emptyTitle, { color: palette.text }]}>لا توجد مجموعات بعد</ThemedText>
            <ThemedText style={[styles.muted, { color: palette.muted }]}>
              أنشئ مجموعة لإرسال الدعوات بسرعة لأفراد العائلة.
            </ThemedText>
          </View>
        ) : (
          groups.map((group) => {
            const open = expandedId === group.id;
            const preview = group.persons.slice(0, 2);
            const remaining = Math.max(0, group.persons_count - preview.length);
            const countWord = group.persons_count >= 3 && group.persons_count <= 10 ? 'أعضاء' : 'عضو';

            return (
              <View
                key={group.id}
                style={[
                  styles.card,
                  cardShadowStyle(palette),
                  {
                    backgroundColor: palette.surface,
                    borderColor: open ? palette.borderStrong : palette.border,
                  },
                ]}>
                <View style={styles.cardHeaderRow}>
                  <Pressable
                    onPress={() => setExpandedId(open ? null : group.id)}
                    style={styles.cardMainPress}>
                    {/* تكديس حروف/أفاتار المجموعة والأعضاء */}
                    <View style={styles.avatarStack}>
                      <View style={[styles.groupLeadAvatar, { backgroundColor: palette.primaryDark, borderColor: palette.surface }]}>
                        <Text style={styles.leadAvatarText}>{firstLetter(group.name)}</Text>
                      </View>
                      {preview.map((p, idx) => (
                        <View
                          key={p.id}
                          style={[
                            styles.stackBadge,
                            {
                              backgroundColor: AVATAR_TONES[(idx + 1) % AVATAR_TONES.length],
                              borderColor: palette.surface,
                              marginStart: -10,
                            },
                          ]}>
                          {p.photo_url ? (
                            <PersonAvatar uri={p.photo_url} size={28} mutedColor="#FFFFFF" name={p.first_name} />
                          ) : (
                            <Text style={styles.stackBadgeText}>{firstLetter(p.first_name)}</Text>
                          )}
                        </View>
                      ))}
                      {remaining > 0 ? (
                        <View
                          style={[
                            styles.stackCountPill,
                            {
                              backgroundColor: palette.pillBg,
                              borderColor: palette.surface,
                              marginStart: -8,
                            },
                          ]}>
                          <Text style={[styles.stackCountText, { color: palette.pillText }]}>{`${remaining}+`}</Text>
                        </View>
                      ) : null}
                    </View>

                    <View style={styles.cardMeta}>
                      <ThemedText style={[styles.groupName, { color: palette.text }]} numberOfLines={1}>
                        {group.name}
                      </ThemedText>
                      <ThemedText style={[styles.groupSub, { color: palette.muted }]} numberOfLines={1}>
                        {group.persons_count} {countWord}
                        {group.description ? ` · ${group.description}` : ''}
                      </ThemedText>
                    </View>
                  </Pressable>

                  <View style={styles.actions}>
                    <Pressable
                      onPress={() => openEdit(group)}
                      accessibilityLabel={`تعديل ${group.name}`}
                      style={[styles.iconActionBtn, { backgroundColor: palette.surfaceAlt, borderColor: palette.border }]}>
                      <Ionicons name="create-outline" size={17} color={palette.primary} />
                    </Pressable>
                    <Pressable
                      onPress={() => confirmDelete(group)}
                      accessibilityLabel={`حذف ${group.name}`}
                      style={[styles.iconActionBtn, { backgroundColor: palette.dangerBg, borderColor: palette.dangerBorder }]}>
                      <Ionicons name="trash-outline" size={17} color={palette.dangerText} />
                    </Pressable>
                  </View>
                </View>

                {open ? (
                  <View style={[styles.membersTray, { backgroundColor: palette.surfaceAlt, borderColor: palette.border }]}>
                    {group.persons.length === 0 ? (
                      <ThemedText style={[styles.muted, { color: palette.muted, paddingVertical: 8 }]}>
                        لا يوجد أعضاء في هذه المجموعة بعد.
                      </ThemedText>
                    ) : (
                      group.persons.map((p, index) => (
                        <View
                          key={p.id}
                          style={[
                            styles.memberRow,
                            { backgroundColor: palette.surface, borderColor: palette.border },
                          ]}>
                          {p.photo_url ? (
                            <PersonAvatar uri={p.photo_url} size={34} mutedColor={palette.muted} name={p.first_name} />
                          ) : (
                            <View style={[styles.memberInitialCircle, { backgroundColor: AVATAR_TONES[index % AVATAR_TONES.length] }]}>
                              <Text style={styles.memberInitialText}>{firstLetter(p.first_name)}</Text>
                            </View>
                          )}
                          <ThemedText style={[styles.memberName, { color: palette.text }]}>
                            {p.full_name || p.first_name}
                          </ThemedText>
                          <Pressable
                            onPress={() => {
                              CustomAlert.alert('إزالة العضو', `إزالة ${p.first_name} من المجموعة؟`, [
                                { text: 'إلغاء', style: 'cancel' },
                                {
                                  text: 'إزالة',
                                  style: 'destructive',
                                  onPress: () => {
                                    void onDetach(group.id, p.id).then((res) => {
                                      if (!res.ok) CustomAlert.alert('المجموعات', res.message);
                                    });
                                  },
                                },
                              ]);
                            }}
                            hitSlop={8}
                            style={styles.removeMemberBtn}>
                            <Ionicons name="close" size={17} color={palette.muted} />
                          </Pressable>
                        </View>
                      ))
                    )}
                  </View>
                ) : null}
              </View>
            );
          })
        )}
      </ScrollView>

      <GroupFormModal
        visible={formVisible}
        onClose={() => setFormVisible(false)}
        saving={saving}
        group={editing}
        colors={colors}
        isDark={isDark}
        onSubmit={async (input) => {
          if (editing) return onUpdate(editing.id, input);
          return onCreate(input);
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  flex: {
    flex: 1,
  },
  scrollContent: {
    paddingBottom: 32,
  },
  centered: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingVertical: 32,
  },
  errorBanner: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 10,
    marginBottom: 12,
  },
  error: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    textAlign: 'center',
  },
  emptyCard: {
    borderWidth: 1,
    borderRadius: 22,
    padding: 28,
    alignItems: 'center',
    gap: 8,
  },
  emptyIconWrap: {
    width: 64,
    height: 64,
    borderRadius: 32,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  emptyTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  muted: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
  },
  card: {
    borderWidth: 1,
    borderRadius: 22,
    padding: 14,
    marginBottom: 12,
  },
  cardHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cardMainPress: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minWidth: 0,
  },
  avatarStack: {
    flexDirection: 'row',
    alignItems: 'center',
    flexShrink: 0,
  },
  groupLeadAvatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 3,
  },
  leadAvatarText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  stackBadge: {
    width: 30,
    height: 30,
    borderRadius: 15,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    zIndex: 2,
  },
  stackBadgeText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
  stackCountPill: {
    minWidth: 30,
    height: 28,
    paddingHorizontal: 6,
    borderRadius: 14,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 1,
  },
  stackCountText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  cardMeta: {
    flex: 1,
    minWidth: 0,
  },
  groupName: {
    fontSize: 15.5,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  groupSub: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
    textAlign: 'right',
    writingDirection: 'rtl',
    marginTop: 2,
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexShrink: 0,
  },
  iconActionBtn: {
    width: 36,
    height: 36,
    borderRadius: 11,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  membersTray: {
    marginTop: 12,
    borderRadius: 16,
    borderWidth: 1,
    padding: 8,
    gap: 8,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: 13,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  memberInitialCircle: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
  },
  memberInitialText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  memberName: {
    flex: 1,
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  removeMemberBtn: {
    width: 28,
    height: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
