import { Ionicons } from '@expo/vector-icons';
import { View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ThemedText } from '@/components/themed-text';
import type { ReportsPageData } from '@/lib/api/reports';
import { formatNumber } from '../helpers';
import { styles } from '../styles';

interface EducationCardsRowProps {
  data: ReportsPageData;
  isDark: boolean;
}

export function EducationCardsRow({ data, isDark }: EducationCardsRowProps) {
  return (
          <View style={styles.eduCardsRow}>
            {/* حملة الدكتوراه */}
            <View
              style={[
                styles.eduCard,
                {
                  backgroundColor: isDark ? '#231a08' : 'rgba(255, 255, 255, 0.94)',
                  borderColor: isDark ? '#78350f' : '#fef3c7',
                },
              ]}>
              <View style={styles.eduCardHeader}>
                <LinearGradient
                  colors={['#f59e0b', '#d97706']}
                  style={styles.eduIconCircle}>
                  <Ionicons name="school" size={22} color="#ffffff" />
                </LinearGradient>
                <ThemedText style={[styles.eduCardTitle, { color: isDark ? '#fde68a' : '#b45309' }]}>
                  حملة الدكتوراه
                </ThemedText>
              </View>
              <ThemedText style={[styles.eduCardNumber, { color: isDark ? '#fbbf24' : '#d97706' }]}>
                {formatNumber(data.phdCount)}
              </ThemedText>
            </View>

            {/* حملة الماجستير */}
            <View
              style={[
                styles.eduCard,
                {
                  backgroundColor: isDark ? '#191933' : 'rgba(255, 255, 255, 0.94)',
                  borderColor: isDark ? '#312e81' : '#e0e7ff',
                },
              ]}>
              <View style={styles.eduCardHeader}>
                <LinearGradient
                  colors={['#6366f1', '#4f46e5']}
                  style={styles.eduIconCircle}>
                  <Ionicons name="school" size={22} color="#ffffff" />
                </LinearGradient>
                <ThemedText style={[styles.eduCardTitle, { color: isDark ? '#c7d2fe' : '#4338ca' }]}>
                  حملة الماجستير
                </ThemedText>
              </View>
              <ThemedText style={[styles.eduCardNumber, { color: isDark ? '#818cf8' : '#4f46e5' }]}>
                {formatNumber(data.masterDegreeCount)}
              </ThemedText>
            </View>

            {/* حملة البكالوريوس */}
            <View
              style={[
                styles.eduCard,
                {
                  backgroundColor: isDark ? '#0b2615' : 'rgba(255, 255, 255, 0.94)',
                  borderColor: isDark ? '#064e3b' : '#dcfce7',
                },
              ]}>
              <View style={styles.eduCardHeader}>
                <LinearGradient
                  colors={['#22c55e', '#16a34a']}
                  style={styles.eduIconCircle}>
                  <Ionicons name="school" size={22} color="#ffffff" />
                </LinearGradient>
                <ThemedText style={[styles.eduCardTitle, { color: isDark ? '#bbf7d0' : '#15803d' }]}>
                  حملة البكالوريوس
                </ThemedText>
              </View>
              <ThemedText style={[styles.eduCardNumber, { color: isDark ? '#4ade80' : '#16a34a' }]}>
                {formatNumber(data.bachelorDegreeCount)}
              </ThemedText>
            </View>
          </View>
  );
}
