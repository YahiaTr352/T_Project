import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import type { ReportsGenerationTier, ReportsPageData, ReportsPersonRow } from '@/lib/api/reports';
import {
  GENERATION_LABELS,
  LIST_INITIAL,
  LIST_PAGE,
  formatNumber,
  sortGenerationKeys,
} from '../helpers';
import { styles } from '../styles';
import { ShowMoreBtn } from './show-more-btn';

interface GrandfathersTableCardProps {
  data: ReportsPageData;
  gfCardsLimit: number;
  setGfCardsLimit: Dispatch<SetStateAction<number>>;
  expandedGf: Set<number>;
  gfToggles: Record<string, 'members' | 'relatives' | null>;
  toggleGrandfather: (id: number) => void;
  toggleGfSection: (gfId: number, genKey: string, section: 'members' | 'relatives') => void;
  openPersonStats: (person: ReportsPersonRow | { id: number; full_name: string }) => void;
  isDark: boolean;
  mutedColor: string;
}

export function GrandfathersTableCard({
  data,
  gfCardsLimit,
  setGfCardsLimit,
  expandedGf,
  gfToggles,
  toggleGrandfather,
  toggleGfSection,
  openPersonStats,
  isDark,
  mutedColor,
}: GrandfathersTableCardProps) {
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
                <ThemedText style={styles.sectionHeaderTitle}>عدد الأبناء والأحفاد حسب الجد</ThemedText>
                <ThemedText style={styles.sectionHeaderSubtitle} lightColor={mutedColor} darkColor={mutedColor}>
                  العرض يشمل جميع الأجيال: الجيل الأول ← الجيل الثاني ← الجيل الثالث ← الجيل الرابع
                </ThemedText>
              </View>
            </View>

            {(() => {
              const gfs = data.generationsData ?? [];
              if (!gfs.length) {
                return <ThemedText style={styles.emptyHint} lightColor={mutedColor} darkColor={mutedColor}>لا توجد بيانات</ThemedText>;
              }
              const visibleGfs = gfs.slice(0, gfCardsLimit);
              return (
                <View style={styles.tableContainer}>
                  {/* ترويسة الجدول مطابق لـ reports.blade.php */}
                  <View style={[styles.tableHeaderRow, { borderBottomColor: isDark ? '#1e3a29' : '#bbf7d0' }]}>
                    <ThemedText style={[styles.tableColHeader, { flex: 2.2, textAlign: 'right' }]}>الجد</ThemedText>
                    <ThemedText style={[styles.tableColHeader, { flex: 1.2, textAlign: 'center' }]}>الأحفاد</ThemedText>
                    <ThemedText style={[styles.tableColHeader, { flex: 1, textAlign: 'center', color: '#3b82f6' }]}>ذكور</ThemedText>
                    <ThemedText style={[styles.tableColHeader, { flex: 1, textAlign: 'center', color: '#ec4899' }]}>إناث</ThemedText>
                  </View>

                  {visibleGfs.map((gf) => {
                    const isExpanded = expandedGf.has(gf.grandfather_id);
                    const genKeys = sortGenerationKeys(gf.generations_breakdown);

                    return (
                      <View
                        key={`gf-${gf.grandfather_id}`}
                        style={{
                          borderBottomWidth: StyleSheet.hairlineWidth,
                          borderBottomColor: isDark ? '#1e2621' : '#f3f4f6',
                        }}>
                        {/* صف الجد في الجدول */}
                        <Pressable
                          onPress={() => toggleGrandfather(gf.grandfather_id)}
                          style={({ pressed }) => [
                            styles.tableDataRow,
                            {
                              borderBottomWidth: 0,
                              backgroundColor: isExpanded
                                ? isDark
                                  ? '#14251a'
                                  : '#f0fdf4'
                                : pressed
                                ? isDark
                                  ? '#1a241d'
                                  : '#f9fafb'
                                : 'transparent',
                            },
                          ]}>
                          {/* اسم الجد مع زر استعرض / طي مصغر */}
                          <View style={{ flex: 2.2, flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                            <View
                              style={[
                                styles.viewToggleBtnMini,
                                {
                                  backgroundColor: isExpanded
                                    ? isDark
                                      ? '#166534'
                                      : '#bbf7d0'
                                    : isDark
                                    ? '#1b2e20'
                                    : '#e8f5e9',
                                },
                              ]}>
                              <Ionicons
                                name={isExpanded ? 'chevron-up' : 'eye-outline'}
                                size={13}
                                color={isDark ? '#86efac' : '#15803d'}
                              />
                            </View>
                            <ThemedText
                              style={[styles.tableCityName, { flex: 1, color: isDark ? '#f3f4f6' : '#1f2937' }]}
                              numberOfLines={1}>
                              {gf.grandfather_name}
                            </ThemedText>
                          </View>

                          {/* إجمالي الأحفاد */}
                          <View style={{ flex: 1.2, alignItems: 'center' }}>
                            <View style={[styles.miniBadge, { backgroundColor: isDark ? '#14532d' : '#dcfce7' }]}>
                              <ThemedText style={[styles.miniBadgeText, { color: isDark ? '#86efac' : '#15803d' }]}>
                                {formatNumber(gf.total_descendants)}
                              </ThemedText>
                            </View>
                          </View>

                          {/* ذكور */}
                          <View style={{ flex: 1, alignItems: 'center' }}>
                            <View style={[styles.miniBadge, { backgroundColor: isDark ? '#1e3a8a' : '#dbeafe' }]}>
                              <ThemedText style={[styles.miniBadgeText, { color: isDark ? '#93c5fd' : '#1d4ed8' }]}>
                                {formatNumber(gf.male_descendants)}
                              </ThemedText>
                            </View>
                          </View>

                          {/* إناث */}
                          <View style={{ flex: 1, alignItems: 'center' }}>
                            <View style={[styles.miniBadge, { backgroundColor: isDark ? '#831843' : '#fce7f3' }]}>
                              <ThemedText style={[styles.miniBadgeText, { color: isDark ? '#fbcfe8' : '#be185d' }]}>
                                {formatNumber(gf.female_descendants)}
                              </ThemedText>
                            </View>
                          </View>
                        </Pressable>

                        {/* التفاصيل عند التمدد */}
                        {isExpanded ? (
                          <View
                            style={[
                              styles.gfExpandedContent,
                              {
                                backgroundColor: isDark ? '#101712' : '#f8fafc',
                                borderTopWidth: 1,
                                borderTopColor: isDark ? '#1e2d21' : '#e2e8f0',
                              },
                            ]}>
                            {genKeys.map((gKey) => {
                              const tier = gf.generations_breakdown[gKey] as ReportsGenerationTier;
                              const toggleKey = `${gf.grandfather_id}-${gKey}`;
                              const activeSub = gfToggles[toggleKey];

                              return (
                                <View
                                  key={`gf-gen-${gKey}`}
                                  style={[
                                    styles.genTierCard,
                                    {
                                      backgroundColor: isDark ? '#151d17' : '#ffffff',
                                      borderColor: isDark ? '#263329' : '#e5e7eb',
                                    },
                                  ]}>
                                  <View style={styles.genTierTop}>
                                    <ThemedText style={styles.genTierLabel}>
                                      {GENERATION_LABELS[gKey] ?? `الجيل ${gKey}`}
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

                                  {/* أزرار العرض (الأبناء / الأنساب) */}
                                  <View style={styles.genActionBtnsRow}>
                                    {tier.members && tier.members.length > 0 ? (
                                      <Pressable
                                        onPress={() => toggleGfSection(gf.grandfather_id, gKey, 'members')}
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
                                        onPress={() => toggleGfSection(gf.grandfather_id, gKey, 'relatives')}
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

                                  {/* القائمة المنسدلة للأبناء أو الأنساب */}
                                  {activeSub === 'members' && tier.members ? (
                                    <View style={[styles.subMemberList, { borderTopColor: isDark ? '#263329' : '#e5e7eb' }]}>
                                      <ThemedText style={styles.subListHeader}>قائمة الأبناء:</ThemedText>
                                      {tier.members.map((m) => (
                                        <Pressable
                                          key={`mem-${gf.grandfather_id}-${gKey}-${m.id}`}
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
                                                : '#f9fafb',
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

                                  {activeSub === 'relatives' && tier.relatives ? (
                                    <View style={[styles.subMemberList, { borderTopColor: isDark ? '#263329' : '#e5e7eb' }]}>
                                      <ThemedText style={[styles.subListHeader, { color: '#9333ea' }]}>
                                        قائمة الأنساب (من خارج العائلة):
                                      </ThemedText>
                                      {tier.relatives.map((r) => (
                                        <Pressable
                                          key={`rel-${gf.grandfather_id}-${gKey}-${r.id}`}
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
                                                : '#faf5ff',
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
                        ) : null}
                      </View>
                    );
                  })}

                  {/* زر عرض المزيد للأجداد */}
                  <ShowMoreBtn
                    visibleCount={visibleGfs.length}
                    total={gfs.length}
                    onMore={() => setGfCardsLimit((n) => n + LIST_PAGE)}
                    onCollapse={() => setGfCardsLimit(LIST_INITIAL)}
                    isDark={isDark}
                  />
                </View>
              );
            })()}
          </View>
  );
}
