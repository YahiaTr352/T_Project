import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { Pressable, ScrollView, StyleProp, Switch, Text, TouchableOpacity, View, ViewStyle } from 'react-native';
import type { MyProfilePayload, UpdateMarriageInput } from '@/lib/api/myProfile';
import { CustomAlert } from '@/components/ui/AlertModal';
import { DatePickerField } from '@/components/ui/DatePickerField';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import type { MarriageEditsMap, MarriageFormState } from '../helpers';
import { SectionHeader, ModernInput } from './ui';
import { styles } from '../styles';

interface MarriagesTabProps {
  data: MyProfilePayload;
  marriageForm: MarriageFormState;
  setMarriageForm: Dispatch<SetStateAction<MarriageFormState>>;
  marriageEdits: MarriageEditsMap;
  setMarriageEdits: Dispatch<SetStateAction<MarriageEditsMap>>;
  patchMarriage: (id: number, patch: UpdateMarriageInput) => Promise<{ ok: boolean; message: string }>;
  handleAddMarriage: () => unknown;
  saving: boolean;
  cardStyle: StyleProp<ViewStyle>;
}

export function MarriagesTab({
  data,
  marriageForm,
  setMarriageForm,
  marriageEdits,
  setMarriageEdits,
  patchMarriage,
  handleAddMarriage,
  saving,
  cardStyle,
}: MarriagesTabProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const isWoman = data.person?.gender === 'female';

  return (
            <View style={cardStyle}>
              <SectionHeader
                icon="heart-outline"
                title="سجلات الزواج"
                subtitle="توثيق سجلات الزواج والانفصال في العائلة"
              />

              <View style={{ gap: 12, marginVertical: 12 }}>
                {data.marriages.map((m) => {
                  const edit = marriageEdits[m.id] ?? { married_at: '', divorced_at: '', is_divorced: false };
                  return (
                    <View key={m.id} style={[styles.marriageCardItem, { backgroundColor: isDark ? '#111827' : '#F8FAFC', borderColor: isDark ? '#334155' : '#E2E8F0' }]}>
                      <View style={styles.marriageCardTopRow}>
                        <View style={[styles.marriageHeartIcon, { backgroundColor: m.is_divorced ? '#EF444415' : '#EC489915' }]}>
                          <Ionicons name="heart" size={20} color={m.is_divorced ? '#EF4444' : '#EC4899'} />
                        </View>
                        <View style={{ flex: 1 }}>
                          <Text style={[styles.spouseNameText, { color: colors.text }]}>{m.spouse?.full_name ?? '—'}</Text>
                          <Text style={[styles.marriageStatusPill, { color: m.is_divorced ? '#EF4444' : '#059669' }]}>
                            {m.status}
                          </Text>
                        </View>
                      </View>

                      <View style={styles.marriageFieldsGrid}>
                        <DatePickerField
                          label="تاريخ الزواج"
                          value={edit.married_at}
                          onChange={(v) => setMarriageEdits((e) => ({ ...e, [m.id]: { ...edit, married_at: v } }))}
                          maximumDate={new Date()}
                        />
                        <DatePickerField
                          label="تاريخ الطلاق (إن وجد)"
                          icon="calendar-clear-outline"
                          value={edit.divorced_at}
                          onChange={(v) => setMarriageEdits((e) => ({ ...e, [m.id]: { ...edit, divorced_at: v } }))}
                          maximumDate={new Date()}
                        />
                      </View>

                      <View style={styles.divorceSwitchRow}>
                        <Text style={[styles.switchLabelText, { color: colors.text }]}>حالة انفصال</Text>
                        <Switch
                          value={edit.is_divorced}
                          onValueChange={(v) => setMarriageEdits((e) => ({ ...e, [m.id]: { ...edit, is_divorced: v } }))}
                          trackColor={{ false: '#CBD5E1', true: colors.primary }}
                        />
                      </View>

                      <TouchableOpacity
                        disabled={saving}
                        style={[styles.updateMarriageBtn, { borderColor: colors.primary }]}
                        onPress={() =>
                          void patchMarriage(m.id, {
                            married_at: edit.married_at || null,
                            divorced_at: edit.divorced_at || null,
                            is_divorced: edit.is_divorced,
                          }).then((r) => CustomAlert.alert(r.ok ? 'نجاح' : 'خطأ', r.message))
                        }>
                        <Ionicons name="sync-outline" size={16} color={colors.primary} />
                        <Text style={[styles.updateMarriageBtnText, { color: colors.primary }]}>تحديث سجل الزواج</Text>
                      </TouchableOpacity>
                    </View>
                  );
                })}
              </View>

              <Text style={[styles.subFormTitle, { color: colors.text, marginTop: 16 }]}>إضافة زواج جديد</Text>

              {/* تبويبات نوع الزوجة */}
              <View style={{ flexDirection: 'row', gap: 6, marginVertical: 10 }}>
                {[
                  { key: 'existing', label: 'من الشجرة', icon: 'search-outline' },
                  { key: 'outside_family', label: 'خارج العائلة', icon: 'globe-outline' },
                  { key: 'inside_family', label: isWoman ? 'من العائلة (جديد)' : 'من العائلة (جديدة)', icon: 'person-add-outline' },
                ].map((tab) => {
                  const isSelected = marriageForm.spouseType === tab.key;
                  return (
                    <Pressable
                      key={tab.key}
                      onPress={() => setMarriageForm((f) => ({ ...f, spouseType: tab.key as any }))}
                      style={{
                        flex: 1,
                        paddingVertical: 9,
                        borderRadius: 10,
                        backgroundColor: isSelected ? colors.primary : isDark ? '#1F2937' : '#F1F5F9',
                        alignItems: 'center',
                        justifyContent: 'center',
                        borderWidth: 1,
                        borderColor: isSelected ? colors.primary : isDark ? '#374151' : '#E2E8F0',
                      }}>
                      <Ionicons name={tab.icon as any} size={15} color={isSelected ? '#FFF' : colors.text} />
                      <Text style={{ fontSize: 11, fontFamily: 'Tajawal-Bold', lineHeight: 17, marginTop: 3, color: isSelected ? '#FFF' : colors.text }}>
                        {tab.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>

              {/* الحالة 1: بحث واختيار من شجرة العائلة */}
              {marriageForm.spouseType === 'existing' && (
                <View style={{ gap: 8, marginVertical: 4 }}>
                  <ModernInput
                    label={isWoman ? 'ابحث بالاسم الكامل للزوج' : 'ابحث بالاسم الكامل للزوجة'}
                    icon="search-outline"
                    placeholder={isWoman ? 'اكتب اسم الزوج أو والده...' : 'اكتب اسم الزوجة أو والدها...'}
                    value={marriageForm.searchQuery}
                    onChangeText={(v) => setMarriageForm((f) => ({ ...f, searchQuery: v }))}
                  />

                  {/* عرض النتائج المطابقة للبحث فقط بالاسم الكامل */}
                  {marriageForm.searchQuery.trim() ? (
                    <View style={{ gap: 6, maxHeight: 180 }}>
                      <ScrollView nestedScrollEnabled style={{ maxHeight: 180 }}>
                        {data.spouse_candidates
                          .filter((c) => c.full_name?.toLowerCase().includes(marriageForm.searchQuery.trim().toLowerCase()))
                          .map((c) => {
                            const isSelected = marriageForm.spouseId === c.id;
                            return (
                              <Pressable
                                key={c.id}
                                onPress={() => setMarriageForm((f) => ({ ...f, spouseId: c.id }))}
                                style={{
                                  padding: 10,
                                  borderRadius: 10,
                                  marginBottom: 6,
                                  flexDirection: 'row',
                                  alignItems: 'center',
                                  justifyContent: 'space-between',
                                  backgroundColor: isSelected ? colors.primary + '15' : isDark ? '#1F2937' : '#F8FAFC',
                                  borderWidth: 1.5,
                                  borderColor: isSelected ? colors.primary : isDark ? '#374151' : '#E2E8F0',
                                }}>
                                <View style={{ flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                                  <Ionicons name="heart" size={16} color={isSelected ? colors.primary : '#EC4899'} />
                                  <Text style={{ fontSize: 13, fontFamily: 'Tajawal-Bold', lineHeight: 21, color: colors.text }}>
                                    {c.full_name}
                                  </Text>
                                </View>
                                {isSelected && <Ionicons name="checkmark-circle" size={18} color={colors.primary} />}
                              </Pressable>
                            );
                          })}
                      </ScrollView>
                    </View>
                  ) : (
                    <Text style={{ fontSize: 12, color: colors.icon, textAlign: 'center', marginVertical: 6 }}>
                      🔍 {isWoman ? 'اكتب في خانة البحث بالأعلى لعرض أسماء الأزواج بالكامل' : 'اكتب في خانة البحث بالأعلى لعرض أسماء الزوجات بالكامل'}
                    </Text>
                  )}
                </View>
              )}

              {/* الحالة 2: زوجة من خارج العائلة */}
              {marriageForm.spouseType === 'outside_family' && (
                <View style={{ gap: 10 }}>
                  <ModernInput
                    label={isWoman ? 'اسم الزوج الأول *' : 'اسم الزوجة الأول *'}
                    icon="person-outline"
                    placeholder={isWoman ? 'مثال: محمد' : 'مثال: نورة'}
                    value={marriageForm.firstName}
                    onChangeText={(v) => setMarriageForm((f) => ({ ...f, firstName: v }))}
                  />
                  <ModernInput
                    label="اسم العائلة / القبيلة"
                    icon="business-outline"
                    placeholder="مثال: آل فلان أو الغامدي"
                    value={marriageForm.lastName}
                    onChangeText={(v) => setMarriageForm((f) => ({ ...f, lastName: v }))}
                  />
                  <DatePickerField
                    label="تاريخ الميلاد (اختياري)"
                    value={marriageForm.birthDate}
                    onChange={(v) => setMarriageForm((f) => ({ ...f, birthDate: v }))}
                    maximumDate={new Date()}
                  />
                </View>
              )}

              {/* الحالة 3: زوجة من العائلة ولكن غير مسجلة بالشجرة */}
              {marriageForm.spouseType === 'inside_family' && (
                <View style={{ gap: 10 }}>
                  <ModernInput
                    label={isWoman ? 'اسم الزوج الأول *' : 'اسم الزوجة الأول *'}
                    icon="person-outline"
                    placeholder={isWoman ? 'مثال: أحمد' : 'مثال: مريم'}
                    value={marriageForm.firstName}
                    onChangeText={(v) => setMarriageForm((f) => ({ ...f, firstName: v }))}
                  />
                  <Text style={[styles.inputLabel, { color: colors.icon }]}>
                    {isWoman ? 'اختر والده من رجال العائلة لتوثيق نسبه *' : 'اختر والدها من رجال العائلة لتوثيق نسبها *'}
                  </Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalChipsRow}>
                    {(data.father_candidates || []).map((f) => {
                      const isSelected = marriageForm.parentId === f.id;
                      return (
                        <Pressable
                          key={f.id}
                          onPress={() => setMarriageForm((prev) => ({ ...prev, parentId: f.id }))}
                          style={[
                            styles.chipPill,
                            {
                              backgroundColor: isSelected ? colors.primary : isDark ? '#111827' : '#F1F5F9',
                              borderColor: isSelected ? colors.primary : isDark ? '#334155' : '#E2E8F0',
                            },
                          ]}>
                          <Ionicons name="person" size={14} color={isSelected ? '#FFF' : colors.primary} />
                          <Text style={[styles.chipPillText, { color: isSelected ? '#FFF' : colors.text }]}>
                            {f.full_name}
                          </Text>
                        </Pressable>
                      );
                    })}
                  </ScrollView>
                  <DatePickerField
                    label="تاريخ الميلاد (اختياري)"
                    value={marriageForm.birthDate}
                    onChange={(v) => setMarriageForm((f) => ({ ...f, birthDate: v }))}
                    maximumDate={new Date()}
                  />
                </View>
              )}

              {/* تاريخ الزواج المشترك */}
              <DatePickerField
                label="تاريخ الزواج"
                value={marriageForm.married_at}
                onChange={(v) => setMarriageForm((f) => ({ ...f, married_at: v }))}
                maximumDate={new Date()}
              />

              <TouchableOpacity
                disabled={saving}
                style={[styles.primaryBtn, { backgroundColor: colors.primary, marginTop: 12 }]}
                onPress={() => void handleAddMarriage()}>
                <Ionicons name="add-circle-outline" size={20} color="#FFF" />
                <Text style={styles.primaryBtnText}>إرسال طلب الزواج</Text>
              </TouchableOpacity>
            </View>
  );
}
