import { InvitationField } from '@/components/invitations/InvitationField';
import { InvitationGradientButton } from '@/components/invitations/InvitationPrimitives';
import { getInvitationPalette } from '@/components/invitations/invitationTheme';
import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { RecipientPickerModal } from '@/components/invitations/RecipientPickerModal';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import type { InvitationGroup, InvitationPersonBrief } from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import React, { useEffect, useState } from 'react';
import {
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
  const palette = getInvitationPalette(isDark);
  const muted = palette.muted;
  const cardBg = palette.surface;
  const border = palette.border;
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
          <View style={[styles.overlay, { backgroundColor: palette.overlay }]}>
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
                  backgroundColor: isDark ? '#0D1E18' : '#F6FBF8',
                  borderColor: border,
                  paddingBottom: Math.max(insets.bottom, 16),
                },
              ]}>
              <View style={styles.header}>
                <ThemedText style={[styles.title, { color: palette.text }]}>
                  {group ? 'تعديل المجموعة' : 'مجموعة جديدة'}
                </ThemedText>
                <Pressable
                  onPress={() => {
                    Keyboard.dismiss();
                    onClose();
                  }}
                  hitSlop={12}
                  style={[styles.closeBtn, { backgroundColor: palette.surface, borderColor: border }]}>
                  <IconSymbol name="xmark" size={16} color={muted} />
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
                  placeholder="مثال: أعمامي وكبار العائلة"
                  mutedColor={muted}
                  textColor={palette.text}
                  cardBg={cardBg}
                  borderColor={border}
                />
                <InvitationField
                  label="الوصف (اختياري)"
                  value={description}
                  onChangeText={setDescription}
                  placeholder="وصف قصير للمجموعة"
                  mutedColor={muted}
                  textColor={palette.text}
                  cardBg={cardBg}
                  borderColor={border}
                />

                <ThemedText style={[styles.membersLabel, { color: muted }]}>الأعضاء</ThemedText>
                <Pressable
                  onPress={() => setPickerVisible(true)}
                  style={[styles.pickBtn, { borderColor: palette.borderStrong, backgroundColor: palette.surfaceSoft }]}>
                  <IconSymbol name="plus.circle.fill" size={18} color={palette.primary} />
                  <ThemedText style={[styles.pickBtnText, { color: palette.primary }]}>
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
                        <PersonAvatar uri={m.photo_url} size={36} mutedColor={muted} name={m.first_name} />
                        <ThemedText style={[styles.memberName, { color: palette.text }]}>{m.first_name}</ThemedText>
                        <Pressable
                          onPress={() => setMembers((prev) => prev.filter((x) => x.id !== m.id))}
                          hitSlop={8}
                          style={[styles.removeBtn, { backgroundColor: palette.dangerBg }]}>
                          <Ionicons name="trash-outline" size={16} color={palette.dangerText} />
                        </Pressable>
                      </View>
                    ))}
                  </View>
                )}

                {error ? (
                  <ThemedText style={[styles.error, { color: palette.dangerText }]}>{error}</ThemedText>
                ) : null}

                <InvitationGradientButton
                  label="حفظ المجموعة"
                  icon="checkmark-circle-outline"
                  loading={saving}
                  disabled={saving}
                  onPress={() => void handleSave()}
                  palette={palette}
                />
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
    justifyContent: 'flex-end',
  },
  sheet: {
    maxHeight: '90%',
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    borderWidth: 1,
    borderBottomWidth: 0,
    paddingHorizontal: 16,
    paddingTop: 16,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 14,
  },
  title: {
    fontSize: 20,
    fontFamily: 'Tajawal-Bold',
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  membersLabel: {
    fontSize: 12.5,
    fontFamily: 'Tajawal-Medium',
    marginBottom: 6,
    textAlign: 'right',
  },
  pickBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 13,
    borderRadius: 16,
    borderWidth: 1.5,
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
    borderRadius: 16,
    padding: 10,
  },
  memberName: {
    flex: 1,
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'right',
  },
  removeBtn: {
    width: 32,
    height: 32,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  error: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    textAlign: 'center',
    marginBottom: 12,
  },
});
