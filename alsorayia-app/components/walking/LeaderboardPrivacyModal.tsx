import { ThemedText } from '@/components/themed-text';
import { Colors, silaTheme } from '@/constants/theme';
import type { WalkingPrivacyMode } from '@/lib/api/walkingApi';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

type Props = {
  visible: boolean;
  currentMode: WalkingPrivacyMode;
  onClose: () => void;
  onSave: (mode: WalkingPrivacyMode) => Promise<boolean>;
};

interface PrivacyOption {
  key: WalkingPrivacyMode;
  badge: string;
  badgeColor: string;
  icon: string;
  title: string;
  desc: string;
}

const OPTIONS: PrivacyOption[] = [
  {
    key: 'visible',
    badge: 'مستحسن',
    badgeColor: '#10B981',
    icon: '🏆',
    title: 'الظهور الكامل للجميع',
    desc: 'يظهر اسمك وصورتك وترتيبك وخطواتك الكاملة لتحفيز التنافس الشريف بين أفراد العائلة.',
  },
  {
    key: 'anonymous',
    badge: 'خصوصية ذكية',
    badgeColor: '#F59E0B',
    icon: '🥷',
    title: 'إخفاء بياناتي الشخصية فقط',
    desc: 'تظهر خطواتك وترتيبك بدقة في الصدارة، ولكن يُحجب اسمك وصورتك وتظهر بدلاً منهما صورة رمزية عامة (متسابق متخفي).',
  },
  {
    key: 'hidden',
    badge: 'استبعاد تام',
    badgeColor: '#EF4444',
    icon: '🚫',
    title: 'عدم الظهور في لوحة الصدارة',
    desc: 'استبعاد خطواتك وترتيبك تماماً من لوحة الشرف، مع استمرار تسجيل خطواتك وإحصائياتك الشخصية في نشاطك.',
  },
];

export function LeaderboardPrivacyModal({ visible, currentMode, onClose, onSave }: Props) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme === 'dark' ? 'dark' : 'light'];
  const isDark = colorScheme === 'dark';

  const [selected, setSelected] = useState<WalkingPrivacyMode>(currentMode);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setSelected(currentMode);
  }, [currentMode, visible]);

  const handleSelect = (mode: WalkingPrivacyMode) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    setSelected(mode);
  };

  const handleConfirm = async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    setSaving(true);
    const ok = await onSave(selected);
    setSaving(false);
    if (ok) {
      onClose();
    }
  };

  return (
    <Modal visible={visible} animationType="fade" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View
          style={[
            styles.content,
            {
              backgroundColor: isDark ? '#18181B' : '#FFFFFF',
              borderColor: colors.border,
            },
          ]}>
          {/* Header */}
          <View style={styles.header}>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn} activeOpacity={0.7} hitSlop={10}>
              <Ionicons name="close" size={20} color={colors.icon} />
            </TouchableOpacity>
            <View style={styles.headerTitleWrap}>
              <ThemedText style={styles.title}>إعدادات الخصوصية في الصدارة</ThemedText>
              <ThemedText style={[styles.subtitle, { color: colors.icon }]}>
                تحكّم في كيفية ظهورك وتنافسك في لوحة المتصدرين العامة
              </ThemedText>
            </View>
          </View>

          {/* Options */}
          <View style={styles.optionsWrap}>
            {OPTIONS.map((opt) => {
              const isSelected = selected === opt.key;
              return (
                <TouchableOpacity
                  key={opt.key}
                  activeOpacity={0.85}
                  onPress={() => handleSelect(opt.key)}
                  style={[
                    styles.optionCard,
                    {
                      backgroundColor: isDark ? '#27272A' : '#F9FAFB',
                      borderColor: isSelected ? silaTheme.primary : (isDark ? '#3F3F46' : '#E5E7EB'),
                    },
                    isSelected && styles.optionCardSelected,
                  ]}>
                  <View style={styles.cardHeader}>
                    <View style={styles.iconTitleRow}>
                      <ThemedText style={styles.optionEmoji}>{opt.icon}</ThemedText>
                      <ThemedText style={[styles.optionTitle, isSelected && { color: silaTheme.primary }]}>
                        {opt.title}
                      </ThemedText>
                    </View>
                    <View style={[styles.badge, { backgroundColor: opt.badgeColor + '20' }]}>
                      <ThemedText style={[styles.badgeText, { color: opt.badgeColor }]}>
                        {opt.badge}
                      </ThemedText>
                    </View>
                  </View>
                  <ThemedText style={[styles.optionDesc, { color: colors.icon }]}>
                    {opt.desc}
                  </ThemedText>
                </TouchableOpacity>
              );
            })}
          </View>

          {/* Action Button */}
          <TouchableOpacity
            activeOpacity={0.8}
            onPress={handleConfirm}
            disabled={saving}
            style={[styles.saveBtn, { backgroundColor: silaTheme.primary }]}>
            {saving ? (
              <ActivityIndicator color="#FFFFFF" size="small" />
            ) : (
              <ThemedText style={styles.saveBtnText}>حفظ الإعدادات</ThemedText>
            )}
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
  content: {
    width: '100%',
    maxWidth: 420,
    borderRadius: 24,
    borderWidth: 1,
    padding: 22,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 20,
    elevation: 8,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 18,
  },
  closeBtn: {
    padding: 6,
    borderRadius: 12,
    backgroundColor: 'rgba(150, 150, 150, 0.1)',
  },
  headerTitleWrap: {
    flex: 1,
    marginRight: 10,
    alignItems: 'flex-end',
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 17,
    textAlign: 'right',
  },
  subtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    textAlign: 'right',
    marginTop: 4,
    lineHeight: 18,
  },
  optionsWrap: {
    gap: 12,
    marginBottom: 20,
  },
  optionCard: {
    borderRadius: 16,
    borderWidth: 1.5,
    padding: 14,
  },
  optionCardSelected: {
    borderWidth: 2,
    backgroundColor: 'rgba(16, 185, 129, 0.06)',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  iconTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  optionEmoji: {
    fontSize: 18,
  },
  optionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  badgeText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11,
  },
  optionDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    lineHeight: 18,
    textAlign: 'right',
  },
  saveBtn: {
    height: 48,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
  },
  saveBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    color: '#FFFFFF',
  },
});
