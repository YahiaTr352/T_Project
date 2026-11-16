import { ThemedText } from '@/components/themed-text';
import { Colors } from '@/constants/theme';
import { getHealthProvider } from '@/lib/walking/health';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import React from 'react';
import {
  Linking,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

interface Props {
  visible: boolean;
  onClose: () => void;
  onSettingsOpened?: () => void;
  onOpenWatchGuide?: () => void;
}

interface StepItem {
  number: number;
  title: string;
  description: string;
  badge?: string;
}

export function WalkingPermissionGuideModal({ visible, onClose, onSettingsOpened, onOpenWatchGuide }: Props) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  const isIos = Platform.OS === 'ios';
  const [isHcAvailable, setIsHcAvailable] = React.useState<boolean | null>(null);

  React.useEffect(() => {
    if (visible && Platform.OS === 'android') {
      const provider = getHealthProvider();
      if (provider) {
        void provider.isAvailable().then(setIsHcAvailable).catch(() => setIsHcAvailable(false));
      } else {
        setIsHcAvailable(false);
      }
    }
  }, [visible]);

  const iosSteps: StepItem[] = [
    {
      number: 1,
      title: 'اضغط على زر «فتح تطبيق صحتي»',
      description: 'سينقلك التطبيق مباشرة إلى تطبيق صحتي (Apple Health) على جهازك.',
    },
    {
      number: 2,
      title: 'اختر «الخصوصية» ثم «التطبيقات»',
      description: 'من الملف الشخصي، ادخل إلى الخصوصية ثم التطبيقات واختر «صلة العائلة».',
    },
    {
      number: 3,
      title: 'فعّل قراءة «الخطوات»',
      description: 'اسمح لتطبيق صلة العائلة بقراءة بيانات الخطوات من صحتي.',
      badge: 'مهم جداً',
    },
    {
      number: 4,
      title: 'عُد للتطبيق مباشرة',
      description: 'بمجرد العودة سيكتشف التطبيق الصلاحية تلقائياً ويبدأ بمزامنة خطواتك.',
    },
  ];

  const androidSteps: StepItem[] = isHcAvailable === false
    ? [
        {
          number: 1,
          title: 'اضغط زر «تثبيت Health Connect» أدناه',
          description: 'سينقلك التطبيق مباشرة لمتجر Google Play لتثبيت تطبيق Health Connect الرسمي من Google.',
          badge: 'خطوة أولى ضرورية',
        },
        {
          number: 2,
          title: 'اربط تطبيق لياقتك أو ساعتك',
          description: 'اربط Google Fit أو تطبيق ساعتك (سامسونج، هواوي، شاومي، إلخ) بـ Health Connect.',
        },
        {
          number: 3,
          title: 'اسمح بقراءة «الخطوات»',
          description: 'فعّل إذن قراءة الخطوات (Steps) لتطبيق صلة العائلة داخل Health Connect.',
          badge: 'الخطوة الأساسية',
        },
        {
          number: 4,
          title: 'عُد للتطبيق واضغط «مزامنة خطواتي»',
          description: 'ستُجلب خطواتك التراكمية التاريخية فوراً وبدقة تامة وتختفي مشكلة الـ 0 خطوة نهائياً!',
        },
      ]
    : [
        {
          number: 1,
          title: 'اضغط على زر «فتح Health Connect»',
          description: 'سينقلك التطبيق مباشرة إلى إعدادات Health Connect على جهازك.',
        },
        {
          number: 2,
          title: 'اختر «أذونات التطبيقات»',
          description: 'ابحث عن تطبيق «صلة العائلة» داخل قائمة التطبيقات المتصلة.',
        },
        {
          number: 3,
          title: 'اسمح بقراءة «الخطوات»',
          description: 'فعّل إذن قراءة الخطوات (Steps) لتتمكن من مزامنة نشاطك.',
          badge: 'الخطوة الأساسية',
        },
        {
          number: 4,
          title: 'عُد للتطبيق مباشرة',
          description: 'بمجرد الرجوع للشاشة ستبدأ مزامنة الخطوات وتُحدث لوحة الشرف فوراً!',
        },
      ];

  const steps = isIos ? iosSteps : androidSteps;

  const handleOpenSettings = async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    onSettingsOpened?.();

    const provider = getHealthProvider();
    if (provider && (await provider.isAvailable().catch(() => false))) {
      try {
        await provider.openSettings();
      } catch {}
      return;
    }

    if (Platform.OS === 'android') {
      await Linking.openURL(
        'https://play.google.com/store/apps/details?id=com.google.android.apps.healthdata'
      ).catch(() => Linking.openSettings().catch(() => {}));
      return;
    }

    await Linking.openSettings().catch(() => {});
  };

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent
      onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View
          style={[
            styles.modalContainer,
            {
              backgroundColor: isDark ? '#18181B' : '#FFFFFF',
              borderColor: isDark ? '#27272A' : '#E5E7EB',
            },
          ]}>
          {/* Header */}
          <View style={styles.header}>
            <TouchableOpacity
              onPress={onClose}
              style={[
                styles.closeButton,
                { backgroundColor: isDark ? '#27272A' : '#F3F4F6' },
              ]}
              activeOpacity={0.7}
              hitSlop={12}>
              <Ionicons name="close" size={20} color={colors.text} />
            </TouchableOpacity>

            <View style={styles.headerIconBadge}>
              <Ionicons name="footsteps-sharp" size={26} color="#16A34A" />
            </View>

            <ThemedText style={styles.title}>دليل تفعيل عدّاد الخطوات</ThemedText>
            <ThemedText style={[styles.subtitle, { color: colors.icon }]}>
              {isIos
                ? 'خطوات سريعة وبسيطة للسماح بقراءة الخطوات من تطبيق صحتي'
                : 'خطوات سريعة وبسيطة للسماح بقراءة الخطوات من Health Connect'}
            </ThemedText>
          </View>

          {/* Steps List */}
          <ScrollView
            showsVerticalScrollIndicator={false}
            contentContainerStyle={styles.scrollList}
            style={styles.scrollView}>
            {steps.map((step) => (
              <View
                key={step.number}
                style={[
                  styles.stepCard,
                  {
                    backgroundColor: isDark ? '#27272A80' : '#F9FAFB',
                    borderColor: isDark ? '#3F3F46' : '#E5E7EB',
                  },
                ]}>
                <View style={styles.stepNumberBadge}>
                  <ThemedText style={styles.stepNumberText}>{step.number}</ThemedText>
                </View>

                <View style={styles.stepContent}>
                  <View style={styles.stepTitleRow}>
                    <ThemedText style={styles.stepTitle}>{step.title}</ThemedText>
                    {step.badge && (
                      <View style={styles.tagBadge}>
                        <ThemedText style={styles.tagBadgeText}>{step.badge}</ThemedText>
                      </View>
                    )}
                  </View>
                  <ThemedText style={[styles.stepDesc, { color: colors.icon }]}>
                    {step.description}
                  </ThemedText>
                </View>
              </View>
            ))}

            {/* Smartwatch Banner */}
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={() => {
                onClose();
                onOpenWatchGuide?.();
              }}
              style={[
                styles.watchBannerCard,
                {
                  backgroundColor: isDark ? '#082F49' : '#F0F9FF',
                  borderColor: isDark ? '#0284C7' : '#BAE6FD',
                },
              ]}>
              <Ionicons name="watch-outline" size={22} color="#0284C7" />
              <View style={{ flex: 1 }}>
                <ThemedText style={[styles.watchBannerTitle, { color: '#0284C7' }]}>
                  هل تستخدم ساعة ذكية؟ (سامسونج، بيكسل، هواوي...)
                </ThemedText>
                <ThemedText style={[styles.watchBannerSubtitle, { color: colors.icon }]}>
                  اضغط هنا لفتح دليل خطوات ربط ومزامنة ساعتك بدقة
                </ThemedText>
              </View>
              <Ionicons name="chevron-back" size={18} color="#0284C7" />
            </TouchableOpacity>

            {/* Smart Auto-Detect Callout */}
            <View
              style={[
                styles.calloutCard,
                {
                  backgroundColor: isDark ? '#064E3B40' : '#ECFDF5',
                  borderColor: isDark ? '#05966960' : '#A7F3D0',
                },
              ]}>
              <Ionicons name="sparkles" size={18} color="#059669" style={styles.calloutIcon} />
              <ThemedText style={[styles.calloutText, { color: isDark ? '#D1FAE5' : '#065F46' }]}>
                كشف تلقائي فوري: بمجرد تفعيل الإذن والرجوع إلى هنا، سيتعرف التطبيق على الصلاحية تلقائياً دون الحاجة لإعادة تشغيل أو إغلاق التطبيق.
              </ThemedText>
            </View>
          </ScrollView>

          {/* Footer Actions */}
          <View style={styles.footer}>
            <TouchableOpacity
              activeOpacity={0.85}
              onPress={handleOpenSettings}
              style={[styles.primaryActionBtn, { backgroundColor: '#16A34A' }]}>
              <Ionicons name="open-outline" size={19} color="#FFFFFF" />
              <ThemedText style={styles.primaryActionBtnText}>
                {isIos
                  ? 'فتح تطبيق صحتي الآن'
                  : isHcAvailable === false
                  ? 'تثبيت تطبيق Health Connect من Google Play'
                  : 'فتح Health Connect الآن'}
              </ThemedText>
            </TouchableOpacity>

            <TouchableOpacity
              activeOpacity={0.7}
              onPress={onClose}
              style={styles.secondaryActionBtn}>
              <ThemedText style={[styles.secondaryActionBtnText, { color: colors.icon }]}>
                سأقوم بذلك لاحقاً (المتابعة كمتفرج)
              </ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 18,
  },
  modalContainer: {
    width: '100%',
    maxWidth: 440,
    maxHeight: '90%',
    borderRadius: 24,
    borderWidth: 1,
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 20,
    elevation: 8,
  },
  header: {
    alignItems: 'center',
    marginBottom: 16,
    position: 'relative',
  },
  closeButton: {
    position: 'absolute',
    left: 0,
    top: 0,
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 2,
  },
  headerIconBadge: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#DCFCE7',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
    marginTop: 4,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    textAlign: 'center',
    marginBottom: 4,
  },
  subtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    textAlign: 'center',
    lineHeight: 18,
    paddingHorizontal: 12,
  },
  scrollView: {
    maxHeight: 330,
  },
  scrollList: {
    gap: 10,
    paddingVertical: 4,
  },
  stepCard: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
  },
  stepNumberBadge: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: '#16A34A',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 2,
  },
  stepNumberText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  stepContent: {
    flex: 1,
    gap: 3,
  },
  stepTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 6,
  },
  stepTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    flex: 1,
    textAlign: 'right',
  },
  tagBadge: {
    backgroundColor: '#FEF3C7',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
  },
  tagBadgeText: {
    color: '#B45309',
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
  },
  stepDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    lineHeight: 17,
    textAlign: 'right',
  },
  watchBannerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
    marginTop: 4,
  },
  watchBannerTitle: {
    fontSize: 13,
    fontWeight: '700',
  },
  watchBannerSubtitle: {
    fontSize: 11.5,
    marginTop: 2,
  },
  calloutCard: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
    marginTop: 4,
  },
  calloutIcon: {
    marginTop: 2,
  },
  calloutText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    lineHeight: 18,
    textAlign: 'right',
  },
  footer: {
    marginTop: 16,
    gap: 8,
  },
  primaryActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 13,
    borderRadius: 16,
    minHeight: 48,
  },
  primaryActionBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  secondaryActionBtn: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 8,
  },
  secondaryActionBtnText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
});
