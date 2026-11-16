import React, { useCallback } from 'react';
import {
  Platform,
  StyleSheet,
  TouchableOpacity,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { type Href, useRouter } from 'expo-router';

import { ThemedText } from '@/components/themed-text';
import { type ThemeColors } from '@/constants/theme';

interface HomeContactWidgetProps {
  colors: ThemeColors;
  isDark?: boolean;
  onFocusInput?: () => void;
}

export default function HomeContactWidget({ colors, isDark = false }: HomeContactWidgetProps) {
  const router = useRouter();

  const handleOpenContact = useCallback(() => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push('/(app)/contact' as Href);
  }, [router]);

  return (
    <View style={styles.container}>
      <TouchableOpacity
        activeOpacity={0.88}
        onPress={handleOpenContact}
        style={[
          styles.card,
          {
            backgroundColor: isDark ? '#1C1917' : '#FFFFFF',
            borderColor: isDark ? 'rgba(255, 255, 255, 0.08)' : '#E7E5E4',
          },
        ]}
      >
        <View style={styles.contentRow}>
          {/* Suggestion Icon */}
          <View
            style={[
              styles.iconWrapper,
              {
                backgroundColor: isDark ? 'rgba(245, 158, 11, 0.15)' : '#FEF3C7',
                borderColor: isDark ? 'rgba(245, 158, 11, 0.25)' : '#FDE68A',
              },
            ]}
          >
            <Ionicons
              name="bulb-outline"
              size={22}
              color={isDark ? '#FBBF24' : '#D97706'}
            />
          </View>

          {/* Text Info */}
          <View style={styles.textContainer}>
            <ThemedText style={[styles.title, { color: isDark ? '#FFFFFF' : '#111827' }]}>
              هل لديك اقتراح؟
            </ThemedText>
            <ThemedText style={[styles.subtitle, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>
              شاركنا أفكارك وملاحظاتك لتطوير المنصة
            </ThemedText>
          </View>

          {/* Action Button */}
          <View
            style={[
              styles.actionButton,
              {
                backgroundColor: isDark ? '#059669' : '#047857',
              },
            ]}
          >
            <ThemedText style={styles.actionButtonText}>
              شاركنا باقتراحك
            </ThemedText>
            <Ionicons
              name="chevron-back"
              size={14}
              color="#FFFFFF"
              style={{ marginRight: -1 }}
            />
          </View>
        </View>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginHorizontal: 16,
    marginVertical: 10,
  },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 6,
      },
      android: {
        elevation: 2,
      },
    }),
  },
  contentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  iconWrapper: {
    width: 44,
    height: 44,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  textContainer: {
    flex: 1,
    justifyContent: 'center',
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
    marginBottom: 2,
  },
  subtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11.5,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
    ...Platform.select({
      ios: {
        shadowColor: '#059669',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 4,
      },
      android: {
        elevation: 2,
      },
    }),
  },
  actionButtonText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 12,
  },
});
