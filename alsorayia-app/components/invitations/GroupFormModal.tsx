import { InvitationField } from '@/components/invitations/InvitationField';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { RecipientPickerModal } from '@/components/invitations/RecipientPickerModal';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import type { InvitationGroup, InvitationPersonBrief } from '@/lib/api/invitations';
import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

interface GroupFormModalProps {
  visible: boolean;
  onClose: () => void;
  onSubmit: (input: {
    name: string;
    description: string | null;
    person_ids: number[];
  }) => Promise<{ ok: boolean; message: string }>;
  saving: boolean;
  group?: InvitationGroup | null;
  colors: ThemeColors;
  isDark: boolean;
}

export function GroupFormModal({
  visible,
  onClose,
  onSubmit,
  saving,
  group,
  colors,
  isDark,
}: GroupFormModalProps) {
  const insets = useSafeAreaInsets();
  const muted = colors.icon;
  const cardBg = isDark ? colors.card : '#FFFFFF';
  const border = isDark ? '#334155' : colors.border;
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [members, setMembers] = useState<InvitationPersonBrief[]>([]);
  const [pickerVisible, setPickerVisible] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isKeyboardOpen, setIsKeyboardOpen] = useState(false);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const onShow = () => setIsKeyboardOpen(true);
    const onHide = () => setIsKeyboardOpen(false);

    const showSub = Keyboard.addListener(showEvent, onShow);
    const hideSub = Keyboard.addListener(hideEvent, onHide);

    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);

  useEffect(() => {
    if (!visible) return;
    setName(group?.name ?? '');
    setDescription(group?.description ?? '');
    setMembers(group?.persons ?? []);
    setError(null);
  }, [visible, group]);

  const handleSave = async () => {
    Keyboard.dismiss();
    const trimmed = name.trim();
    if (!trimmed) {
      setError('اسم المجموعة مطلوب.');
      return;
    }
    const res = await onSubmit({
      name: trimmed,
      description: description.trim() || null,
      person_ids: members.map((m) => m.id),
    });
    if (!res.ok) {
      setError(res.message);
      return;
    }
    onClose();
  };

  return (
    <>
      <Modal visible={visible && !pickerVisible} transparent animationType="slide" onRequestClose={onClose}>
        <KeyboardAvoidingView
          style={styles.keyboardContainer}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
          <View style={styles.overlay}>
            <Pressable
              style={StyleSheet.absoluteFill}
              onPress={() => {
                Keyboard.dismiss();
                onClose();
              }}
            />
            <View
              style={[
                styles.sheet,
                {
                  backgroundColor: isDark ? '#0F172A' : '#F8FAFC',
                  paddingBottom: Math.max(insets.bottom, 16),
                },
              ]}>
              <View style={styles.header}>
                <ThemedText style={styles.title}>
                  {group ? 'تعديل المجموعة' : 'مجموعة جديدة'}
                </ThemedText>
                <Pressable
                  onPress={() => {
                    Keyboard.dismiss();
                    onClose();
                  }}
                  hitSlop={12}
                  style={[styles.closeBtn, { backgroundColor: colors.primary + '18' }]}>
                  <IconSymbol name="xmark.circle.fill" size={22} color={muted} />
                </Pressable>
              </View>

              <ScrollView
                style={{ flexShrink: 1 }}
                contentContainerStyle={{ paddingBottom: isKeyboardOpen ? 180 : 24 }}
                keyboardShouldPersistTaps="handled"
                showsVerticalScrollIndicator={false}>
            <InvitationField
              label="اسم المجموعة"
              value={name}
              onChangeText={setName}
              placeholder="مثال: أعمامي"
              mutedColor={muted}
              textColor={colors.text}
              cardBg={cardBg}
              borderColor={border}
            />
            <InvitationField
              label="الوصف (اختياري)"
              value={description}
              onChangeText={setDescription}
              placeholder="وصف قصير للمجموعة"
              mutedColor={muted}
              textColor={colors.text}
              cardBg={cardBg}
              borderColor={border}
            />

            <ThemedText style={[styles.membersLabel, { color: muted }]}>الأعضاء</ThemedText>
            <Pressable
              onPress={() => setPickerVisible(true)}
              style={[styles.pickBtn, { borderColor: border, backgroundColor: cardBg }]}>
              <IconSymbol name="plus.circle.fill" size={18} color={colors.primary} />
              <ThemedText style={[styles.pickBtnText, { color: colors.primary }]}>
                اختيار الأعضاء من الشجرة
              </ThemedText>
            </Pressable>

            {members.length === 0 ? (
              <ThemedText style={[styles.empty, { color: muted }]}>
                لم يُضف أعضاء بعد
              </ThemedText>
            ) : (
              <View style={styles.memberList}>
                {members.map((m) => (
                  <View
                    key={m.id}
                    style={[styles.memberRow, { backgroundColor: cardBg, borderColor: border }]}>
                    <PersonAvatar uri={m.photo_url} size={36} mutedColor={muted} />
                    <ThemedText style={styles.memberName}>{m.first_name}</ThemedText>
                    <Pressable
                      onPress={() => setMembers((prev) => prev.filter((x) => x.id !== m.id))}
                      hitSlop={8}>
                      <IconSymbol name="trash.fill" size={18} color="#EF4444" />
                    </Pressable>
                  </View>
                ))}
              </View>
            )}

            {error ? (
              <ThemedText style={styles.error}>{error}</ThemedText>
            ) : null}

            <Pressable
              onPress={() => void handleSave()}
              disabled={saving}
              style={[
                styles.saveBtn,
                { backgroundColor: colors.primary, opacity: saving ? 0.7 : 1 },
              ]}>
              {saving ? (
                <ActivityIndicator color="#FFFFFF" />
              ) : (
                <ThemedText style={styles.saveText}>حفظ المجموعة</ThemedText>
              )}
            </Pressable>
            </ScrollView>
          </View>
        </View>
        </KeyboardAvoidingView>
      </Modal>

      <RecipientPickerModal
        visible={pickerVisible}
        onClose={() => setPickerVisible(false)}
        onConfirm={setMembers}
        initialSelected={members}
        title="أعضاء المجموعة"
        colors={colors}
        isDark={isDark}
      />
    </>
  );
}

const styles = StyleSheet.create({
  keyboardContainer: {
    flex: 1,
  },
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'flex-end',
  },
  sheet: {
    maxHeight: '90%',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingHorizontal: 16,
    paddingTop: 16,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  title: {
    fontSize: 20,
    fontFamily: 'Tajawal-Bold',
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  membersLabel: {
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
    marginBottom: 6,
  },
  pickBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 14,
    borderWidth: 1,
    borderStyle: 'dashed',
    marginBottom: 12,
  },
  pickBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  empty: {
    textAlign: 'center',
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    marginBottom: 16,
  },
  memberList: {
    gap: 8,
    marginBottom: 16,
  },
  memberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: 14,
    padding: 10,
  },
  memberName: {
    flex: 1,
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  error: {
    color: '#EF4444',
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    textAlign: 'center',
    marginBottom: 12,
  },
  saveBtn: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 14,
    marginBottom: 8,
  },
  saveText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
});
