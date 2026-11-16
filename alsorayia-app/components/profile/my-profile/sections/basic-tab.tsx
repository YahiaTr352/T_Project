import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, Pressable, StyleProp, Text, TouchableOpacity, View, ViewStyle } from 'react-native';
import type { MyPerson } from '@/lib/api/myProfile';
import { DatePickerField } from '@/components/ui/DatePickerField';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import type { BasicFormState } from '../helpers';
import { SectionHeader, ModernInput } from './ui';
import { styles } from '../styles';

interface BasicTabProps {
  person: MyPerson;
  basicForm: BasicFormState;
  setBasicForm: Dispatch<SetStateAction<BasicFormState>>;
  showDeathFields: boolean;
  setShowDeathFields: Dispatch<SetStateAction<boolean>>;
  pickPhoto: () => unknown;
  handleSaveBasic: () => unknown;
  saving: boolean;
  photoUri: string | null;
  cardStyle: StyleProp<ViewStyle>;
}

export function BasicTab({
  person,
  basicForm,
  setBasicForm,
  showDeathFields,
  setShowDeathFields,
  pickPhoto,
  handleSaveBasic,
  saving,
  photoUri,
  cardStyle,
}: BasicTabProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
            <View style={cardStyle}>
              <SectionHeader
                icon="create-outline"
                title="البيانات الأساسية"
                subtitle="قم بتحديث مهنتك ونبذتك وبياناتك الشخصية"
              />

              {/* Locked Name Card (Official Lineage in Tree) */}
              <View
                style={[
                  styles.lockedNameCard,
                  {
                    backgroundColor: isDark ? '#1E293B' : '#F8FAFC',
                    borderColor: isDark ? '#334155' : '#E2E8F0',
                  },
                ]}>
                <View style={styles.lockedNameHeader}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <Ionicons name="lock-closed" size={15} color={colors.primary} />
                    <Text style={[styles.lockedNameTitle, { color: colors.text }]}>الاسم الكامل (موثق رسمي)</Text>
                  </View>
                  <View style={[styles.lockedPill, { backgroundColor: isDark ? colors.primary + '25' : colors.primary + '18' }]}>
                    <Text style={[styles.lockedPillText, { color: colors.primary }]}>غير قابل للتعديل</Text>
                  </View>
                </View>
                <Text style={[styles.lockedNameValue, { color: colors.text }]}>{person.full_name}</Text>
                <View style={[styles.lockedNameFooter, { borderTopColor: isDark ? '#334155' : '#E2E8F0' }]}>
                  <Ionicons name="information-circle-outline" size={15} color={isDark ? '#94A3B8' : '#64748B'} />
                  <Text style={[styles.lockedNameNotice, { color: isDark ? '#94A3B8' : '#64748B' }]}>
                    الاسم موثق رسمياً في شجرة العائلة ولا يمكن تعديله من التطبيق. لتعديل الاسم يرجى التواصل مع إدارة العائلة.
                  </Text>
                </View>
              </View>
              <DatePickerField
                label="تاريخ الميلاد"
                value={basicForm.birth_date}
                onChange={(v) => setBasicForm((f) => ({ ...f, birth_date: v }))}
                maximumDate={new Date()}
              />
              <ModernInput
                label="مكان الميلاد"
                icon="location-outline"
                value={basicForm.birth_place}
                onChangeText={(v) => setBasicForm((f) => ({ ...f, birth_place: v }))}
              />
              <ModernInput
                label="المهنة / التخصص"
                icon="briefcase-outline"
                value={basicForm.occupation}
                onChangeText={(v) => setBasicForm((f) => ({ ...f, occupation: v }))}
              />

              <View style={styles.photoSelectContainer}>
                <Text style={[styles.inputLabel, { color: colors.icon }]}>الصورة الشخصية</Text>
                <TouchableOpacity
                  style={[styles.photoSelectBtn, { borderColor: isDark ? '#334155' : colors.border, backgroundColor: isDark ? '#111827' : '#F8FAFC' }]}
                  onPress={() => void pickPhoto()}>
                  <Ionicons name="image-outline" size={20} color={colors.primary} />
                  <Text style={[styles.photoSelectText, { color: colors.text }]}>
                    {photoUri ? 'تم اختيار صورة جديدة 📸' : 'تغيير الصورة من المعرض'}
                  </Text>
                </TouchableOpacity>
              </View>

              <ModernInput
                label="نبذة سيرة ذاتية"
                icon="document-text-outline"
                multiline
                numberOfLines={4}
                value={basicForm.biography}
                onChangeText={(v) => setBasicForm((f) => ({ ...f, biography: v }))}
              />

              <Pressable onPress={() => setShowDeathFields((v) => !v)} style={styles.deathSectionToggle}>
                <Ionicons name={showDeathFields ? 'chevron-up' : 'chevron-down'} size={18} color={colors.primary} />
                <Text style={[styles.deathSectionToggleText, { color: colors.primary }]}>
                  {showDeathFields ? 'إخفاء بيانات الوفاة' : 'بيانات الوفاة (اختياري)'}
                </Text>
              </Pressable>

              {showDeathFields && (
                <View style={styles.deathFieldsWrap}>
                  <DatePickerField
                    label="تاريخ الوفاة"
                    value={basicForm.death_date}
                    onChange={(v) => setBasicForm((f) => ({ ...f, death_date: v }))}
                    maximumDate={new Date()}
                  />
                  <ModernInput
                    label="مكان الوفاة"
                    icon="location-outline"
                    value={basicForm.death_place}
                    onChangeText={(v) => setBasicForm((f) => ({ ...f, death_place: v }))}
                  />
                  <ModernInput
                    label="المقبرة"
                    icon="flower-outline"
                    value={basicForm.cemetery}
                    onChangeText={(v) => setBasicForm((f) => ({ ...f, cemetery: v }))}
                  />
                </View>
              )}

              <TouchableOpacity
                disabled={saving}
                style={[styles.primaryBtn, { backgroundColor: colors.primary, marginTop: 20 }]}
                onPress={() => void handleSaveBasic()}>
                {saving ? (
                  <ActivityIndicator color="#FFF" />
                ) : (
                  <>
                    <Ionicons name="checkmark-done" size={20} color="#FFF" />
                    <Text style={styles.primaryBtnText}>حفظ التغييرات</Text>
                  </>
                )}
              </TouchableOpacity>
            </View>
  );
}
