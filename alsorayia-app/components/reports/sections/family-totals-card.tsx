import { Ionicons } from '@expo/vector-icons';
import { View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ThemedText } from '@/components/themed-text';
import type { ReportsPageData } from '@/lib/api/reports';
import { formatNumber } from '../helpers';
import { styles } from '../styles';

interface TotalFamilyStatsCardProps {
  data: ReportsPageData;
  isDark: boolean;
  mutedColor: string;
}

export function TotalFamilyStatsCard({ data, isDark, mutedColor }: TotalFamilyStatsCardProps) {
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
                <ThemedText style={styles.sectionHeaderTitle}>إجمالي أبناء العائلة</ThemedText>
                <ThemedText style={styles.sectionHeaderSubtitle} lightColor={mutedColor} darkColor={mutedColor}>
                  عدد أبناء العائلة من الذكور والإناث (الأحياء فقط)
                </ThemedText>
              </View>
            </View>

            {/* بطاقة الإجمالي الكبرى */}
            <LinearGradient
              colors={isDark ? ['#052e16', '#064e3b'] : ['#f0fdf4', '#dcfce7']}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={styles.totalBigCard}>
              <ThemedText style={[styles.totalBigNumber, { color: isDark ? '#4ade80' : '#15803d' }]}>
                {formatNumber(data.totalFamilyMembers)}
              </ThemedText>
              <ThemedText style={[styles.totalBigLabel, { color: isDark ? '#bbf7d0' : '#166534' }]}>
                إجمالي الأبناء
              </ThemedText>
            </LinearGradient>

            {/* شبكة الذكور والإناث */}
            <View style={styles.genderStatsGrid}>
              {/* الذكور */}
              <View
                style={[
                  styles.genderStatBox,
                  { backgroundColor: isDark ? '#172554' : '#eff6ff' },
                ]}>
                <ThemedText style={[styles.genderNumber, { color: isDark ? '#93c5fd' : '#2563eb' }]}>
                  {formatNumber(data.maleCount)}
                </ThemedText>
                <View style={styles.genderLabelRow}>
                  <Ionicons name="male" size={14} color={isDark ? '#93c5fd' : '#2563eb'} />
                  <ThemedText style={[styles.genderLabel, { color: isDark ? '#bfdbfe' : '#1e40af' }]}>
                    ذكور
                  </ThemedText>
                </View>
                <ThemedText style={[styles.genderPercent, { color: isDark ? '#93c5fd' : '#3b82f6' }]}>
                  {data.totalFamilyMembers > 0
                    ? ((data.maleCount / data.totalFamilyMembers) * 100).toFixed(1)
                    : 0}
                  %
                </ThemedText>
              </View>

              {/* الإناث */}
              <View
                style={[
                  styles.genderStatBox,
                  { backgroundColor: isDark ? '#3b1d28' : '#fdf2f8' },
                ]}>
                <ThemedText style={[styles.genderNumber, { color: isDark ? '#f472b6' : '#db2777' }]}>
                  {formatNumber(data.femaleCount)}
                </ThemedText>
                <View style={styles.genderLabelRow}>
                  <Ionicons name="female" size={14} color={isDark ? '#f472b6' : '#db2777'} />
                  <ThemedText style={[styles.genderLabel, { color: isDark ? '#fbcfe8' : '#9d174d' }]}>
                    إناث
                  </ThemedText>
                </View>
                <ThemedText style={[styles.genderPercent, { color: isDark ? '#f472b6' : '#ec4899' }]}>
                  {data.totalFamilyMembers > 0
                    ? ((data.femaleCount / data.totalFamilyMembers) * 100).toFixed(1)
                    : 0}
                  %
                </ThemedText>
              </View>
            </View>
          </View>
  );
}
