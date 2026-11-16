import { silaTheme } from '@/constants/theme';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import * as Haptics from 'expo-haptics';
import React, { useEffect } from 'react';
import {
  Modal,
  Platform,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ThemedText } from '../themed-text';

export interface DeviceGraceNoticeModalProps {
  visible: boolean;
  personName?: string;
  hoursRemaining?: number;
  onProceed: () => void;
}

/**
 * شاشة تنبيه مهلة الواتساب (24 ساعة) للأعضاء الذين لا يمتلكون رقم واتساب مسجل
 */
export function DeviceGraceNoticeModal({
  visible,
  personName,
  hoursRemaining = 24,
  onProceed,
}: DeviceGraceNoticeModalProps) {
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';

  useEffect(() => {
    if (visible && Platform.OS !== 'web') {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    }
  }, [visible]);

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent
      statusBarTranslucent
      onRequestClose={onProceed}
    >
      <View style={styles.overlay}>
        <View
          style={[
            styles.card,
            {
              backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
              borderColor: isDark ? '#334155' : '#E2E8F0',
              paddingBottom: insets.bottom > 0 ? insets.bottom + 12 : 24,
            },
          ]}
        >
          <View style={styles.iconCircle}>
            <MaterialIcons name="security" size={36} color="#D97706" />
          </View>

          <ThemedText style={[styles.title, { color: isDark ? '#F8FAFC' : '#0F172A' }]}>
            {personName ? `أهلاً بك يا ${personName}` : 'أهلاً بك في منصة العائلة'}
          </ThemedText>

          <View style={styles.badgeRow}>
            <MaterialIcons name="check-circle" size={16} color="#10B981" />
            <ThemedText style={styles.badgeText}>تم التعرف على ملامح وجهك بنجاح</ThemedText>
          </View>

          <View
            style={[
              styles.noticeBox,
              { backgroundColor: isDark ? 'rgba(217, 119, 6, 0.15)' : '#FEF3C7' },
            ]}
          >
            <ThemedText style={[styles.noticeText, { color: isDark ? '#FDE68A' : '#92400E' }]}>
              تم رصد دخولك من جهاز جديد لأول مرة. لحماية حسابك وتوثيق أجهزتك مستقبلاً، يلزم ربط رقم واتساب معتمد.
            </ThemedText>
            <ThemedText
              style={[
                styles.noticeSubText,
                { color: isDark ? '#FCD34D' : '#B45309', marginTop: 8 },
              ]}
            >
              ⏳ تم تفعيل مهلة أمان مؤقتة لمدة ({hoursRemaining} ساعة) لمتابعة الدخول. يُرجى تحديث رقم الواتساب من الملف الشخصي.
            </ThemedText>
          </View>

          <TouchableOpacity
            style={[styles.proceedBtn, { backgroundColor: silaTheme.primary }]}
            onPress={onProceed}
            activeOpacity={0.85}
          >
            <MaterialIcons name="arrow-back" size={20} color="#FFFFFF" style={{ marginLeft: 6 }} />
            <ThemedText style={styles.proceedBtnText}>متابعة الدخول للمنصة</ThemedText>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.65)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  card: {
    width: '100%',
    maxWidth: 420,
    borderRadius: 24,
    padding: 24,
    alignItems: 'center',
    borderWidth: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.15,
    shadowRadius: 20,
    elevation: 8,
  },
  iconCircle: {
    width: 68,
    height: 68,
    borderRadius: 34,
    backgroundColor: '#FEF3C7',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
    textAlign: 'center',
    marginBottom: 6,
  },
  badgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 16,
  },
  badgeText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#059669',
  },
  noticeBox: {
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: '#FDE68A',
  },
  noticeText: {
    fontSize: 14,
    lineHeight: 22,
    textAlign: 'center',
    fontWeight: '600',
  },
  noticeSubText: {
    fontSize: 13,
    lineHeight: 20,
    textAlign: 'center',
    fontWeight: '700',
  },
  proceedBtn: {
    width: '100%',
    height: 52,
    borderRadius: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: silaTheme.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 3,
  },
  proceedBtnText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '800',
  },
});
