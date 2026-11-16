import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { Pressable, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import type { ReportsLocationStatRow } from '@/lib/api/reports';
import { LIST_INITIAL, LIST_PAGE, formatNumber } from '../helpers';
import { styles } from '../styles';
import { ShowMoreBtn } from './show-more-btn';

interface LocationsPopulationCardProps {
  locationsSummary: { list: ReportsLocationStatRow[]; total: number; males: number; females: number };
  locationsLimit: number;
  setLocationsLimit: Dispatch<SetStateAction<number>>;
  openLocationList: (row: ReportsLocationStatRow) => void;
  isDark: boolean;
  mutedColor: string;
}

export function LocationsPopulationCard({
  locationsSummary,
  locationsLimit,
  setLocationsLimit,
  openLocationList,
  isDark,
  mutedColor,
}: LocationsPopulationCardProps) {
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
                <ThemedText style={styles.sectionHeaderTitle}>عدد سكان المدن</ThemedText>
                <ThemedText style={styles.sectionHeaderSubtitle} lightColor={mutedColor} darkColor={mutedColor}>
                  توزيع أبناء العائلة على الأماكن حسب الذكور والإناث
                </ThemedText>
              </View>
            </View>

            {/* صفوف المدن */}
            <View style={styles.tableContainer}>
              <View style={[styles.tableHeaderRow, { borderBottomColor: isDark ? '#1e3a29' : '#bbf7d0' }]}>
                <ThemedText style={[styles.tableColHeader, { flex: 2, textAlign: 'right' }]}>المدينة</ThemedText>
                <ThemedText style={[styles.tableColHeader, { flex: 1.2, textAlign: 'center' }]}>الإجمالي</ThemedText>
                <ThemedText style={[styles.tableColHeader, { flex: 1, textAlign: 'center', color: '#3b82f6' }]}>ذكور</ThemedText>
                <ThemedText style={[styles.tableColHeader, { flex: 1, textAlign: 'center', color: '#ec4899' }]}>إناث</ThemedText>
                <ThemedText style={[styles.tableColHeader, { flex: 1.4, textAlign: 'center' }]}>النسبة</ThemedText>
              </View>

              {locationsSummary.list.length === 0 ? (
                <ThemedText style={styles.emptyHint} lightColor={mutedColor} darkColor={mutedColor}>
                  لا توجد بيانات عن الأماكن
                </ThemedText>
              ) : (
                locationsSummary.list.slice(0, locationsLimit).map((row, idx) => {
                  const locName = String(row.location_name ?? row.name ?? 'موقع');
                  const count = Number(row.total ?? row.count ?? row.persons_count ?? 0) || 0;
                  const males = Number(row.males ?? 0) || 0;
                  const females = Number(row.females ?? 0) || 0;
                  const pct = locationsSummary.total > 0 ? (count / locationsSummary.total) * 100 : 0;

                  return (
                    <Pressable
                      key={`loc-item-${row.id ?? row.location_id ?? idx}`}
                      onPress={() => openLocationList(row)}
                      style={({ pressed }) => [
                        styles.tableDataRow,
                        {
                          borderBottomColor: isDark ? '#1e2621' : '#f3f4f6',
                          backgroundColor: pressed
                            ? isDark
                              ? '#1f2922'
                              : '#f0fdf4'
                            : 'transparent',
                        },
                      ]}>
                      {/* اسم المدينة */}
                      <View style={{ flex: 2, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
                        <Ionicons name="location-sharp" size={14} color="#16a34a" />
                        <ThemedText
                          style={[styles.tableCityName, { color: isDark ? '#86efac' : '#15803d' }]}
                          numberOfLines={1}>
                          {locName}
                        </ThemedText>
                      </View>

                      {/* الإجمالي */}
                      <View style={{ flex: 1.2, alignItems: 'center' }}>
                        <View style={[styles.miniBadge, { backgroundColor: isDark ? '#14532d' : '#dcfce7' }]}>
                          <ThemedText style={[styles.miniBadgeText, { color: isDark ? '#86efac' : '#15803d' }]}>
                            {formatNumber(count)}
                          </ThemedText>
                        </View>
                      </View>

                      {/* الذكور */}
                      <View style={{ flex: 1, alignItems: 'center' }}>
                        <View style={[styles.miniBadge, { backgroundColor: isDark ? '#1e3a8a' : '#dbeafe' }]}>
                          <ThemedText style={[styles.miniBadgeText, { color: isDark ? '#93c5fd' : '#1d4ed8' }]}>
                            {formatNumber(males)}
                          </ThemedText>
                        </View>
                      </View>

                      {/* الإناث */}
                      <View style={{ flex: 1, alignItems: 'center' }}>
                        <View style={[styles.miniBadge, { backgroundColor: isDark ? '#831843' : '#fce7f3' }]}>
                          <ThemedText style={[styles.miniBadgeText, { color: isDark ? '#fbcfe8' : '#be185d' }]}>
                            {formatNumber(females)}
                          </ThemedText>
                        </View>
                      </View>

                      {/* النسبة المئوية مع شريط التقدم */}
                      <View style={{ flex: 1.4, alignItems: 'center', gap: 3 }}>
                        <ThemedText style={styles.tablePctText}>
                          {pct.toFixed(1)}%
                        </ThemedText>
                        <View style={[styles.progressBarTrack, { backgroundColor: isDark ? '#27342b' : '#e5e7eb' }]}>
                          <View
                            style={[
                              styles.progressBarFill,
                              { width: `${Math.min(100, Math.max(0, pct))}%` },
                            ]}
                          />
                        </View>
                      </View>
                    </Pressable>
                  );
                })
              )}

              {/* صف الإجمالي النهائي */}
              {locationsSummary.list.length > 0 ? (
                <View
                  style={[
                    styles.tableFooterRow,
                    {
                      backgroundColor: isDark ? '#0b2615' : '#f0fdf4',
                      borderTopColor: isDark ? '#166534' : '#86efac',
                    },
                  ]}>
                  <ThemedText style={[styles.tableFooterLabel, { flex: 2 }]}>الإجمالي</ThemedText>
                  <View style={{ flex: 1.2, alignItems: 'center' }}>
                    <View style={[styles.solidBadge, { backgroundColor: '#16a34a' }]}>
                      <ThemedText style={styles.solidBadgeText}>
                        {formatNumber(locationsSummary.total)}
                      </ThemedText>
                    </View>
                  </View>
                  <View style={{ flex: 1, alignItems: 'center' }}>
                    <View style={[styles.solidBadge, { backgroundColor: '#2563eb' }]}>
                      <ThemedText style={styles.solidBadgeText}>
                        {formatNumber(locationsSummary.males)}
                      </ThemedText>
                    </View>
                  </View>
                  <View style={{ flex: 1, alignItems: 'center' }}>
                    <View style={[styles.solidBadge, { backgroundColor: '#db2777' }]}>
                      <ThemedText style={styles.solidBadgeText}>
                        {formatNumber(locationsSummary.females)}
                      </ThemedText>
                    </View>
                  </View>
                  <ThemedText style={[styles.tablePctText, { flex: 1.4, textAlign: 'center', fontFamily: 'Tajawal-Bold' }]}>
                    100%
                  </ThemedText>
                </View>
              ) : null}

              {/* زر عرض المزيد من الأماكن */}
              <ShowMoreBtn
                visibleCount={Math.min(locationsLimit, locationsSummary.list.length)}
                total={locationsSummary.list.length}
                onMore={() => setLocationsLimit((n) => n + LIST_PAGE)}
                onCollapse={() => setLocationsLimit(LIST_INITIAL)}
                isDark={isDark}
              />
            </View>
          </View>
  );
}
