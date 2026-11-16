import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { Pressable, ScrollView, StyleProp, Text, TouchableOpacity, View, ViewStyle } from 'react-native';
import type { MyProfilePayload } from '@/lib/api/myProfile';
import { CustomAlert } from '@/components/ui/AlertModal';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { CONTACT_TYPES, type ContactFormState } from '../helpers';
import { SectionHeader, ModernInput } from './ui';
import { styles } from '../styles';

interface ContactsTabProps {
  data: MyProfilePayload;
  contactForm: ContactFormState;
  setContactForm: Dispatch<SetStateAction<ContactFormState>>;
  removeContact: (id: number) => Promise<{ ok: boolean; message: string }>;
  handleAddContact: () => unknown;
  saving: boolean;
  cardStyle: StyleProp<ViewStyle>;
}

export function ContactsTab({
  data,
  contactForm,
  setContactForm,
  removeContact,
  handleAddContact,
  saving,
  cardStyle,
}: ContactsTabProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
            <View style={cardStyle}>
              <SectionHeader
                icon="call-outline"
                title="حسابات وسنوات التواصل"
                subtitle="أضف أرقامك وحساباتك لتواصل العائلة وتفعيل OTP"
              />

              <View style={[styles.whatsappNoticeBox, { backgroundColor: isDark ? '#78350F' : '#FEF3C7', borderColor: isDark ? '#92400E' : '#FCD34D' }]}>
                <Ionicons name="information-circle" size={20} color={isDark ? '#FCD34D' : '#92400E'} />
                <Text style={[styles.whatsappNoticeText, { color: isDark ? '#FCD34D' : '#92400E' }]}>
                  تنبيه: لتفعيل تسجيل الدخول بالواتساب، أضف رقمك كحساب بنوع (واتساب).
                </Text>
              </View>

              {/* List of Contacts */}
              <View style={{ gap: 10, marginVertical: 12 }}>
                {data.contacts.map((c) => {
                  const meta = CONTACT_TYPES.find((t) => t.value === c.type) || {
                    color: colors.primary,
                    icon: 'link' as const,
                    label: c.type,
                  };

                  return (
                    <View key={c.id} style={[styles.contactCardItem, { backgroundColor: isDark ? '#111827' : '#F8FAFC', borderColor: isDark ? '#334155' : '#E2E8F0' }]}>
                      <View style={[styles.contactBadgeIcon, { backgroundColor: meta.color + '20' }]}>
                        <Ionicons name={meta.icon} size={22} color={meta.color} />
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.contactMetaLabel, { color: meta.color }]}>{meta.label}</Text>
                        <Text style={[styles.contactValueText, { color: colors.text }]}>{c.value}</Text>
                        {c.label ? <Text style={[styles.contactSubLabel, { color: colors.icon }]}>{c.label}</Text> : null}
                      </View>
                      <TouchableOpacity
                        onPress={() =>
                          CustomAlert.alert('حذف الحساب', 'هل أنت تأكد من حذف هذا الحساب؟', [
                            { text: 'إلغاء', style: 'cancel' },
                            {
                              text: 'حذف',
                              style: 'destructive',
                              onPress: () => void removeContact(c.id).then((r) => CustomAlert.alert(r.ok ? 'تم' : 'خطأ', r.message)),
                            },
                          ])
                        }
                        style={styles.deleteCircleBtn}>
                        <Ionicons name="trash-outline" size={18} color="#EF4444" />
                      </TouchableOpacity>
                    </View>
                  );
                })}
              </View>

              {/* Add New Contact Form */}
              <Text style={[styles.subFormTitle, { color: colors.text }]}>إضافة حساب تواصل جديد</Text>

              <Text style={[styles.inputLabel, { color: colors.icon }]}>نوع الحساب</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalChipsRow} keyboardShouldPersistTaps="handled">
                {CONTACT_TYPES.map((t) => {
                  const isSelected = contactForm.type === t.value;
                  return (
                    <Pressable
                      key={t.value}
                      onPress={() => setContactForm((f) => ({ ...f, type: t.value }))}
                      style={[
                        styles.chipPill,
                        {
                          backgroundColor: isSelected ? t.color : isDark ? '#111827' : '#F1F5F9',
                          borderColor: isSelected ? t.color : isDark ? '#334155' : '#E2E8F0',
                        },
                      ]}>
                      <Ionicons name={t.icon} size={15} color={isSelected ? '#FFF' : t.color} />
                      <Text style={[styles.chipPillText, { color: isSelected ? '#FFF' : colors.text }]}>{t.label}</Text>
                    </Pressable>
                  );
                })}
              </ScrollView>

              <ModernInput
                label="الرقم / المعرف / الرابط"
                icon="at-outline"
                placeholder="05XXXXXXXX / username"
                value={contactForm.value}
                onChangeText={(v) => setContactForm((f) => ({ ...f, value: v }))}
              />
              <ModernInput
                label="تسمية توضيحية (اختياري)"
                icon="text-outline"
                placeholder="مثال: رقم العمل / الشخصي"
                value={contactForm.label}
                onChangeText={(v) => setContactForm((f) => ({ ...f, label: v }))}
              />

              <TouchableOpacity
                disabled={saving}
                style={[styles.primaryBtn, { backgroundColor: colors.primary, marginTop: 12 }]}
                onPress={() => void handleAddContact()}>
                <Ionicons name="add-circle-outline" size={20} color="#FFF" />
                <Text style={styles.primaryBtnText}>إضافة حساب التواصل</Text>
              </TouchableOpacity>
            </View>
  );
}
