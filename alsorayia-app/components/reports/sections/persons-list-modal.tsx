import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, Modal, Pressable, ScrollView, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import type { EdgeInsets } from 'react-native-safe-area-context';
import type { ReportsPersonRow } from '@/lib/api/reports';
import { MODAL_PAGE, formatAge, formatLocationDisplay, formatNumber, type ListModalState } from '../helpers';
import { styles } from '../styles';
import { ShowMoreBtn } from './show-more-btn';

interface PersonListModalProps {
  listModal: ListModalState;
  modalListLimit: number;
  setModalListLimit: Dispatch<SetStateAction<number>>;
  closeListModal: () => void;
  openPersonStats: (person: ReportsPersonRow | { id: number; full_name: string }) => void;
  insets: EdgeInsets;
  isDark: boolean;
  mutedColor: string;
}

export function PersonListModal({
  listModal,
  modalListLimit,
  setModalListLimit,
  closeListModal,
  openPersonStats,
  insets,
  isDark,
  mutedColor,
}: PersonListModalProps) {
  return (
      <Modal
        visible={listModal.visible}
        animationType="slide"
        transparent
        onRequestClose={closeListModal}>
        <Pressable style={styles.modalOverlay} onPress={closeListModal}>
          <Pressable
            style={[
              styles.modalSheetBox,
              {
                backgroundColor: isDark ? '#141a16' : '#ffffff',
                borderColor: isDark ? '#26382a' : '#e5e7eb',
              },
            ]}
            onPress={(e) => e.stopPropagation()}>
            {/* ترويسة المودال */}
            <View style={[styles.modalHeaderRow, { borderBottomColor: isDark ? '#223025' : '#f3f4f6' }]}>
              <ThemedText style={styles.modalTitleText} numberOfLines={2}>
                {listModal.title}
              </ThemedText>
              <Pressable hitSlop={12} onPress={closeListModal} style={styles.modalCloseCircle}>
                <Ionicons name="close" size={20} color={mutedColor} />
              </Pressable>
            </View>

            {listModal.loading ? (
              <View style={styles.modalLoadingBox}>
                <ActivityIndicator size="large" color="#16a34a" />
                <ThemedText style={styles.loadingHint} lightColor={mutedColor} darkColor={mutedColor}>
                  جاري التحميل…
                </ThemedText>
              </View>
            ) : (
              <ScrollView
                // مهم: لا تستخدم flex:1 هنا — الورقة (modalSheetBox) ارتفاعها تلقائي (maxHeight فقط)،
                // و flex:1 يجعل flexBasis=0 فينهار الـ ScrollView إلى ارتفاع 0 ولا يظهر أي محتوى.
                style={{ flexShrink: 1 }}
                contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 24 }}>
                {/* شريط الإحصائيات الثلاثي مثل الـ Blade */}
                <View style={styles.modalStatsRow}>
                  <View style={[styles.modalStatItem, { backgroundColor: isDark ? '#0b2615' : '#f0fdf4' }]}>
                    <ThemedText style={[styles.modalStatNum, { color: isDark ? '#4ade80' : '#15803d' }]}>
                      {formatNumber(listModal.total)}
                    </ThemedText>
                    <ThemedText style={[styles.modalStatLbl, { color: isDark ? '#86efac' : '#166534' }]}>
                      الإجمالي
                    </ThemedText>
                  </View>

                  <View style={[styles.modalStatItem, { backgroundColor: isDark ? '#172554' : '#eff6ff' }]}>
                    <ThemedText style={[styles.modalStatNum, { color: isDark ? '#93c5fd' : '#2563eb' }]}>
                      {formatNumber(listModal.males)}
                    </ThemedText>
                    <ThemedText style={[styles.modalStatLbl, { color: isDark ? '#bfdbfe' : '#1d4ed8' }]}>
                      ذكور
                    </ThemedText>
                  </View>

                  <View style={[styles.modalStatItem, { backgroundColor: isDark ? '#3b1d28' : '#fdf2f8' }]}>
                    <ThemedText style={[styles.modalStatNum, { color: isDark ? '#f472b6' : '#db2777' }]}>
                      {formatNumber(listModal.females)}
                    </ThemedText>
                    <ThemedText style={[styles.modalStatLbl, { color: isDark ? '#fbcfe8' : '#be185d' }]}>
                      إناث
                    </ThemedText>
                  </View>
                </View>

                {/* قائمة الأشخاص */}
                {listModal.error ? (
                  <View style={styles.modalErrorBox}>
                    <Ionicons name="alert-circle" size={30} color="#f59e0b" />
                    <ThemedText style={styles.modalErrorText} lightColor={mutedColor} darkColor={mutedColor}>
                      تعذّر تحميل البيانات، حاول تحديث الصفحة أو تحقق من الاتصال بالإنترنت.
                    </ThemedText>
                  </View>
                ) : listModal.persons.length === 0 ? (
                  <ThemedText style={styles.emptyHint} lightColor={mutedColor} darkColor={mutedColor}>
                    لا يوجد أشخاص مسجلين.
                  </ThemedText>
                ) : (
                  <View style={{ gap: 8 }}>
                    {listModal.persons.slice(0, modalListLimit).map((p, pIdx) => {
                      const isMale = p.gender === 'male';
                      const locStr = formatLocationDisplay(p.location);

                      return (
                        <Pressable
                          key={`list-p-${p.id}-${pIdx}`}
                          onPress={() => openPersonStats(p)}
                          style={({ pressed }) => [
                            styles.modalPersonItem,
                            {
                              backgroundColor: isDark ? '#1a221c' : '#f9fafb',
                              borderColor: isDark ? '#263329' : '#e5e7eb',
                              opacity: pressed ? 0.88 : 1,
                            },
                          ]}>
                          <View style={{ flex: 1 }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                              <ThemedText
                                style={{
                                  color: isMale ? '#2563eb' : '#db2777',
                                  fontFamily: 'Tajawal-Bold',
                                  fontSize: 14,
                                }}>
                                {isMale ? '♂' : '♀'}
                              </ThemedText>
                              <ThemedText style={styles.modalPersonName} numberOfLines={1}>
                                {p.full_name}
                              </ThemedText>
                            </View>

                            {/* الموقع إن وجد */}
                            {locStr ? (
                              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 4 }}>
                                <Ionicons name="location-outline" size={13} color={mutedColor} />
                                <ThemedText style={styles.modalPersonLocation} lightColor={mutedColor} darkColor={mutedColor}>
                                  {locStr}
                                </ThemedText>
                              </View>
                            ) : null}
                          </View>

                          {/* العمر وتاريخ الميلاد للذكور فقط */}
                          {isMale && (p.age != null || p.birth_date) ? (
                            <View style={{ alignItems: 'flex-end' }}>
                              {p.age != null ? (
                                <ThemedText style={[styles.personItemAge, { color: isDark ? '#4ade80' : '#16a34a' }]}>
                                  {formatAge(p.age)} سنة
                                </ThemedText>
                              ) : null}
                              {p.birth_date ? (
                                <ThemedText style={styles.personItemBirth} lightColor={mutedColor} darkColor={mutedColor}>
                                  {p.birth_date}
                                </ThemedText>
                              ) : null}
                            </View>
                          ) : null}
                        </Pressable>
                      );
                    })}

                    <ShowMoreBtn
                      visibleCount={Math.min(modalListLimit, listModal.persons.length)}
                      total={listModal.persons.length}
                      onMore={() => setModalListLimit((n) => n + MODAL_PAGE)}
                      isDark={isDark}
                    />
                  </View>
                )}
              </ScrollView>
            )}
          </Pressable>
        </Pressable>
      </Modal>
  );
}
