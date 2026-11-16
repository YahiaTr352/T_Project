import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { Linking, Pressable, ScrollView, StyleProp, Text, TouchableOpacity, View, ViewStyle } from 'react-native';
import type { MyProfilePayload } from '@/lib/api/myProfile';
import { CustomAlert } from '@/components/ui/AlertModal';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import type { LocationFormState } from '../helpers';
import { SectionHeader, ModernInput } from './ui';
import { styles } from '../styles';

interface LocationsTabProps {
  data: MyProfilePayload;
  locationForm: LocationFormState;
  setLocationForm: Dispatch<SetStateAction<LocationFormState>>;
  handleAddLocation: () => unknown;
  removeLocation: (id: number) => Promise<{ ok: boolean; message: string }>;
  saving: boolean;
  cardStyle: StyleProp<ViewStyle>;
}

export function LocationsTab({
  data,
  locationForm,
  setLocationForm,
  handleAddLocation,
  removeLocation,
  saving,
  cardStyle,
}: LocationsTabProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
            <View style={cardStyle}>
              <SectionHeader
                icon="location-outline"
                title="أماكن الإقامة والسكن"
                subtitle="أضف مدينتك أو مقر سكنك الحالي والسابقة"
              />

              <View style={{ gap: 10, marginVertical: 12 }}>
                {data.locations.map((loc) => (
                  <View key={loc.id} style={[styles.locationCardItem, { backgroundColor: isDark ? '#111827' : '#F8FAFC', borderColor: isDark ? '#334155' : '#E2E8F0' }]}>
                    <View style={[styles.locationPinIconWrap, { backgroundColor: colors.primary + '18' }]}>
                      <Ionicons name="map" size={20} color={colors.primary} />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.locationNameText, { color: colors.text }]}>{loc.location_name}</Text>
                      {loc.label ? <Text style={[styles.locationLabelText, { color: colors.icon }]}>{loc.label}</Text> : null}
                    </View>
                    {loc.url ? (
                      <TouchableOpacity onPress={() => Linking.openURL(loc.url!)} style={styles.mapLinkBtn}>
                        <Ionicons name="navigate-circle" size={24} color={colors.primary} />
                      </TouchableOpacity>
                    ) : null}
                    <TouchableOpacity
                      onPress={() =>
                        CustomAlert.alert('حذف المكان', 'هل أنت تأكد من حذف هذا الموقع؟', [
                          { text: 'إلغاء', style: 'cancel' },
                          {
                            text: 'حذف',
                            style: 'destructive',
                            onPress: () => void removeLocation(loc.id).then((r) => CustomAlert.alert(r.ok ? 'تم' : 'خطأ', r.message)),
                          },
                        ])
                      }
                      style={styles.deleteCircleBtn}>
                      <Ionicons name="trash-outline" size={18} color="#EF4444" />
                    </TouchableOpacity>
                  </View>
                ))}
              </View>

              <Text style={[styles.subFormTitle, { color: colors.text }]}>إضافة موقع سكن جديد</Text>

              <Text style={[styles.inputLabel, { color: colors.icon }]}>اختر المدينة / المكان</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalChipsRow} keyboardShouldPersistTaps="handled">
                {data.locations_catalog.map((l) => {
                  const isSelected = locationForm.location_id === l.id;
                  return (
                    <Pressable
                      key={l.id}
                      onPress={() => setLocationForm((f) => ({ ...f, location_id: l.id }))}
                      style={[
                        styles.chipPill,
                        {
                          backgroundColor: isSelected ? colors.primary : isDark ? '#111827' : '#F1F5F9',
                          borderColor: isSelected ? colors.primary : isDark ? '#334155' : '#E2E8F0',
                        },
                      ]}>
                      <Ionicons name="navigate" size={14} color={isSelected ? '#FFF' : colors.primary} />
                      <Text style={[styles.chipPillText, { color: isSelected ? '#FFF' : colors.text }]}>{l.name}</Text>
                    </Pressable>
                  );
                })}
              </ScrollView>

              <ModernInput
                label="تسمية المكان"
                icon="bookmark-outline"
                placeholder="مثال: المنزل / العمل / المزرعة"
                value={locationForm.label}
                onChangeText={(v) => setLocationForm((f) => ({ ...f, label: v }))}
              />
              <ModernInput
                label="رابط خرائط جوجل (اختياري)"
                icon="compass-outline"
                placeholder="https://maps.google.com/..."
                value={locationForm.url}
                onChangeText={(v) => setLocationForm((f) => ({ ...f, url: v }))}
                keyboardType="url"
              />

              <TouchableOpacity
                disabled={saving}
                style={[styles.primaryBtn, { backgroundColor: colors.primary, marginTop: 12 }]}
                onPress={() => void handleAddLocation()}>
                <Ionicons name="add-circle-outline" size={20} color="#FFF" />
                <Text style={styles.primaryBtnText}>إضافة موقع الإقامة</Text>
              </TouchableOpacity>
            </View>
  );
}
