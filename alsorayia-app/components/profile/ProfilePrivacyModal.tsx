import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import type {
  ContactType,
  MyContact,
  PrivacyVisibility,
  ProfilePrivacySettings,
} from '@/lib/api/myProfile';
import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import {
  ActivityIndicator,
  Dimensions,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

const CONTACT_TYPES: { value: ContactType; label: string; icon: keyof typeof Ionicons.glyphMap; color: string }[] = [
  { value: 'whatsapp', label: 'واتساب', icon: 'logo-whatsapp', color: '#25D366' },
  { value: 'phone', label: 'هاتف', icon: 'call', color: '#3B82F6' },
  { value: 'email', label: 'بريد', icon: 'mail', color: '#EA4335' },
  { value: 'twitter', label: 'تويتر/X', icon: 'logo-twitter', color: '#1DA1F2' },
  { value: 'instagram', label: 'انستغرام', icon: 'logo-instagram', color: '#E4405F' },
  { value: 'facebook', label: 'فيسبوك', icon: 'logo-facebook', color: '#1877F2' },
  { value: 'telegram', label: 'تيليجرام', icon: 'paper-plane', color: '#2AABEE' },
  { value: 'linkedin', label: 'لينكدإن', icon: 'logo-linkedin', color: '#0A66C2' },
  { value: 'other', label: 'أخرى', icon: 'link', color: '#8B5CF6' },
];

interface ProfilePrivacyModalProps {
  visible: boolean;
  onClose: () => void;
  privacyForm: ProfilePrivacySettings;
  setPrivacyForm: React.Dispatch<React.SetStateAction<ProfilePrivacySettings>>;
  contactVisibilities: Record<number, PrivacyVisibility>;
  setContactVisibilities: React.Dispatch<React.SetStateAction<Record<number, PrivacyVisibility>>>;
  contacts: MyContact[];
  onSave: () => Promise<void>;
  saving: boolean;
}

export function ProfilePrivacyModal({
  visible,
  onClose,
  privacyForm,
  setPrivacyForm,
  contactVisibilities,
  setContactVisibilities,
  contacts,
  onSave,
  saving,
}: ProfilePrivacyModalProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={true}
      onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <Pressable style={styles.backdropPressable} onPress={onClose} />

        <View
          style={[
            styles.sheetContainer,
            {
              backgroundColor: isDark ? '#0F172A' : '#FFFFFF',
              borderColor: isDark ? '#334155' : '#E2E8F0',
            },
          ]}>
          {/* Top Drag Pill */}
          <View style={styles.dragPillWrap}>
            <View style={[styles.dragPill, { backgroundColor: isDark ? '#475569' : '#CBD5E1' }]} />
          </View>

          {/* Modal Header */}
          <View style={styles.headerRow}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, flex: 1 }}>
              <View
                style={[
                  styles.headerIconBadge,
                  { backgroundColor: isDark ? 'rgba(5, 150, 105, 0.25)' : 'rgba(5, 150, 105, 0.12)' },
                ]}>
                <Ionicons name="shield-checkmark" size={24} color={colors.primary} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.headerTitle, { color: colors.text }]}>إعدادات الخصوصية والظهور</Text>
                <Text style={[styles.headerSubtitle, { color: colors.icon }]}>
                  تحكم بما يظهر للعامة ولأبناء العائلة في الشجرة
                </Text>
              </View>
            </View>

            <TouchableOpacity
              onPress={onClose}
              style={[
                styles.closeCircleBtn,
                { backgroundColor: isDark ? '#1E293B' : '#F1F5F9' },
              ]}>
              <Ionicons name="close" size={20} color={colors.text} />
            </TouchableOpacity>
          </View>

          {/* Scrollable Content */}
          <ScrollView
            showsVerticalScrollIndicator={false}
            contentContainerStyle={styles.scrollContent}
            keyboardShouldPersistTaps="handled">
            {/* Informational Callout Banner */}
            <View
              style={[
                styles.infoBanner,
                {
                  backgroundColor: isDark ? 'rgba(5, 150, 105, 0.15)' : 'rgba(5, 150, 105, 0.08)',
                  borderColor: isDark ? 'rgba(5, 150, 105, 0.35)' : 'rgba(5, 150, 105, 0.25)',
                },
              ]}>
              <Ionicons name="lock-closed" size={20} color={colors.primary} />
              <Text style={[styles.infoBannerText, { color: colors.text }]}>
                اختر مستوى الخصوصية لكل جزء من بياناتك. يمكنك جعلها مفتوحة للجميع (العامة والأنساب)، مقتصرة على أبناء العائلة، أو مخفية بالكامل.
              </Text>
            </View>

            {/* General Privacy Selectors */}
            <PrivacyRow
              icon="image-outline"
              title="الصورة الشخصية"
              subtitle="ظهور صورتك الشخصية في شجرة العائلة وملفك"
              value={privacyForm.photo ?? 'public'}
              onChange={(val) => setPrivacyForm((f) => ({ ...f, photo: val }))}
              colors={colors}
              isDark={isDark}
            />

            <PrivacyRow
              icon="calendar-outline"
              title="تاريخ الميلاد"
              subtitle="ظهور تاريخ ويوم ميلادك وحساب العمر"
              value={privacyForm.birth_date ?? 'family'}
              onChange={(val) => setPrivacyForm((f) => ({ ...f, birth_date: val }))}
              colors={colors}
              isDark={isDark}
            />

            <PrivacyRow
              icon="location-outline"
              title="أماكن الإقامة والسكن"
              subtitle="المدينة والحي والروابط الجغرافية لمواقع سكنك"
              value={privacyForm.location ?? 'public'}
              onChange={(val) => setPrivacyForm((f) => ({ ...f, location: val }))}
              colors={colors}
              isDark={isDark}
            />

            <PrivacyRow
              icon="document-text-outline"
              title="النبذة الشخصية"
              subtitle="السيرة الموجزة والنبذة المكتوبة في ملفك الشخصي"
              value={privacyForm.biography ?? 'public'}
              onChange={(val) => setPrivacyForm((f) => ({ ...f, biography: val }))}
              colors={colors}
              isDark={isDark}
            />

            <PrivacyRow
              icon="briefcase-outline"
              title="المهنة ومجال العمل"
              subtitle="المسمى الوظيفي ومجال تخصصك المهني"
              value={privacyForm.occupation ?? 'public'}
              onChange={(val) => setPrivacyForm((f) => ({ ...f, occupation: val }))}
              colors={colors}
              isDark={isDark}
            />

            <PrivacyRow
              icon="call-outline"
              title="القاعدة العامة لوسائل التواصل"
              subtitle="المستوى الافتراضي لظهور وسائل التواصل غير المخصصة"
              value={privacyForm.contacts ?? 'family'}
              onChange={(val) => setPrivacyForm((f) => ({ ...f, contacts: val }))}
              colors={colors}
              isDark={isDark}
            />

            {/* Individual Contact Accounts Customization */}
            {contacts && contacts.length > 0 && (
              <View
                style={[
                  styles.contactsSection,
                  { borderTopColor: isDark ? '#334155' : '#E2E8F0' },
                ]}>
                <View style={styles.contactsSectionHeader}>
                  <Text style={[styles.contactsSectionTitle, { color: colors.text }]}>
                    تخصيص مستوى الظهور لكل حساب تواصل
                  </Text>
                  <Text style={[styles.contactsSectionSubtitle, { color: colors.icon }]}>
                    يمكنك تحديد خصوصية مستقلة لكل رقم أو بريد مسجّل على حدة
                  </Text>
                </View>

                <View style={{ gap: 10 }}>
                  {contacts.map((contact) => {
                    const currentVis: PrivacyVisibility =
                      contactVisibilities[contact.id] ?? (contact.visibility || 'family');
                    const meta = CONTACT_TYPES.find((t) => t.value === contact.type);

                    return (
                      <View
                        key={contact.id}
                        style={[
                          styles.contactItemCard,
                          {
                            backgroundColor: isDark ? '#1E293B' : '#F8FAFC',
                            borderColor: isDark ? '#334155' : '#E2E8F0',
                          },
                        ]}>
                        <View style={styles.contactItemHeader}>
                          <View
                            style={[
                              styles.contactIconBadge,
                              { backgroundColor: (meta?.color || colors.primary) + '18' },
                            ]}>
                            <Ionicons
                              name={meta?.icon || 'call'}
                              size={16}
                              color={meta?.color || colors.primary}
                            />
                          </View>
                          <View style={{ flex: 1 }}>
                            <Text
                              style={[styles.contactValueText, { color: colors.text }]}
                              numberOfLines={1}>
                              {contact.value}
                            </Text>
                            {contact.label ? (
                              <Text style={[styles.contactLabelText, { color: colors.icon }]}>
                                {contact.label}
                              </Text>
                            ) : null}
                          </View>
                        </View>

                        <View style={styles.segmentedRow}>
                          {(
                            [
                              { key: 'family', label: 'العائلة فقط', icon: 'people-outline' },
                              { key: 'public', label: 'الجميع', icon: 'globe-outline' },
                              { key: 'private', label: 'مخفي', icon: 'lock-closed-outline' },
                            ] as const
                          ).map((opt) => {
                            const isSelected = currentVis === opt.key;
                            return (
                              <Pressable
                                key={opt.key}
                                onPress={() =>
                                  setContactVisibilities((prev) => ({
                                    ...prev,
                                    [contact.id]: opt.key,
                                  }))
                                }
                                style={({ pressed }) => [
                                  styles.miniSegmentBtn,
                                  {
                                    borderColor: isSelected ? colors.primary : isDark ? '#334155' : '#CBD5E1',
                                    backgroundColor: isSelected
                                      ? colors.primary
                                      : isDark
                                        ? '#0F172A'
                                        : '#FFFFFF',
                                    opacity: pressed ? 0.85 : 1,
                                  },
                                ]}>
                                <Ionicons
                                  name={opt.icon}
                                  size={12}
                                  color={isSelected ? '#FFFFFF' : isDark ? '#94A3B8' : colors.icon}
                                />
                                <Text
                                  style={[
                                    styles.miniSegmentText,
                                    {
                                      color: isSelected ? '#FFFFFF' : isDark ? '#E2E8F0' : '#475569',
                                      fontFamily: isSelected ? 'Tajawal-Bold' : 'Tajawal-Medium',
                                    },
                                  ]}>
                                  {opt.label}
                                </Text>
                              </Pressable>
                            );
                          })}
                        </View>
                      </View>
                    );
                  })}
                </View>
              </View>
            )}
          </ScrollView>

          {/* Sticky Bottom Save Action */}
          <View
            style={[
              styles.footerContainer,
              {
                backgroundColor: isDark ? '#0F172A' : '#FFFFFF',
                borderTopColor: isDark ? '#334155' : '#E2E8F0',
              },
            ]}>
            <TouchableOpacity
              disabled={saving}
              style={[
                styles.saveButton,
                { backgroundColor: colors.primary, opacity: saving ? 0.75 : 1 },
              ]}
              onPress={() => void onSave()}>
              {saving ? (
                <ActivityIndicator size="small" color="#FFFFFF" />
              ) : (
                <Ionicons name="shield-checkmark" size={20} color="#FFFFFF" />
              )}
              <Text style={styles.saveButtonText}>حفظ إعدادات الخصوصية (تطبيق فوري)</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

function PrivacyRow({
  icon,
  title,
  subtitle,
  value,
  onChange,
  colors,
  isDark,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  subtitle: string;
  value: PrivacyVisibility;
  onChange: (val: PrivacyVisibility) => void;
  colors: any;
  isDark: boolean;
}) {
  const options: { key: PrivacyVisibility; label: string; icon: keyof typeof Ionicons.glyphMap }[] = [
    { key: 'public', label: 'مفتوح للجميع', icon: 'globe-outline' },
    { key: 'family', label: 'العائلة فقط', icon: 'people-outline' },
    { key: 'private', label: 'مخفي نهائياً', icon: 'lock-closed-outline' },
  ];

  return (
    <View
      style={[
        styles.privacyCard,
        {
          backgroundColor: isDark ? '#1E293B' : '#F8FAFC',
          borderColor: isDark ? '#334155' : '#E2E8F0',
        },
      ]}>
      <View style={styles.privacyCardTopRow}>
        <View
          style={[
            styles.privacyCardIconBadge,
            { backgroundColor: colors.primary + '18' },
          ]}>
          <Ionicons name={icon} size={18} color={colors.primary} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={[styles.privacyCardTitle, { color: colors.text }]}>{title}</Text>
          <Text style={[styles.privacyCardSubtitle, { color: colors.icon }]}>{subtitle}</Text>
        </View>
      </View>

      <View style={styles.segmentedRow}>
        {options.map((opt) => {
          const isSelected = value === opt.key;
          return (
            <Pressable
              key={opt.key}
              onPress={() => onChange(opt.key)}
              style={({ pressed }) => [
                styles.segmentBtn,
                {
                  borderColor: isSelected ? colors.primary : isDark ? '#334155' : '#CBD5E1',
                  backgroundColor: isSelected
                    ? colors.primary
                    : isDark
                      ? '#0F172A'
                      : '#FFFFFF',
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <Ionicons
                name={opt.icon}
                size={13}
                color={isSelected ? '#FFFFFF' : isDark ? '#94A3B8' : colors.icon}
              />
              <Text
                style={[
                  styles.segmentText,
                  {
                    color: isSelected ? '#FFFFFF' : isDark ? '#E2E8F0' : '#475569',
                    fontFamily: isSelected ? 'Tajawal-Bold' : 'Tajawal-Medium',
                  },
                ]}>
                {opt.label}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'flex-end',
  },
  backdropPressable: {
    flex: 1,
  },
  sheetContainer: {
    maxHeight: SCREEN_HEIGHT * 0.9,
    minHeight: SCREEN_HEIGHT * 0.65,
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderWidth: 1,
    borderBottomWidth: 0,
    overflow: 'hidden',
  },
  dragPillWrap: {
    alignItems: 'center',
    paddingTop: 12,
    paddingBottom: 6,
  },
  dragPill: {
    width: 44,
    height: 5,
    borderRadius: 3,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 12,
  },
  headerIconBadge: {
    width: 44,
    height: 44,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
  },
  headerSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    marginTop: 2,
  },
  closeCircleBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingBottom: 24,
    gap: 12,
  },
  infoBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 4,
  },
  infoBannerText: {
    flex: 1,
    fontFamily: 'Tajawal-Regular',
    fontSize: 12.5,
    lineHeight: 19,
  },
  privacyCard: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
    gap: 10,
  },
  privacyCardTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  privacyCardIconBadge: {
    width: 36,
    height: 36,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  privacyCardTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  privacyCardSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11.5,
    marginTop: 1,
  },
  segmentedRow: {
    flexDirection: 'row',
    gap: 6,
    marginTop: 2,
  },
  segmentBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    paddingVertical: 9,
    paddingHorizontal: 2,
    borderRadius: 11,
    borderWidth: 1.5,
  },
  segmentText: {
    fontSize: 11,
  },
  contactsSection: {
    marginTop: 10,
    paddingTop: 16,
    borderTopWidth: 1,
  },
  contactsSectionHeader: {
    marginBottom: 12,
  },
  contactsSectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  contactsSectionSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    marginTop: 2,
  },
  contactItemCard: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 12,
    gap: 10,
  },
  contactItemHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  contactIconBadge: {
    width: 32,
    height: 32,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  contactValueText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  contactLabelText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
  },
  miniSegmentBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    paddingVertical: 7,
    borderRadius: 10,
    borderWidth: 1.2,
  },
  miniSegmentText: {
    fontSize: 11,
  },
  footerContainer: {
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderTopWidth: 1,
    paddingBottom: Platform.OS === 'ios' ? 28 : 14,
  },
  saveButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderRadius: 14,
    paddingVertical: 14,
    shadowColor: '#059669',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 4,
  },
  saveButtonText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
});
