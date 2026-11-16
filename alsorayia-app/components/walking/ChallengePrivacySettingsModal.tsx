import { ThemedText } from '@/components/themed-text';
import { Colors, silaTheme } from '@/constants/theme';
import type { MemberChallengeSettings } from '@/lib/api/walkingApi';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useEffect, useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActivityIndicator,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Switch,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';


type Props = {
  visible: boolean;
  groupId: number;
  groupName: string;
  isCreator?: boolean;
  isPublic?: boolean;
  initialSettings?: MemberChallengeSettings | null;
  onClose: () => void;
  onSaveSettings: (
    groupId: number,
    settings: MemberChallengeSettings
  ) => Promise<{ success: boolean; message?: string }>;
  onLeaveChallenge: () => void;
};

export function ChallengePrivacySettingsModal({
  visible,
  groupId,
  groupName,
  isCreator = false,
  isPublic = true,
  initialSettings,
  onClose,
  onSaveSettings,
  onLeaveChallenge,
}: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  const [isAnonymous, setIsAnonymous] = useState<boolean>(
    isPublic ? (initialSettings?.is_anonymous ?? false) : false
  );
  const [shareDetails, setShareDetails] = useState<boolean>(
    initialSettings?.share_details ?? true
  );
  const [notificationsEnabled, setNotificationsEnabled] = useState<boolean>(
    initialSettings?.notifications_enabled ?? true
  );
  const [applyToAll, setApplyToAll] = useState<boolean>(false);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (initialSettings) {
      setIsAnonymous(isPublic ? Boolean(initialSettings.is_anonymous) : false);
      setShareDetails(initialSettings.share_details !== false);
      setNotificationsEnabled(initialSettings.notifications_enabled !== false);
    } else if (!isPublic) {
      setIsAnonymous(false);
    }
  }, [initialSettings, visible, isPublic]);

  const handleToggleAnonymous = (val: boolean) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    setIsAnonymous(val);
  };

  const handleToggleDetails = (val: boolean) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    setShareDetails(val);
  };

  const handleToggleNotifications = (val: boolean) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    setNotificationsEnabled(val);
  };

  const handleToggleApplyToAll = (val: boolean) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    setApplyToAll(val);
  };

  const handleSave = async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch { }

    setIsSaving(true);
    try {
      const res = await onSaveSettings(groupId, {
        is_anonymous: isPublic ? isAnonymous : false,
        share_details: shareDetails,
        notifications_enabled: notificationsEnabled,
        apply_to_all: applyToAll,
      });

      if (res.success) {
        onClose();
        setTimeout(() => {
          CustomAlert.alert('تم بنجاح', res.message || 'تم حفظ إعدادات الخصوصية بنجاح.');
        }, 350);
      } else {
        CustomAlert.alert('تنبيه', res.message || 'تعذر حفظ الإعدادات، يرجى المحاولة لاحقاً.');
      }
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View
          style={[
            styles.content,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}>
          {/* Header */}
          <View style={styles.header}>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn} activeOpacity={0.7} hitSlop={8}>
              <Ionicons name="close" size={20} color={colors.icon} />
            </TouchableOpacity>

            <View style={styles.headerTitleWrap}>
              <Ionicons name="shield-checkmark" size={20} color={silaTheme.primary} />
              <ThemedText style={styles.headerTitle}>إعدادات الخصوصية للتحدي</ThemedText>
            </View>

            <View style={{ width: 36 }} />
          </View>

          <ScrollView
            showsVerticalScrollIndicator={false}
            contentContainerStyle={styles.scroll}>
            {/* Target Challenge Info */}
            <View
              style={[
                styles.groupTag,
                {
                  backgroundColor: isDark ? '#064E3B22' : '#ECFDF5',
                  borderColor: isDark ? '#065F46' : '#A7F3D0',
                },
              ]}>
              <Ionicons name="flag-outline" size={17} color={silaTheme.primary} />
              <ThemedText style={[styles.groupTagLabel, { color: silaTheme.primary }]}>
                التحدي الحالي:
              </ThemedText>
              <ThemedText
                numberOfLines={1}
                style={[styles.groupTagName, { color: colors.text }]}>
                {groupName}
              </ThemedText>
            </View>

            {/* Privacy Options Container */}
            <View style={styles.sectionContainer}>
              {/* Option 1: Ghost / Incognito Mode (Hidden in Private Challenges) */}
              {!isPublic ? (
                <View
                  style={[
                    styles.optionCard,
                    {
                      backgroundColor: isDark ? 'rgba(59, 130, 246, 0.08)' : '#EFF6FF',
                      borderColor: isDark ? 'rgba(59, 130, 246, 0.22)' : '#DBEAFE',
                    },
                  ]}>
                  <View style={styles.optionTopRow}>
                    <View style={[styles.optionIconContainer, { backgroundColor: isDark ? 'rgba(59, 130, 246, 0.18)' : '#DBEAFE' }]}>
                      <Ionicons
                        name="people-outline"
                        size={20}
                        color={isDark ? '#60A5FA' : '#2563EB'}
                      />
                    </View>
                    <View style={styles.optionTextContainer}>
                      <View style={styles.titleWithBadge}>
                        <ThemedText style={[styles.optionTitle, { color: isDark ? '#93C5FD' : '#1D4ED8' }]}>
                          الظهور معلن لجميع المشتركين
                        </ThemedText>
                        <View style={[styles.activeBadge, { backgroundColor: '#2563EB' }]}>
                          <ThemedText style={styles.activeBadgeText}>تحدي خاص</ThemedText>
                        </View>
                      </View>
                      <ThemedText style={[styles.optionDescription, { color: isDark ? '#BFDBFE' : '#1E40AF' }]}>
                        في التحديات الخاصة، يكون الظهور معلناً دائماً بالاسم والصورة لجميع الأعضاء لضمان الشفافية والمنافسة العادلة، ولا يتوفر وضع المتخفي.
                      </ThemedText>
                    </View>
                  </View>
                </View>
              ) : (
                <View
                  style={[
                    styles.optionCard,
                    {
                      backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                      borderColor: isAnonymous
                        ? silaTheme.primary
                        : isDark
                          ? '#374151'
                          : '#E5E7EB',
                    },
                  ]}>
                  <View style={styles.optionTopRow}>
                    <View style={[styles.optionIconContainer, { backgroundColor: isDark ? '#064E3B44' : '#ECFDF5' }]}>
                      <Ionicons
                        name="eye-off-outline"
                        size={20}
                        color={isAnonymous ? silaTheme.primary : colors.icon}
                      />
                    </View>
                    <View style={styles.optionTextContainer}>
                      <View style={styles.titleWithBadge}>
                        <ThemedText style={styles.optionTitle}>وضع المتسابق المتخفي</ThemedText>
                        {isAnonymous && (
                          <View style={styles.activeBadge}>
                            <ThemedText style={styles.activeBadgeText}>مُفعّل</ThemedText>
                          </View>
                        )}
                        {Boolean(initialSettings?.is_inherited && isAnonymous) && (
                          <View style={[styles.activeBadge, { backgroundColor: '#F59E0B' }]}>
                            <ThemedText style={styles.activeBadgeText}>موروث من العام</ThemedText>
                          </View>
                        )}
                      </View>
                      <ThemedText style={[styles.optionDescription, { color: colors.icon }]}>
                        تُحتسب خطواتك وتُضاف لرصيد التحدي بالكامل، لكن يظهر اسمك وصورتك كـ{' '}
                        <ThemedText style={styles.highlightText}>
                          «متسابق متخفي»
                        </ThemedText>{' '}
                        لبقية المشتركين حفاظاً على خصوصيتك.
                      </ThemedText>
                    </View>
                    <Switch
                      value={isAnonymous}
                      onValueChange={handleToggleAnonymous}
                      trackColor={{ false: isDark ? '#4B5563' : '#D1D5DB', true: silaTheme.primary }}
                      thumbColor={Platform.OS === 'ios' ? undefined : '#FFFFFF'}
                    />
                  </View>
                </View>
              )}

              {/* Option 2: Share Activity Details - مخفي مؤقتاً بصرياً مع بقاء الكود مفعلاً وتمرير قيمته للخادم */}
              {false && (
                <View
                  style={[
                    styles.optionCard,
                    {
                      backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                      borderColor: isDark ? '#374151' : '#E5E7EB',
                    },
                  ]}>
                  <View style={styles.optionTopRow}>
                    <View style={[styles.optionIconContainer, { backgroundColor: isDark ? '#374151' : '#F3F4F6' }]}>
                      <Ionicons
                        name="stats-chart-outline"
                        size={19}
                        color={shareDetails ? silaTheme.primary : colors.icon}
                      />
                    </View>
                    <View style={styles.optionTextContainer}>
                      <ThemedText style={styles.optionTitle}>مشاركة تفاصيل النشاط</ThemedText>
                      <ThemedText style={[styles.optionDescription, { color: colors.icon }]}>
                        السماح للأعضاء برؤية تفاصيل المسافة والسعرات، أو الاكتفاء بعدد الخطوات فقط عند التعطيل.
                      </ThemedText>
                    </View>
                    <Switch
                      value={shareDetails}
                      onValueChange={handleToggleDetails}
                      trackColor={{ false: isDark ? '#4B5563' : '#D1D5DB', true: silaTheme.primary }}
                      thumbColor={Platform.OS === 'ios' ? undefined : '#FFFFFF'}
                    />
                  </View>
                </View>
              )}

              {/* Option 3: Challenge Notifications */}
              <View
                style={[
                  styles.optionCard,
                  {
                    backgroundColor: isDark ? '#1F2937' : '#F9FAFB',
                    borderColor: isDark ? '#374151' : '#E5E7EB',
                  },
                ]}>
                <View style={styles.optionTopRow}>
                  <View style={[styles.optionIconContainer, { backgroundColor: isDark ? '#374151' : '#F3F4F6' }]}>
                    <Ionicons
                      name={notificationsEnabled ? 'notifications-outline' : 'notifications-off-outline'}
                      size={19}
                      color={notificationsEnabled ? silaTheme.primary : colors.icon}
                    />
                  </View>
                  <View style={styles.optionTextContainer}>
                    <ThemedText style={styles.optionTitle}>تنبيهات وإشعارات التحدي</ThemedText>
                    <ThemedText style={[styles.optionDescription, { color: colors.icon }]}>
                      استقبال إشعارات تغير الصدارة والتشجيع ورسائل الإنجاز في هذا التحدي.
                    </ThemedText>
                  </View>
                  <Switch
                    value={notificationsEnabled}
                    onValueChange={handleToggleNotifications}
                    trackColor={{ false: isDark ? '#4B5563' : '#D1D5DB', true: silaTheme.primary }}
                    thumbColor={Platform.OS === 'ios' ? undefined : '#FFFFFF'}
                  />
                </View>
              </View>

              {/* Option 4: Apply to all challenges */}
              <View
                style={[
                  styles.optionCard,
                  {
                    backgroundColor: isDark ? '#1E3A5F22' : '#EFF6FF',
                    borderColor: applyToAll ? '#3B82F6' : isDark ? '#1E3A8A' : '#BFDBFE',
                  },
                ]}>
                <View style={styles.optionTopRow}>
                  <View style={[styles.optionIconContainer, { backgroundColor: isDark ? '#1E3A8A44' : '#DBEAFE' }]}>
                    <Ionicons
                      name="globe-outline"
                      size={19}
                      color="#2563EB"
                    />
                  </View>
                  <View style={styles.optionTextContainer}>
                    <ThemedText style={[styles.optionTitle, { color: '#2563EB' }]}>
                      تطبيق على كافة التحديات
                    </ThemedText>
                    <ThemedText style={[styles.optionDescription, { color: isDark ? '#93C5FD' : '#1E40AF' }]}>
                      تعميم هذه الإعدادات على جميع التحديات المشترك بها حالياً وجعلها الإعداد الافتراضي لأي تحدٍ تنضم إليه مستقبلاً.
                    </ThemedText>
                  </View>
                  <Switch
                    value={applyToAll}
                    onValueChange={handleToggleApplyToAll}
                    trackColor={{ false: isDark ? '#4B5563' : '#D1D5DB', true: '#2563EB' }}
                    thumbColor={Platform.OS === 'ios' ? undefined : '#FFFFFF'}
                  />
                </View>
              </View>
            </View>

            {/* Save Settings Button */}
            <TouchableOpacity
              activeOpacity={0.85}
              disabled={isSaving}
              onPress={handleSave}
              style={[styles.saveBtn, { backgroundColor: silaTheme.primary }]}>
              {isSaving ? (
                <ActivityIndicator color="#FFFFFF" size="small" />
              ) : (
                <View style={styles.saveBtnContent}>
                  <Ionicons name="checkmark-circle-outline" size={20} color="#FFFFFF" />
                  <ThemedText style={styles.saveBtnText}>حفظ الإعدادات</ThemedText>
                </View>
              )}
            </TouchableOpacity>

            {/* Danger Zone: Leave Challenge */}
            <View
              style={[
                styles.dangerZone,
                {
                  backgroundColor: isDark ? '#450A0A33' : '#FEF2F2',
                  borderColor: isDark ? '#991B1B' : '#FECACA',
                },
              ]}>
              <View style={styles.dangerHeader}>
                <Ionicons name="exit-outline" size={20} color="#DC2626" />
                <ThemedText style={styles.dangerTitle}>مغادرة التحدي</ThemedText>
              </View>

              <ThemedText style={[styles.dangerDescription, { color: isDark ? '#FCA5A5' : '#991B1B' }]}>
                {isCreator
                  ? 'بما أنك منشئ التحدي، عند مغادرتك ستنتقل إدارة التحدي تلقائياً إلى العضو الأقدم، وستبقى خطواتك محفوظة في سجلك الشخصي.'
                  : 'عند المغادرة، سيتم إخراجك من لوحة صدارة هذا التحدي وستبقى خطواتك مسجلة في ملفك الشخصي بالكامل.'}
              </ThemedText>

              <TouchableOpacity
                activeOpacity={0.8}
                onPress={onLeaveChallenge}
                style={[
                  styles.leaveBtn,
                  {
                    backgroundColor: isDark ? '#7F1D1D' : '#EF4444',
                  },
                ]}>
                <ThemedText style={styles.leaveBtnText}>تأكيد مغادرة التحدي</ThemedText>
              </TouchableOpacity>
            </View>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'flex-end',
  },
  content: {
    borderTopLeftRadius: 26,
    borderTopRightRadius: 26,
    maxHeight: '90%',
    paddingBottom: Platform.OS === 'ios' ? 34 : 20,
    borderWidth: 1,
    borderBottomWidth: 0,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 18,
    paddingBottom: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(156, 163, 175, 0.25)',
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(156, 163, 175, 0.15)',
  },
  headerTitleWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  headerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16.5,
    textAlign: 'center',
  },
  scroll: {
    padding: 20,
    gap: 16,
  },
  groupTag: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 11,
    borderRadius: 14,
    borderWidth: 1,
    gap: 8,
  },
  groupTagLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  groupTagName: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13.5,
    flex: 1,
  },
  sectionContainer: {
    gap: 12,
  },
  optionCard: {
    borderRadius: 16,
    padding: 14,
    borderWidth: 1,
  },
  optionTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  optionIconContainer: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  optionTextContainer: {
    flex: 1,
    gap: 4,
  },
  titleWithBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  optionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
  },
  activeBadge: {
    backgroundColor: '#059669',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
  activeBadgeText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 11,
  },
  optionDescription: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12.5,
    lineHeight: 18,
  },
  highlightText: {
    fontFamily: 'Tajawal-Bold',
    color: silaTheme.primary,
  },
  saveBtn: {
    height: 50,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  saveBtnContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  saveBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  dangerZone: {
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    gap: 10,
    marginTop: 8,
  },
  dangerHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  dangerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
    color: '#DC2626',
  },
  dangerDescription: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12.5,
    lineHeight: 18,
  },
leaveBtn: {
  height: 44,
    borderRadius: 12,
      alignItems: 'center',
        justifyContent: 'center',
          marginTop: 4,
  },
leaveBtnText: {
  color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
      fontSize: 14,
  },
});
