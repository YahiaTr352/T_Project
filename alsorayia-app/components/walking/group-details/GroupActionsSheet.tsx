import { Ionicons } from '@expo/vector-icons';
import { Modal, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { silaTheme, type ThemeColors } from '@/constants/theme';
import type { MemberChallengeSettings, WalkingGroupItem } from '@/lib/api/walkingApi';
import { groupDetailsStyles as styles } from './groupDetailsStyles';

interface GroupActionsSheetProps {
  visible: boolean;
  group: WalkingGroupItem;
  isDark: boolean;
  colors: ThemeColors;
  isCreatorOrAdmin: boolean;
  isMember: boolean;
  memberSettings: MemberChallengeSettings | null;
  onClose: () => void;
  onEdit: () => void;
  onInvite: () => void;
  onShareCode: () => void;
  onOpenPrivacy: () => void;
  onLeave: () => void;
  onDelete: () => void;
}

/**
 * Bottom action sheet for managing a walking challenge (edit, invite, share code,
 * privacy settings, leave, or delete) extracted from GroupDetailsModal.
 */
export function GroupActionsSheet({
  visible,
  group,
  isDark,
  colors,
  isCreatorOrAdmin,
  isMember,
  memberSettings,
  onClose,
  onEdit,
  onInvite,
  onShareCode,
  onOpenPrivacy,
  onLeave,
  onDelete,
}: GroupActionsSheetProps) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <TouchableOpacity activeOpacity={1} onPress={onClose} style={styles.actionMenuOverlay}>
        <TouchableOpacity
          activeOpacity={1}
          style={[
            styles.actionMenuContent,
            { backgroundColor: isDark ? '#1F2937' : '#FFFFFF', borderColor: colors.border },
          ]}>
          <View style={styles.actionMenuHandle} />

          <View style={styles.actionMenuHeader}>
            <ThemedText style={styles.actionMenuTitle} numberOfLines={1}>
              {group.name}
            </ThemedText>
            <ThemedText style={[styles.actionMenuSubtitle, { color: colors.icon }]}>
              خيارات وإدارة التحدي
            </ThemedText>
          </View>

          <View style={styles.actionMenuItemsList}>
            {/* 1. Edit (Admin/Creator) */}
            {isCreatorOrAdmin && (
              <TouchableOpacity
                activeOpacity={0.75}
                style={styles.actionMenuItem}
                onPress={() => {
                  onClose();
                  setTimeout(onEdit, 250);
                }}>
                <View
                  style={[
                    styles.actionMenuIconWrap,
                    { backgroundColor: isDark ? '#064E3B40' : '#ECFDF5' },
                  ]}>
                  <Ionicons name="create-outline" size={19} color={silaTheme.primary} />
                </View>
                <View style={styles.actionMenuItemTextCol}>
                  <ThemedText style={styles.actionMenuItemTitle}>تعديل بيانات التحدي</ThemedText>
                  <ThemedText style={[styles.actionMenuItemSub, { color: colors.icon }]}>
                    تعديل العنوان، الهدف، أو التواريخ
                  </ThemedText>
                </View>
                <IconSymbol name="chevron.backward" size={14} color={colors.icon} />
              </TouchableOpacity>
            )}

            {/* 2. Invite Members (Admin/Creator) */}
            {isCreatorOrAdmin && (
              <TouchableOpacity
                activeOpacity={0.75}
                style={styles.actionMenuItem}
                onPress={() => {
                  onClose();
                  setTimeout(onInvite, 250);
                }}>
                <View
                  style={[
                    styles.actionMenuIconWrap,
                    { backgroundColor: isDark ? '#1E3A8A40' : '#EFF6FF' },
                  ]}>
                  <Ionicons name="person-add-outline" size={19} color="#3B82F6" />
                </View>
                <View style={styles.actionMenuItemTextCol}>
                  <ThemedText style={styles.actionMenuItemTitle}>دعوة وإضافة متسابقين</ThemedText>
                  <ThemedText style={[styles.actionMenuItemSub, { color: colors.icon }]}>
                    إضافة أفراد العائلة مباشرة من شجرة العائلة
                  </ThemedText>
                </View>
                <IconSymbol name="chevron.backward" size={14} color={colors.icon} />
              </TouchableOpacity>
            )}

            {/* 3. Share Code */}
            {Boolean(group.invite_code) && (
              <TouchableOpacity
                activeOpacity={0.75}
                style={styles.actionMenuItem}
                onPress={() => {
                  onClose();
                  onShareCode();
                }}>
                <View
                  style={[
                    styles.actionMenuIconWrap,
                    { backgroundColor: isDark ? '#78350F40' : '#FEF3C7' },
                  ]}>
                  <Ionicons name="share-social-outline" size={19} color="#F59E0B" />
                </View>
                <View style={styles.actionMenuItemTextCol}>
                  <ThemedText style={styles.actionMenuItemTitle}>مشاركة رمز التحدي</ThemedText>
                  <ThemedText style={[styles.actionMenuItemSub, { color: colors.icon }]}>
                    مشاركة كود الانضمام ({group.invite_code}) مع الأصدقاء
                  </ThemedText>
                </View>
                <IconSymbol name="chevron.backward" size={14} color={colors.icon} />
              </TouchableOpacity>
            )}

            {/* 4. Privacy Settings (Member) */}
            {isMember && (
              <TouchableOpacity
                activeOpacity={0.75}
                style={styles.actionMenuItem}
                onPress={() => {
                  onClose();
                  setTimeout(onOpenPrivacy, 250);
                }}>
                <View
                  style={[
                    styles.actionMenuIconWrap,
                    { backgroundColor: isDark ? '#374151' : '#F3F4F6' },
                  ]}>
                  <Ionicons
                    name="shield-checkmark-outline"
                    size={19}
                    color={isDark ? '#E5E7EB' : '#4B5563'}
                  />
                </View>
                <View style={styles.actionMenuItemTextCol}>
                  <ThemedText style={styles.actionMenuItemTitle}>
                    إعدادات الخصوصية والظهور
                  </ThemedText>
                  <ThemedText style={[styles.actionMenuItemSub, { color: colors.icon }]}>
                    {group.is_public
                      ? memberSettings?.is_anonymous
                        ? 'وضع المتخفي مُفعّل'
                        : 'الظهور معلن للجميع'
                      : 'الظهور معلن لجميع الأعضاء'}
                  </ThemedText>
                </View>
                <IconSymbol name="chevron.backward" size={14} color={colors.icon} />
              </TouchableOpacity>
            )}

            {/* 5. Leave Challenge (Member & Not Creator) */}
            {Boolean(isMember && !isCreatorOrAdmin) && (
              <TouchableOpacity
                activeOpacity={0.75}
                style={styles.actionMenuItem}
                onPress={() => {
                  onClose();
                  setTimeout(onLeave, 250);
                }}>
                <View
                  style={[
                    styles.actionMenuIconWrap,
                    { backgroundColor: isDark ? '#7F1D1D30' : '#FEF2F2' },
                  ]}>
                  <Ionicons name="log-out-outline" size={19} color="#EF4444" />
                </View>
                <View style={styles.actionMenuItemTextCol}>
                  <ThemedText style={[styles.actionMenuItemTitle, { color: '#EF4444' }]}>
                    مغادرة التحدي
                  </ThemedText>
                  <ThemedText style={[styles.actionMenuItemSub, { color: colors.icon }]}>
                    الخروج من المنافسة وإيقاف احتساب خطواتك
                  </ThemedText>
                </View>
                <IconSymbol name="chevron.backward" size={14} color="#EF4444" />
              </TouchableOpacity>
            )}

            {/* 6. Delete Challenge (Admin/Creator - Danger) */}
            {isCreatorOrAdmin && (
              <TouchableOpacity
                activeOpacity={0.75}
                style={styles.actionMenuItem}
                onPress={() => {
                  onClose();
                  setTimeout(onDelete, 250);
                }}>
                <View
                  style={[
                    styles.actionMenuIconWrap,
                    { backgroundColor: isDark ? '#7F1D1D40' : '#FEE2E2' },
                  ]}>
                  <Ionicons name="trash-outline" size={19} color="#DC2626" />
                </View>
                <View style={styles.actionMenuItemTextCol}>
                  <ThemedText style={[styles.actionMenuItemTitle, { color: '#DC2626' }]}>
                    حذف التحدي نهائياً
                  </ThemedText>
                  <ThemedText style={[styles.actionMenuItemSub, { color: colors.icon }]}>
                    نقل التحدي لسلة المحذوفات وإخفائه عن الجميع
                  </ThemedText>
                </View>
                <IconSymbol name="chevron.backward" size={14} color="#DC2626" />
              </TouchableOpacity>
            )}
          </View>

          {/* Cancel Button */}
          <TouchableOpacity
            activeOpacity={0.8}
            onPress={onClose}
            style={[
              styles.actionMenuCancelBtn,
              { backgroundColor: isDark ? '#374151' : '#F1F5F9' },
            ]}>
            <ThemedText style={styles.actionMenuCancelText}>إلغاء</ThemedText>
          </TouchableOpacity>
        </TouchableOpacity>
      </TouchableOpacity>
    </Modal>
  );
}
