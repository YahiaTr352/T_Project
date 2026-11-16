import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { Image, Pressable, ScrollView, StyleProp, Text, TouchableOpacity, View, ViewStyle } from 'react-native';
import type { MyPerson, MyPersonBrief, MyProfilePayload } from '@/lib/api/myProfile';
import { DatePickerField } from '@/components/ui/DatePickerField';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import type { ChildFormState } from '../helpers';
import { SectionHeader, ModernInput } from './ui';
import { styles } from '../styles';

interface ChildrenTabProps {
  data: MyProfilePayload;
  person: MyPerson;
  childForm: ChildFormState;
  setChildForm: Dispatch<SetStateAction<ChildFormState>>;
  handleAddChild: () => unknown;
  saving: boolean;
  cardStyle: StyleProp<ViewStyle>;
}

export function ChildrenTab({
  data,
  person,
  childForm,
  setChildForm,
  handleAddChild,
  saving,
  cardStyle,
}: ChildrenTabProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
            <View style={cardStyle}>
              <SectionHeader
                icon="people-outline"
                title="الأبناء والذرية"
                subtitle="أضف وسجّل أطفالك لتوسيع شجرة العائلة"
              />

              {/* Children Notice Card (Tree Documentation Policy) */}
              <View
                style={[
                  styles.childrenNoticeCard,
                  {
                    backgroundColor: isDark ? '#064E3B20' : '#F0FDF4',
                    borderColor: isDark ? '#065F46' : '#BBF7D0',
                  },
                ]}>
                <Ionicons name="shield-checkmark" size={19} color={isDark ? '#34D399' : '#059669'} style={{ marginTop: 2 }} />
                <View style={{ flex: 1 }}>
                  <Text style={[styles.childrenNoticeTitle, { color: isDark ? '#34D399' : '#166534' }]}>
                    توثيق أسماء الأبناء في شجرة العائلة
                  </Text>
                  <Text style={[styles.childrenNoticeText, { color: isDark ? '#CBD5E1' : '#15803D' }]}>
                    أسماء الأبناء تُعتمد وتوثق رسمياً في الشجرة بعد إضافتها ولا يمكن تعديلها من التطبيق. في حال وجود خطأ في كتابة اسم أي ابن أو ابنة، يرجى التواصل مع إدارة العائلة.
                  </Text>
                </View>
              </View>


              <View style={{ gap: 10, marginVertical: 12 }}>
                {data.children.map((c) => (
                  <View key={c.id} style={[styles.childCardItem, { backgroundColor: isDark ? '#111827' : '#F8FAFC', borderColor: isDark ? '#334155' : '#E2E8F0' }]}>
                    {c.avatar ? (
                      <Image source={{ uri: c.avatar }} style={styles.childAvatarImg} />
                    ) : (
                      <View style={[styles.childAvatarFallback, { backgroundColor: colors.primary + '18' }]}>
                        <Ionicons name="person" size={20} color={colors.primary} />
                      </View>
                    )}
                    <View style={{ flex: 1 }}>
                      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                        <Text style={[styles.childNameText, { color: colors.text }]}>{c.full_name}</Text>
                        <Ionicons name="lock-closed" size={12} color={colors.icon} />
                      </View>
                      {c.birth_date ? (
                        <Text style={[styles.childSubText, { color: colors.icon }]}>تاريخ الميلاد: {c.birth_date}</Text>
                      ) : null}
                    </View>
                  </View>
                ))}
              </View>

              <Text style={[styles.subFormTitle, { color: colors.text }]}>إضافة ابن / ابنة جديد</Text>

              <ModernInput
                label="الاسم"
                icon="person-add-outline"
                value={childForm.first_name}
                onChangeText={(v) => setChildForm((f) => ({ ...f, first_name: v }))}
              />

              <Text style={[styles.inputLabel, { color: colors.icon }]}>الجنس</Text>
              <View style={styles.genderToggleRow}>
                <Pressable
                  onPress={() => setChildForm((f) => ({ ...f, gender: 'male' }))}
                  style={[
                    styles.genderPill,
                    {
                      backgroundColor: childForm.gender === 'male' ? '#3B82F6' : isDark ? '#111827' : '#F1F5F9',
                      borderColor: childForm.gender === 'male' ? '#3B82F6' : isDark ? '#334155' : '#E2E8F0',
                    },
                  ]}>
                  <Ionicons name="man-outline" size={16} color={childForm.gender === 'male' ? '#FFF' : '#3B82F6'} />
                  <Text style={[styles.genderPillText, { color: childForm.gender === 'male' ? '#FFF' : colors.text }]}>ذكر 👦</Text>
                </Pressable>

                <Pressable
                  onPress={() => setChildForm((f) => ({ ...f, gender: 'female' }))}
                  style={[
                    styles.genderPill,
                    {
                      backgroundColor: childForm.gender === 'female' ? '#EC4899' : isDark ? '#111827' : '#F1F5F9',
                      borderColor: childForm.gender === 'female' ? '#EC4899' : isDark ? '#334155' : '#E2E8F0',
                    },
                  ]}>
                  <Ionicons name="woman-outline" size={16} color={childForm.gender === 'female' ? '#FFF' : '#EC4899'} />
                  <Text style={[styles.genderPillText, { color: childForm.gender === 'female' ? '#FFF' : colors.text }]}>أنثى 👧</Text>
                </Pressable>
              </View>

              <DatePickerField
                label="تاريخ الميلاد"
                value={childForm.birth_date}
                onChange={(v) => setChildForm((f) => ({ ...f, birth_date: v }))}
                maximumDate={new Date()}
              />

              {person.gender === 'female' && (!data.husband_candidates || data.husband_candidates.length === 0) ? (
                <View
                  style={[
                    styles.childrenNoticeCard,
                    {
                      backgroundColor: isDark ? '#78350F20' : '#FFFBEB',
                      borderColor: isDark ? '#92400E' : '#FDE68A',
                      marginVertical: 10,
                    },
                  ]}>
                  <Ionicons name="alert-circle-outline" size={20} color={isDark ? '#FBBF24' : '#D97706'} style={{ marginTop: 2 }} />
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.childrenNoticeTitle, { color: isDark ? '#FBBF24' : '#B45309' }]}>
                      توثيق الزواج أولاً مطلوب
                    </Text>
                    <Text style={[styles.childrenNoticeText, { color: isDark ? '#FDE68A' : '#92400E' }]}>
                      لا يوجد أزواج مسجلون في ملفكِ الشخصي. لإضافة الأبناء ونسبهم لوالدهم، يرجى أولاً توثيق سجل الزواج من تبويب "سجلات الزواج" بالأسفل.
                    </Text>
                  </View>
                </View>
              ) : (
                <>
                  <Text style={[styles.inputLabel, { color: colors.icon }]}>
                    {person.gender === 'male' ? 'اختر الأم من الزوجات المسجلات' : 'اختر الأب من الأزواج المسجلين'}
                  </Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalChipsRow} keyboardShouldPersistTaps="handled">
                    {(person.gender === 'male' ? (data.wife_candidates || []) : (data.husband_candidates || [])).map((candidate: MyPersonBrief) => {
                      const isSelected = childForm.parentChoiceId === candidate.id;
                      const statusBadge = candidate.status_text ? ` (${candidate.status_text})` : '';
                      return (
                        <Pressable
                          key={candidate.id}
                          onPress={() => setChildForm((f) => ({ ...f, parentChoiceId: candidate.id }))}
                          style={[
                            styles.chipPill,
                            {
                              backgroundColor: isSelected ? colors.primary : isDark ? '#111827' : '#F1F5F9',
                              borderColor: isSelected ? colors.primary : isDark ? '#334155' : '#E2E8F0',
                            },
                          ]}>
                          <Ionicons name="heart-outline" size={15} color={isSelected ? '#FFF' : colors.primary} />
                          <Text style={[styles.chipPillText, { color: isSelected ? '#FFF' : colors.text }]}>
                            {candidate.full_name}{statusBadge}
                          </Text>
                        </Pressable>
                      );
                    })}
                  </ScrollView>
                </>
              )}

              <TouchableOpacity
                disabled={saving || (person.gender === 'female' && (!data.husband_candidates || data.husband_candidates.length === 0))}
                style={[
                  styles.primaryBtn,
                  {
                    backgroundColor: (person.gender === 'female' && (!data.husband_candidates || data.husband_candidates.length === 0))
                      ? (isDark ? '#334155' : '#CBD5E1')
                      : colors.primary,
                    marginTop: 12,
                  },
                ]}
                onPress={() => void handleAddChild()}>
                <Ionicons name="add-circle-outline" size={20} color="#FFF" />
                <Text style={styles.primaryBtnText}>إضافة الابن / الابنة</Text>
              </TouchableOpacity>
            </View>
  );
}
