import type { Dispatch, SetStateAction } from 'react';
import { Pressable, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ThemedText } from '@/components/themed-text';
import type { ReportsPageData, ReportsPersonRow } from '@/lib/api/reports';
import { LIST_INITIAL, LIST_PAGE, formatLocationDisplay } from '../helpers';
import { styles } from '../styles';
import { ShowMoreBtn } from './show-more-btn';

interface MalesByAgeCardProps {
  data: ReportsPageData;
  malesLimit: number;
  setMalesLimit: Dispatch<SetStateAction<number>>;
  openPersonStats: (person: ReportsPersonRow | { id: number; full_name: string }) => void;
  isDark: boolean;
  mutedColor: string;
}

export function MalesByAgeCard({
  data,
  malesLimit,
  setMalesLimit,
  openPersonStats,
  isDark,
  mutedColor,
}: MalesByAgeCardProps) {
  return (
          <View
            style={[
              styles.sectionCard,
              {
                backgroundColor: isDark ? 'rgba(24, 31, 26, 0.88)' : 'rgba(255, 255, 255, 0.94)',
                borderColor: isDark ? 'rgba(34, 197, 94, 0.25)' : 'rgba(34, 197, 94, 0.2)',
              },
            ]}>
            <View style={styles.sectionHeaderRow}>
              <View>
                <ThemedText style={styles.sectionHeaderTitle}>ترتيب أبناء العائلة حسب العمر</ThemedText>
                <ThemedText style={styles.sectionHeaderSubtitle} lightColor={mutedColor} darkColor={mutedColor}>
                  الذكور الأحياء فقط
                </ThemedText>
              </View>
            </View>

            {(() => {
              const males = data.allFamilyMembersByAge ?? [];
              if (!males.length) {
                return <ThemedText style={styles.emptyHint} lightColor={mutedColor} darkColor={mutedColor}>لا توجد بيانات</ThemedText>;
              }
              const visibleMales = males.slice(0, malesLimit);
              return (
                <View>
                  <View style={{ gap: 8 }}>
                    {visibleMales.map((person, idx) => (
                      <Pressable
                        key={`male-${person.id}-${idx}`}
                        onPress={() => openPersonStats(person)}
                        style={({ pressed }) => [
                          styles.personItemRow,
                          {
                            backgroundColor: isDark ? '#18201a' : 'rgba(255, 255, 255, 0.7)',
                            borderColor: isDark ? '#263329' : '#e5e7eb',
                            opacity: pressed ? 0.85 : 1,
                          },
                        ]}>
                        {/* البادج الرقمي الأخضر */}
                        <LinearGradient
                          colors={['#22c55e', '#16a34a']}
                          style={styles.rankBadge}>
                          <ThemedText style={styles.rankBadgeText}>{idx + 1}</ThemedText>
                        </LinearGradient>

                        {/* الاسم مع رمز الذكورة */}
                        <View style={{ flex: 1, paddingHorizontal: 8 }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                            <ThemedText style={styles.personItemName} numberOfLines={1}>
                              {person.full_name}
                            </ThemedText>
                            <ThemedText style={{ color: '#2563eb', fontSize: 13, fontFamily: 'Tajawal-Bold' }}>♂</ThemedText>
                          </View>
                          {person.location ? (
                            <ThemedText style={styles.personItemSub} lightColor={mutedColor} darkColor={mutedColor}>
                              {formatLocationDisplay(person.location)}
                            </ThemedText>
                          ) : null}
                        </View>

                        {/* العمر وتاريخ الميلاد */}
                        <View style={{ alignItems: 'flex-end' }}>
                          {person.age != null ? (
                            <ThemedText style={[styles.personItemAge, { color: isDark ? '#4ade80' : '#16a34a' }]}>
                              {person.age} سنة
                            </ThemedText>
                          ) : null}
                          {person.birth_date ? (
                            <ThemedText style={styles.personItemBirth} lightColor={mutedColor} darkColor={mutedColor}>
                              {person.birth_date}
                            </ThemedText>
                          ) : null}
                        </View>
                      </Pressable>
                    ))}
                  </View>

                  <ShowMoreBtn
                    visibleCount={visibleMales.length}
                    total={males.length}
                    onMore={() => setMalesLimit((n) => n + LIST_PAGE)}
                    onCollapse={() => setMalesLimit(LIST_INITIAL)}
                    isDark={isDark}
                  />
                </View>
              );
            })()}
          </View>
  );
}

interface FemalesByAgeCardProps {
  data: ReportsPageData;
  femalesLimit: number;
  setFemalesLimit: Dispatch<SetStateAction<number>>;
  openPersonStats: (person: ReportsPersonRow | { id: number; full_name: string }) => void;
  isDark: boolean;
  mutedColor: string;
}

export function FemalesByAgeCard({
  data,
  femalesLimit,
  setFemalesLimit,
  openPersonStats,
  isDark,
  mutedColor,
}: FemalesByAgeCardProps) {
  return (
          <View
            style={[
              styles.sectionCard,
              {
                backgroundColor: isDark ? 'rgba(24, 31, 26, 0.88)' : 'rgba(255, 255, 255, 0.94)',
                borderColor: isDark ? 'rgba(219, 39, 119, 0.25)' : 'rgba(219, 39, 119, 0.2)',
              },
            ]}>
            <View style={styles.sectionHeaderRow}>
              <View>
                <ThemedText style={styles.sectionHeaderTitle}>ترتيب النساء حسب العمر</ThemedText>
                <ThemedText style={styles.sectionHeaderSubtitle} lightColor={mutedColor} darkColor={mutedColor}>
                  الإناث الأحياء فقط
                </ThemedText>
              </View>
            </View>

            {(() => {
              const females = data.allFamilyFemalesByAge ?? [];
              if (!females.length) {
                return <ThemedText style={styles.emptyHint} lightColor={mutedColor} darkColor={mutedColor}>لا توجد بيانات</ThemedText>;
              }
              const visibleFemales = females.slice(0, femalesLimit);
              return (
                <View>
                  <View style={{ gap: 8 }}>
                    {visibleFemales.map((person, idx) => (
                      <Pressable
                        key={`female-${person.id}-${idx}`}
                        onPress={() => openPersonStats(person)}
                        style={({ pressed }) => [
                          styles.personItemRow,
                          {
                            backgroundColor: isDark ? '#23151c' : 'rgba(255, 255, 255, 0.7)',
                            borderColor: isDark ? '#3d202e' : '#fce7f3',
                            opacity: pressed ? 0.85 : 1,
                          },
                        ]}>
                        {/* البادج الرقمي الوردي */}
                        <LinearGradient
                          colors={['#ec4899', '#db2777']}
                          style={styles.rankBadge}>
                          <ThemedText style={styles.rankBadgeText}>{idx + 1}</ThemedText>
                        </LinearGradient>

                        {/* الاسم مع رمز الإناث (إخفاء العمر احتراماً للخصوصية مثل الـ Blade) */}
                        <View style={{ flex: 1, paddingHorizontal: 8 }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                            <ThemedText style={[styles.personItemName, { color: isDark ? '#f472b6' : '#db2777' }]} numberOfLines={1}>
                              {person.full_name}
                            </ThemedText>
                            <ThemedText style={{ color: '#db2777', fontSize: 13, fontFamily: 'Tajawal-Bold' }}>♀</ThemedText>
                          </View>
                          {person.location ? (
                            <ThemedText style={styles.personItemSub} lightColor={mutedColor} darkColor={mutedColor}>
                              {formatLocationDisplay(person.location)}
                            </ThemedText>
                          ) : null}
                        </View>
                      </Pressable>
                    ))}
                  </View>

                  <ShowMoreBtn
                    visibleCount={visibleFemales.length}
                    total={females.length}
                    onMore={() => setFemalesLimit((n) => n + LIST_PAGE)}
                    onCollapse={() => setFemalesLimit(LIST_INITIAL)}
                    isDark={isDark}
                  />
                </View>
              );
            })()}
          </View>
  );
}
