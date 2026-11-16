import type { Dispatch, SetStateAction } from 'react';
import { Pressable, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ThemedText } from '@/components/themed-text';
import type { ReportsCommonNameRow, ReportsPageData } from '@/lib/api/reports';
import { NAMES_INITIAL, NAMES_PAGE, formatNumber } from '../helpers';
import { styles } from '../styles';
import { ShowMoreBtn } from './show-more-btn';

interface CommonNamesCardProps {
  data: ReportsPageData;
  namesLimit: number;
  setNamesLimit: Dispatch<SetStateAction<number>>;
  openNameList: (row: ReportsCommonNameRow) => void;
  isDark: boolean;
  mutedColor: string;
}

export function CommonNamesCard({
  data,
  namesLimit,
  setNamesLimit,
  openNameList,
  isDark,
  mutedColor,
}: CommonNamesCardProps) {
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
                <ThemedText style={styles.sectionHeaderTitle}>أكثر الأسماء تكراراً</ThemedText>
                <ThemedText style={styles.sectionHeaderSubtitle} lightColor={mutedColor} darkColor={mutedColor}>
                  الأسماء الأكثر شيوعاً بين أبناء العائلة
                </ThemedText>
              </View>
            </View>

            {(() => {
              const names = data.mostCommonNames ?? [];
              if (!names.length) {
                return <ThemedText style={styles.emptyHint} lightColor={mutedColor} darkColor={mutedColor}>لا توجد بيانات</ThemedText>;
              }
              const visibleNames = names.slice(0, namesLimit);
              return (
                <View>
                  <View style={styles.namesGrid}>
                    {visibleNames.map((row, idx) => {
                      const isFemale = row.dominant_gender === 'female';
                      const badgeColors = isFemale
                        ? (['#ec4899', '#db2777'] as const)
                        : (['#3b82f6', '#1d4ed8'] as const);
                      const textColor = isFemale
                        ? (isDark ? '#f472b6' : '#db2777')
                        : (isDark ? '#93c5fd' : '#1d4ed8');

                      return (
                        <Pressable
                          key={`cname-${row.name}-${idx}`}
                          onPress={() => openNameList(row)}
                          style={({ pressed }) => [
                            styles.nameCard,
                            {
                              backgroundColor: isDark ? '#1a221c' : 'rgba(255, 255, 255, 0.8)',
                              borderColor: isDark ? '#273429' : '#e5e7eb',
                              opacity: pressed ? 0.88 : 1,
                            },
                          ]}>
                          <View style={styles.nameCardTop}>
                            <LinearGradient colors={badgeColors} style={styles.miniRankCircle}>
                              <ThemedText style={styles.miniRankText}>{idx + 1}</ThemedText>
                            </LinearGradient>
                            <ThemedText style={[styles.nameCardCount, { color: textColor }]}>
                              {formatNumber(row.count)}
                            </ThemedText>
                          </View>
                          <ThemedText style={[styles.nameCardLabel, { color: textColor }]} numberOfLines={1}>
                            {row.name}
                          </ThemedText>
                        </Pressable>
                      );
                    })}
                  </View>

                  <ShowMoreBtn
                    visibleCount={visibleNames.length}
                    total={names.length}
                    onMore={() => setNamesLimit((n) => n + NAMES_PAGE)}
                    onCollapse={() => setNamesLimit(NAMES_INITIAL)}
                    isDark={isDark}
                  />
                </View>
              );
            })()}
          </View>
  );
}
