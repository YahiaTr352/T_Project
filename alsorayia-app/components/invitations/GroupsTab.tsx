import { GroupFormModal } from '@/components/invitations/GroupFormModal';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import type { InvitationGroup } from '@/lib/api/invitations';
import React, { useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
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
  const muted = colors.icon;
  const cardBg = isDark ? colors.card : '#FFFFFF';
  const border = isDark ? '#334155' : colors.border;
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
        <ActivityIndicator color={colors.primary} />
        <ThemedText style={[styles.muted, { color: muted }]}>جاري تحميل المجموعات...</ThemedText>
      </View>
    );
  }

  return (
    <View style={styles.flex}>
      <Pressable
        onPress={openCreate}
        style={[styles.createBtn, { backgroundColor: colors.primary }]}>
        <IconSymbol name="plus.circle.fill" size={20} color="#FFFFFF" />
        <ThemedText style={styles.createText}>مجموعة جديدة</ThemedText>
      </Pressable>

      {error ? (
        <ThemedText style={styles.error}>{error}</ThemedText>
      ) : null}

      <ScrollView
        style={styles.flex}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />
        }
        contentContainerStyle={groups.length === 0 ? styles.emptyWrap : { paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}>
        {groups.length === 0 ? (
          <View style={styles.centered}>
            <IconSymbol name="person.2.fill" size={40} color={muted} />
            <ThemedText style={[styles.muted, { color: muted }]}>
              لا توجد مجموعات بعد. أنشئ مجموعة لإرسال الدعوات بسرعة.
            </ThemedText>
          </View>
        ) : (
          groups.map((group) => {
            const open = expandedId === group.id;
            const preview = group.persons.slice(0, 4);
            return (
              <View
                key={group.id}
                style={[styles.card, { backgroundColor: cardBg, borderColor: border }]}>
                <Pressable
                  onPress={() => setExpandedId(open ? null : group.id)}
                  style={styles.cardHead}>
                  <View style={styles.avatars}>
                    {preview.length === 0 ? (
                      <View
                        style={[
                          styles.emptyAvatar,
                          { backgroundColor: colors.primary + '18' },
                        ]}>
                        <IconSymbol name="person.2.fill" size={18} color={colors.primary} />
                      </View>
                    ) : (
                      preview.map((p, index) => (
                        <View key={p.id} style={{ marginStart: index === 0 ? 0 : -10 }}>
                          <PersonAvatar uri={p.photo_url} size={32} mutedColor={muted} />
                        </View>
                      ))
                    )}
                  </View>
                  <View style={styles.cardMeta}>
                    <ThemedText style={styles.groupName}>{group.name}</ThemedText>
                    <ThemedText style={[styles.muted, { color: muted, textAlign: 'left' }]}>
                      {group.persons_count} عضو
                      {group.description ? ` · ${group.description}` : ''}
                    </ThemedText>
                  </View>
                  <IconSymbol
                    name="chevron.right"
                    size={16}
                    color={muted}
                  />
                </Pressable>

                <View style={styles.actions}>
                  <Pressable onPress={() => openEdit(group)} style={styles.iconAction}>
                    <IconSymbol name="pencil" size={18} color={colors.primary} />
                  </Pressable>
                  <Pressable onPress={() => confirmDelete(group)} style={styles.iconAction}>
                    <IconSymbol name="trash.fill" size={18} color="#EF4444" />
                  </Pressable>
                </View>

                {open && group.persons.length > 0 ? (
                  <View style={styles.members}>
                    {group.persons.map((p) => (
                      <View key={p.id} style={[styles.memberRow, { borderColor: border }]}>
                        <PersonAvatar uri={p.photo_url} size={32} mutedColor={muted} />
                        <ThemedText style={styles.memberName}>{p.first_name}</ThemedText>
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
                          hitSlop={8}>
                          <IconSymbol name="xmark.circle.fill" size={18} color={muted} />
                        </Pressable>
                      </View>
                    ))}
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
  createBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 14,
    marginBottom: 14,
  },
  createText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  error: {
    color: '#EF4444',
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
    marginBottom: 10,
  },
  emptyWrap: {
    paddingVertical: 40,
  },
  centered: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingVertical: 32,
  },
  muted: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
  },
  card: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 12,
    marginBottom: 12,
  },
  cardHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  avatars: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  emptyAvatar: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardMeta: {
    flex: 1,
  },
  groupName: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
  },
  actions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 8,
    marginTop: 8,
  },
  iconAction: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
  },
  members: {
    marginTop: 8,
    gap: 8,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingTop: 8,
  },
  memberName: {
    flex: 1,
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
  },
});
