import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, Modal, Pressable, ScrollView, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import type { EdgeInsets } from 'react-native-safe-area-context';
import type { ReportsGenerationTier, ReportsPersonRow } from '@/lib/api/reports';
import { GENERATION_LABELS, formatNumber, sortGenerationKeys, type StatsModalState } from '../helpers';
import { styles } from '../styles';

interface PersonStatsModalProps {
  statsModal: StatsModalState;
  modalGenToggles: Record<string, 'members' | 'relatives' | null>;
  toggleModalGenSection: (genKey: string, section: 'members' | 'relatives') => void;
  closeStatsModal: () => void;
  openPersonStats: (person: ReportsPersonRow | { id: number; full_name: string }) => void;
  insets: EdgeInsets;
  isDark: boolean;
  mutedColor: string;
}

export function PersonStatsModal({
  statsModal,
  modalGenToggles,
  toggleModalGenSection,
  closeStatsModal,
  openPersonStats,
  insets,
  isDark,
  mutedColor,
}: PersonStatsModalProps) {
  return (
      <Modal
        visible={statsModal.visible}
        animationType="fade"
        transparent
        onRequestClose={closeStatsModal}>
        <Pressable style={styles.modalOverlay} onPress={closeStatsModal}>
          <Pressable
            style={[
              styles.modalSheetBox,
              {
                backgroundColor: isDark ? '#141a16' : '#ffffff',
                borderColor: isDark ? '#26382a' : '#e5e7eb',
              },
            ]}
            onPress={(e) => e.stopPropagation()}>
            <View style={[styles.modalHeaderRow, { borderBottomColor: isDark ? '#223025' : '#f3f4f6' }]}>
              <ThemedText style={styles.modalTitleText} numberOfLines={2}>
                {statsModal.personName}
              </ThemedText>
              <Pressable hitSlop={12} onPress={closeStatsModal} style={styles.modalCloseCircle}>
                <Ionicons name="close" size={20} color={mutedColor} />
              </Pressable>
            </View>

            {statsModal.loading ? (
              <View style={styles.modalLoadingBox}>
                <ActivityIndicator size="large" color="#16a34a" />
                <ThemedText style={styles.loadingHint} lightColor={mutedColor} darkColor={mutedColor}>
                  جاري جلب تفاصيل الأجيال…
                </ThemedText>
              </View>
            ) : (
              <ScrollView
                // مهم: لا تستخدم flex:1 هنا — الورقة (modalSheetBox) ارتفاعها تلقائي (maxHeight فقط)،
                // و flex:1 يجعل flexBasis=0 فينهار الـ ScrollView إلى ارتفاع 0 ولا يظهر أي محتوى.
                style={{ flexShrink: 1 }}
                contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 24 }}>
                {/* شريط الإحصائيات الثلاثي */}
                <View style={styles.modalStatsRow}>
                  <View style={[styles.modalStatItem, { backgroundColor: isDark ? '#0b2615' : '#f0fdf4' }]}>
                    <ThemedText style={[styles.modalStatNum, { color: isDark ? '#4ade80' : '#15803d' }]}>
                      {formatNumber(statsModal.totalDescendants)}
                    </ThemedText>
                    <ThemedText style={[styles.modalStatLbl, { color: isDark ? '#86efac' : '#166534' }]}>
                      إجمالي الأحفاد
                    </ThemedText>
                  </View>

                  <View style={[styles.modalStatItem, { backgroundColor: isDark ? '#172554' : '#eff6ff' }]}>
                    <ThemedText style={[styles.modalStatNum, { color: isDark ? '#93c5fd' : '#2563eb' }]}>
                      {formatNumber(statsModal.maleDescendants)}
                    </ThemedText>
                    <ThemedText style={[styles.modalStatLbl, { color: isDark ? '#bfdbfe' : '#1d4ed8' }]}>
                      ذكور
                    </ThemedText>
                  </View>

                  <View style={[styles.modalStatItem, { backgroundColor: isDark ? '#3b1d28' : '#fdf2f8' }]}>
                    <ThemedText style={[styles.modalStatNum, { color: isDark ? '#f472b6' : '#db2777' }]}>
                      {formatNumber(statsModal.femaleDescendants)}
                    </ThemedText>
                    <ThemedText style={[styles.modalStatLbl, { color: isDark ? '#fbcfe8' : '#be185d' }]}>
                      إناث
                    </ThemedText>
                  </View>
                </View>

                {/* كروت تفاصيل الأجيال */}
                {statsModal.breakdown && Object.keys(statsModal.breakdown).length ? (
                  <View style={{ gap: 10 }}>
                    {sortGenerationKeys(statsModal.breakdown).map((genKey) => {
                      const tier = statsModal.breakdown![genKey] as ReportsGenerationTier;
                      const activeSub = modalGenToggles[genKey];

                      return (
                        <View
                          key={`modal-tier-${genKey}`}
                          style={[
                            styles.genTierCard,
                            {
                              backgroundColor: isDark ? '#1a221c' : '#f9fafb',
                              borderColor: isDark ? '#263329' : '#e5e7eb',
                            },
                          ]}>
                          <View style={styles.genTierTop}>
                            <ThemedText style={styles.genTierLabel}>
                              {GENERATION_LABELS[genKey] ?? `الجيل ${genKey}`}
                            </ThemedText>
                            <ThemedText style={[styles.genTierCount, { color: isDark ? '#4ade80' : '#16a34a' }]}>
                              {formatNumber(tier.total)}
                            </ThemedText>
                          </View>

                          <View style={styles.genTierMetaRow}>
                            <ThemedText style={{ color: '#2563eb', fontSize: 12, fontFamily: 'Tajawal-Medium' }}>
                              ♂ {formatNumber(tier.males)} ذكور
                            </ThemedText>
                            <ThemedText style={{ color: '#db2777', fontSize: 12, fontFamily: 'Tajawal-Medium' }}>
                              ♀ {formatNumber(tier.females)} إناث
                            </ThemedText>
                            {tier.relatives_count && tier.relatives_count > 0 ? (
                              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 3 }}>
                                <Ionicons name="heart" size={12} color="#9333ea" />
                                <ThemedText style={{ color: '#9333ea', fontSize: 12, fontFamily: 'Tajawal-Bold' }}>
                                  الأنساب: {formatNumber(tier.relatives_count)}
                                </ThemedText>
                              </View>
                            ) : null}
                          </View>

                          {/* أزرار العرض */}
                          <View style={styles.genActionBtnsRow}>
                            {tier.members && tier.members.length > 0 ? (
                              <Pressable
                                onPress={() => toggleModalGenSection(genKey, 'members')}
                                style={[
                                  styles.subToggleBtn,
                                  {
                                    backgroundColor:
                                      activeSub === 'members'
                                        ? '#16a34a'
                                        : isDark
                                        ? '#14532d'
                                        : '#dcfce7',
                                  },
                                ]}>
                                <Ionicons
                                  name="people"
                                  size={14}
                                  color={activeSub === 'members' ? '#ffffff' : isDark ? '#86efac' : '#15803d'}
                                />
                                <ThemedText
                                  style={[
                                    styles.subToggleBtnText,
                                    {
                                      color:
                                        activeSub === 'members'
                                          ? '#ffffff'
                                          : isDark
                                          ? '#86efac'
                                          : '#15803d',
                                    },
                                  ]}>
                                  الأبناء ({tier.members.length})
                                </ThemedText>
                              </Pressable>
                            ) : null}

                            {tier.relatives && tier.relatives.length > 0 ? (
                              <Pressable
                                onPress={() => toggleModalGenSection(genKey, 'relatives')}
                                style={[
                                  styles.subToggleBtn,
                                  {
                                    backgroundColor:
                                      activeSub === 'relatives'
                                        ? '#9333ea'
                                        : isDark
                                        ? '#581c87'
                                        : '#f3e8ff',
                                  },
                                ]}>
                                <Ionicons
                                  name="heart"
                                  size={14}
                                  color={activeSub === 'relatives' ? '#ffffff' : isDark ? '#d8b4fe' : '#7e22ce'}
                                />
                                <ThemedText
                                  style={[
                                    styles.subToggleBtnText,
                                    {
                                      color:
                                        activeSub === 'relatives'
                                          ? '#ffffff'
                                          : isDark
                                          ? '#d8b4fe'
                                          : '#7e22ce',
                                    },
                                  ]}>
                                  الأنساب ({tier.relatives.length})
                                </ThemedText>
                              </Pressable>
                            ) : null}
                          </View>

                          {/* قائمة الأبناء في المودال */}
                          {activeSub === 'members' && tier.members ? (
                            <View style={[styles.subMemberList, { borderTopColor: isDark ? '#263329' : '#e5e7eb' }]}>
                              <ThemedText style={styles.subListHeader}>قائمة الأبناء:</ThemedText>
                              {tier.members.map((m) => (
                                <Pressable
                                  key={`modal-m-${m.id}`}
                                  onPress={() => openPersonStats(m)}
                                  style={({ pressed }) => [
                                    styles.miniPersonRow,
                                    {
                                      backgroundColor: pressed
                                        ? isDark
                                          ? '#1f2e22'
                                          : '#f0fdf4'
                                        : isDark
                                        ? '#1b241d'
                                        : '#ffffff',
                                    },
                                  ]}>
                                  <ThemedText style={styles.miniPersonName} numberOfLines={1}>
                                    {m.full_name}
                                  </ThemedText>
                                  <ThemedText
                                    style={{
                                      color: m.gender === 'female' ? '#db2777' : '#2563eb',
                                      fontFamily: 'Tajawal-Bold',
                                      fontSize: 13,
                                    }}>
                                    {m.gender === 'female' ? '♀' : '♂'}
                                  </ThemedText>
                                </Pressable>
                              ))}
                            </View>
                          ) : null}

                          {/* قائمة الأنساب في المودال */}
                          {activeSub === 'relatives' && tier.relatives ? (
                            <View style={[styles.subMemberList, { borderTopColor: isDark ? '#263329' : '#e5e7eb' }]}>
                              <ThemedText style={[styles.subListHeader, { color: '#9333ea' }]}>
                                قائمة الأنساب:
                              </ThemedText>
                              {tier.relatives.map((r) => (
                                <Pressable
                                  key={`modal-r-${r.id}`}
                                  onPress={() => openPersonStats(r)}
                                  style={({ pressed }) => [
                                    styles.miniPersonRow,
                                    {
                                      backgroundColor: pressed
                                        ? isDark
                                          ? '#2c1938'
                                          : '#faf5ff'
                                        : isDark
                                        ? '#22152b'
                                        : '#ffffff',
                                    },
                                  ]}>
                                  <ThemedText style={styles.miniPersonName} numberOfLines={1}>
                                    {r.full_name}
                                  </ThemedText>
                                  <ThemedText
                                    style={{
                                      color: r.gender === 'female' ? '#db2777' : '#2563eb',
                                      fontFamily: 'Tajawal-Bold',
                                      fontSize: 13,
                                    }}>
                                    {r.gender === 'female' ? '♀' : '♂'}
                                  </ThemedText>
                                </Pressable>
                              ))}
                            </View>
                          ) : null}
                        </View>
                      );
                    })}
                  </View>
                ) : (
                  <ThemedText style={styles.emptyHint} lightColor={mutedColor} darkColor={mutedColor}>
                    لا يوجد أبناء أو أحفاد مسجلين.
                  </ThemedText>
                )}
              </ScrollView>
            )}
          </Pressable>
        </Pressable>
      </Modal>
  );
}
