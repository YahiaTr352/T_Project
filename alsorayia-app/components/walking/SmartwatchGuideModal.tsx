import { ThemedText } from '@/components/themed-text';
import { Colors } from '@/constants/theme';
import { getHealthProvider } from '@/lib/walking/health';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import React, { useState } from 'react';
import {
  I18nManager,
  Linking,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  TextStyle,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

interface Props {
  visible: boolean;
  onClose: () => void;
}

type WatchBrand = 'samsung' | 'pixel' | 'xiaomi' | 'huawei' | 'garmin' | 'apple';

interface GuideStep {
  number: number;
  title: string;
  description: string;
  enTerms?: string[];
  badge?: string;
}

interface BrandGuide {
  id: WatchBrand;
  arabicName: string;
  englishSub: string;
  icon: string;
  companionArabic: string;
  companionEn: string;
  playStoreUrl?: string;
  steps: GuideStep[];
}

const BRAND_GUIDES: BrandGuide[] = [
  {
    id: 'samsung',
    arabicName: 'سامسونج',
    englishSub: 'Galaxy Watch',
    icon: 'watch-outline',
    companionArabic: 'تطبيق صحة سامسونج',
    companionEn: 'Samsung Health',
    playStoreUrl: 'https://play.google.com/store/apps/details?id=com.sec.android.app.shealth',
    steps: [
      {
        number: 1,
        title: 'مزامنة الساعة مع الهاتف',
        description: 'افتح تطبيق سامسونج وتأكد من اتصال ساعتك بالبلوتوث لتنتقل خطواتك الأخيرة إلى الهاتف أولاً.',
        enTerms: ['Samsung Health'],
      },
      {
        number: 2,
        title: 'الدخول إلى إعدادات المشاركة',
        description: 'من داخل التطبيق اضغط على القائمة الجانبية (⋮) ثم الإعدادات، واختر خدمة ربط الصحة.',
        enTerms: ['Settings', 'Health Connect'],
      },
      {
        number: 3,
        title: 'تفعيل إذن قراءة الخطوات',
        description: 'فعّل خيار السماح بمشاركة الخطوات لتصل إلى تطبيق العائلة تلقائياً.',
        enTerms: ['Allow Steps', 'Allow all'],
        badge: 'خطوة لمرة واحدة',
      },
      {
        number: 4,
        title: 'التحديث في تطبيق العائلة',
        description: 'عُد لتطبيق العائلة واضغط على زر «مزامنة الآن» وستظهر خطواتك في لوحة الشرف فوراً.',
      },
    ],
  },
  {
    id: 'pixel',
    arabicName: 'بيكسل وجوجل',
    englishSub: 'Pixel & Wear OS',
    icon: 'hardware-chip-outline',
    companionArabic: 'تطبيق فيتبيت أو جوجل فيت',
    companionEn: 'Fitbit / Google Fit',
    playStoreUrl: 'https://play.google.com/store/apps/details?id=com.fitbit.FitbitMobile',
    steps: [
      {
        number: 1,
        title: 'فتح التطبيق المصاحب',
        description: 'افتح تطبيق فيتبيت أو جوجل فيت المتصل بساعتك الذكية على الهاتف.',
        enTerms: ['Fitbit', 'Google Fit'],
      },
      {
        number: 2,
        title: 'ربط المنصة الموحدة',
        description: 'من إعدادات الملف الشخصي، اختر خيار الربط ومشاركة البيانات مع منصة الصحة.',
        enTerms: ['Connected Apps', 'Health Connect'],
      },
      {
        number: 3,
        title: 'منح صلاحية الخطوات',
        description: 'اسمح بمزامنة قراءة وكتابة الخطوات ليتم احتسابها تلقائياً على مدار اليوم.',
        enTerms: ['Steps Permission'],
        badge: 'مرة واحدة فقط',
      },
      {
        number: 4,
        title: 'استعراض الإنجاز',
        description: 'ستصل خطوات ساعتك إلى تحديات العائلة ولوحة المتصدرين بسلاسة تامة.',
      },
    ],
  },
  {
    id: 'xiaomi',
    arabicName: 'شاومي وريدمي',
    englishSub: 'Xiaomi & Mi Band',
    icon: 'fitness-outline',
    companionArabic: 'تطبيق مي فيتنس',
    companionEn: 'Mi Fitness',
    playStoreUrl: 'https://play.google.com/store/apps/details?id=com.xiaomi.wearable',
    steps: [
      {
        number: 1,
        title: 'مزامنة السوار أو الساعة',
        description: 'افتح تطبيق شاومي وتأكد من اكتمال سحب خطوات سوارك الذكي لليوم.',
        enTerms: ['Mi Fitness'],
      },
      {
        number: 2,
        title: 'خيارات مشاركة البيانات',
        description: 'ادخل إلى تبويب «الملف الشخصي» ثم اختر «البيانات المشتركة».',
        enTerms: ['Profile', 'Connected Apps'],
      },
      {
        number: 3,
        title: 'ربط منصة الصحة',
        description: 'اختر منصة الصحة واضغط على «ربط» وفعّل خيار الخطوات.',
        enTerms: ['Health Connect', 'Steps'],
        badge: 'ربط تلقائي',
      },
      {
        number: 4,
        title: 'المزامنة مع تطبيق العائلة',
        description: 'اضغط على مزامنة الآن داخل تطبيق العائلة لتنعكس خطواتك الحقيقية.',
      },
    ],
  },
  {
    id: 'huawei',
    arabicName: 'هواوي وهونر',
    englishSub: 'Huawei & Honor',
    icon: 'time-outline',
    companionArabic: 'هواوي صحة + تطبيق المزامنة',
    companionEn: 'Huawei Health + Health Sync',
    playStoreUrl: 'https://play.google.com/store/apps/details?id=nl.appyhapps.healthsync',
    steps: [
      {
        number: 1,
        title: 'طبيعة الربط لساعات هواوي',
        description: 'ساعات هواوي لا تدعم الربط المباشر مع المنصة، لذا نستخدم تطبيقاً مساعداً معتمداً ومجانياً لنقل الخطوات.',
      },
      {
        number: 2,
        title: 'تثبيت التطبيق المساعد',
        description: 'ثبّت تطبيق هيلث سينك من متجر جوجل بلاي على هاتفك.',
        enTerms: ['Health Sync'],
        badge: 'تطبيق مجاني معتمد',
      },
      {
        number: 3,
        title: 'تحديد مسار نقل الخطوات',
        description: 'في الإعداد الأول، اختر المصدر تطبيق هواوي، والوجهة هي منصة الصحة الموحدة.',
        enTerms: ['Huawei Health', 'Health Connect'],
      },
      {
        number: 4,
        title: 'مزامنة تلقائية مستمرة',
        description: 'ستنتقل خطوات ساعتك إلى تطبيق العائلة تلقائياً دون أي مجهود إضافي.',
      },
    ],
  },
  {
    id: 'garmin',
    arabicName: 'جارمن',
    englishSub: 'Garmin Watch',
    icon: 'compass-outline',
    companionArabic: 'تطبيق جارمن كونكت',
    companionEn: 'Garmin Connect',
    playStoreUrl: 'https://play.google.com/store/apps/details?id=com.garmin.android.apps.connectmobile',
    steps: [
      {
        number: 1,
        title: 'مزامنة الساعة',
        description: 'افتح تطبيق جارمن كونكت على الهاتف لمزامنة بيانات الساعة الأخيرة.',
        enTerms: ['Garmin Connect'],
      },
      {
        number: 2,
        title: 'التطبيقات المتصلة',
        description: 'من القائمة الجانبية ادخل إلى الإعدادات ثم التطبيقات المتصلة.',
        enTerms: ['Settings', 'Connected Apps'],
      },
      {
        number: 3,
        title: 'تفعيل الربط',
        description: 'اختر منصة الصحة الموحدة واضغط على موافقة لتمرير خطواتك اليومية.',
        enTerms: ['Health Connect'],
      },
      {
        number: 4,
        title: 'جاهزية التحديات',
        description: 'تظهر خطوات ساعتك مباشرة في إحصائياتك وتحديات العائلة.',
      },
    ],
  },
  {
    id: 'apple',
    arabicName: 'ساعات آبل',
    englishSub: 'Apple Watch',
    icon: 'logo-apple',
    companionArabic: 'تطبيق صحتي المدمج',
    companionEn: 'Apple Health',
    steps: [
      {
        number: 1,
        title: 'مزامنة تلقائية مدمجة',
        description: 'ساعات آبل ترتبط تلقائياً بتطبيق «صحتي» على جهاز الآيفون دون الحاجة لأي إعداد إضافي.',
        enTerms: ['Apple Health'],
      },
      {
        number: 2,
        title: 'صلاحية تطبيق العائلة',
        description: 'تأكد فقط من منح تطبيق «صلة العائلة» إذن قراءة الخطوات عند طلبه.',
        badge: 'تأكيد الإذن',
      },
      {
        number: 3,
        title: 'دقة تامة وفورية',
        description: 'تُحسب خطوات ساعتك فورياً بدقة 100% وتظهر في التحديات ولوحة الشرف.',
      },
    ],
  },
];

const isRTL = I18nManager.isRTL;
const rtlRow = isRTL ? 'row' : 'row-reverse';
const rtlTextAlign: TextStyle['textAlign'] = isRTL ? undefined : 'right';

export function SmartwatchGuideModal({ visible, onClose }: Props) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  const defaultBrand: WatchBrand = Platform.OS === 'ios' ? 'apple' : 'samsung';
  const [selectedBrand, setSelectedBrand] = useState<WatchBrand>(defaultBrand);

  const activeGuide =
    BRAND_GUIDES.find((g) => g.id === selectedBrand) ?? BRAND_GUIDES[0];

  const handleBrandSelect = (brand: WatchBrand) => {
    try {
      Haptics.selectionAsync();
    } catch {}
    setSelectedBrand(brand);
  };

  const handleOpenHealthConnect = async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}
    const provider = getHealthProvider();
    if (provider) {
      try {
        await provider.openSettings();
        return;
      } catch {}
    }
    if (Platform.OS === 'android') {
      await Linking.openURL(
        'https://play.google.com/store/apps/details?id=com.google.android.apps.healthdata'
      ).catch(() => Linking.openSettings().catch(() => {}));
    } else {
      await Linking.openURL('x-apple-health://').catch(() => Linking.openSettings().catch(() => {}));
    }
  };

  const handleOpenCompanionApp = async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}
    if (activeGuide.playStoreUrl) {
      await Linking.openURL(activeGuide.playStoreUrl).catch(() => {});
    }
  };

  return (
    <Modal visible={visible} animationType="fade" transparent onRequestClose={onClose}>
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
              <Ionicons name="watch" size={26} color="#0284C7" />
            </View>

            <ThemedText style={styles.title}>دليل ربط الساعات الذكية</ThemedText>
            <ThemedText style={[styles.subtitle, { color: colors.icon }]}>
              اختر نوع ساعتك لمعرفة خطوات مزامنتها مع التطبيق بدقة
            </ThemedText>
          </View>

          {/* Horizontal Brand Selector Chips (Arabic-first) */}
          <View style={styles.chipsWrapper}>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.chipsScroll}>
              {BRAND_GUIDES.map((brand) => {
                const isSelected = brand.id === selectedBrand;
                return (
                  <TouchableOpacity
                    key={brand.id}
                    onPress={() => handleBrandSelect(brand.id)}
                    activeOpacity={0.8}
                    style={[
                      styles.chipItem,
                      isSelected && styles.chipItemSelected,
                      {
                        backgroundColor: isSelected
                          ? '#0284C7'
                          : isDark
                          ? '#27272A'
                          : '#F3F4F6',
                        borderColor: isSelected
                          ? '#0284C7'
                          : isDark
                          ? '#3F3F46'
                          : '#E5E7EB',
                      },
                    ]}>
                    <Ionicons
                      name={brand.icon as any}
                      size={15}
                      color={isSelected ? '#FFFFFF' : colors.text}
                      style={styles.chipIcon}
                    />
                    <ThemedText
                      style={[
                        styles.chipText,
                        isSelected && styles.chipTextSelected,
                        { color: isSelected ? '#FFFFFF' : colors.text },
                      ]}>
                      {brand.arabicName}
                    </ThemedText>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          </View>

          {/* Main Scroll Content */}
          <ScrollView
            showsVerticalScrollIndicator={false}
            contentContainerStyle={styles.scrollList}
            style={styles.scrollView}>
            {/* Active Brand Header Box */}
            <View
              style={[
                styles.brandCardHeader,
                {
                  backgroundColor: isDark ? '#0F2338' : '#F0F9FF',
                  borderColor: isDark ? '#1E3A5F' : '#BAE6FD',
                },
              ]}>
              <View style={styles.brandTitleRow}>
                <Ionicons name={activeGuide.icon as any} size={22} color="#0284C7" />
                <View style={styles.brandTitleTextWrap}>
                  <ThemedText style={styles.brandFullTitle}>{activeGuide.arabicName}</ThemedText>
                  <ThemedText style={styles.brandEnglishSub}>{activeGuide.englishSub}</ThemedText>
                </View>
              </View>

              <View style={styles.companionRow}>
                <ThemedText style={[styles.companionLabel, { color: colors.icon }]}>
                  التطبيق المصاحب للساعة:
                </ThemedText>
                <View style={styles.companionBadge}>
                  <ThemedText style={styles.companionBadgeAr}>{activeGuide.companionArabic}</ThemedText>
                  <ThemedText style={styles.companionBadgeEn}>({activeGuide.companionEn})</ThemedText>
                </View>
              </View>
            </View>

            {/* Steps List */}
            {activeGuide.steps.map((s) => (
              <View
                key={s.number}
                style={[
                  styles.stepCard,
                  {
                    backgroundColor: isDark ? '#27272A80' : '#F9FAFB',
                    borderColor: isDark ? '#3F3F46' : '#E5E7EB',
                  },
                ]}>
                {/* Step Number Circle */}
                <View style={styles.stepNumberBadge}>
                  <ThemedText style={styles.stepNumberText}>{s.number}</ThemedText>
                </View>

                {/* Step Text Content */}
                <View style={styles.stepContent}>
                  <View style={styles.stepHeaderRow}>
                    <ThemedText style={styles.stepTitle}>{s.title}</ThemedText>
                    {s.badge && (
                      <View style={styles.tagBadge}>
                        <ThemedText style={styles.tagBadgeText}>{s.badge}</ThemedText>
                      </View>
                    )}
                  </View>

                  <ThemedText style={[styles.stepDesc, { color: colors.text }]}>
                    {s.description}
                  </ThemedText>

                  {/* English technical terms in dedicated, clean pill badges */}
                  {s.enTerms && s.enTerms.length > 0 && (
                    <View style={styles.enTermsRow}>
                      {s.enTerms.map((term, idx) => (
                        <View
                          key={idx}
                          style={[
                            styles.enTermPill,
                            {
                              backgroundColor: isDark ? '#1E293B' : '#E2E8F0',
                              borderColor: isDark ? '#334155' : '#CBD5E1',
                            },
                          ]}>
                          <ThemedText style={[styles.enTermPillText, { color: isDark ? '#93C5FD' : '#1E3A8A' }]}>
                            {term}
                          </ThemedText>
                        </View>
                      ))}
                    </View>
                  )}
                </View>
              </View>
            ))}

            {/* Golden Rule Tip Card */}
            <View
              style={[
                styles.goldenTipCard,
                {
                  backgroundColor: isDark ? '#261C08' : '#FEFCE8',
                  borderColor: isDark ? '#785412' : '#FEF08A',
                },
              ]}>
              <Ionicons name="bulb-outline" size={20} color="#D97706" style={styles.tipIcon} />
              <View style={styles.tipContent}>
                <ThemedText style={[styles.goldenTipTitle, { color: isDark ? '#FDE68A' : '#92400E' }]}>
                  نصيحة ذهبية لضمان دقة الخطوات:
                </ThemedText>
                <ThemedText style={[styles.goldenTipText, { color: isDark ? '#FEF08A' : '#854D0E' }]}>
                  الساعات الذكية لا تبث خطواتها بالإنترنت مباشرة، بل تنقلها عبر البلوتوث لتطبيق ساعتك أولاً. إذا أنهيت المشي ولم تظهر خطواتك فوراً، فقط افتح تطبيق ساعتك لثوانٍ ثم عُد لتطبيق العائلة واضغط «مزامنة الآن»!
                </ThemedText>
              </View>
            </View>
          </ScrollView>

          {/* Footer Actions */}
          <View style={styles.footer}>
            {Platform.OS === 'android' && (
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={handleOpenHealthConnect}
                style={[styles.primaryActionBtn, { backgroundColor: '#0284C7' }]}>
                <Ionicons name="settings-outline" size={18} color="#FFFFFF" />
                <ThemedText style={styles.primaryActionBtnText}>
                  فتح إعدادات Health Connect
                </ThemedText>
              </TouchableOpacity>
            )}

            {activeGuide.playStoreUrl && (
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={handleOpenCompanionApp}
                style={[
                  styles.secondaryAppBtn,
                  {
                    backgroundColor: isDark ? '#27272A' : '#F3F4F6',
                    borderColor: isDark ? '#3F3F46' : '#E5E7EB',
                  },
                ]}>
                <Ionicons name="download-outline" size={17} color={colors.text} />
                <ThemedText style={[styles.secondaryAppBtnText, { color: colors.text }]}>
                  تثبيت / فتح {activeGuide.companionArabic}
                </ThemedText>
              </TouchableOpacity>
            )}

            <TouchableOpacity activeOpacity={0.7} onPress={onClose} style={styles.closeTextBtn}>
              <ThemedText style={[styles.closeTextBtnText, { color: colors.icon }]}>
                تم، فهمت الطريقة
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
    backgroundColor: 'rgba(0, 0, 0, 0.72)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 16,
  },
  modalContainer: {
    width: '100%',
    maxWidth: 460,
    maxHeight: '92%',
    borderRadius: 24,
    borderWidth: 1,
    paddingHorizontal: 18,
    paddingTop: 18,
    paddingBottom: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 20,
    elevation: 8,
  },
  header: {
    alignItems: 'center',
    marginBottom: 10,
    position: 'relative',
  },
  closeButton: {
    position: 'absolute',
    left: 0,
    top: 0,
    width: 32,
    height: 32,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 10,
  },
  headerIconBadge: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: '#E0F2FE',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    lineHeight: 26,
    textAlign: 'center',
    marginBottom: 4,
  },
  subtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 22,
    textAlign: 'center',
    paddingHorizontal: 10,
  },
  chipsWrapper: {
    marginVertical: 10,
  },
  chipsScroll: {
    paddingHorizontal: 2,
    gap: 8,
    flexDirection: rtlRow,
  },
  chipItem: {
    flexDirection: rtlRow,
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 20,
    borderWidth: 1,
    gap: 6,
  },
  chipItemSelected: {
    borderColor: '#0284C7',
  },
  chipIcon: {
    marginHorizontal: 1,
  },
  chipText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
    lineHeight: 18,
    textAlign: rtlTextAlign,
  },
  chipTextSelected: {
    fontFamily: 'Tajawal-Bold',
  },
  scrollView: {
    maxHeight: 330,
  },
  scrollList: {
    paddingVertical: 6,
    gap: 10,
  },
  brandCardHeader: {
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
    marginBottom: 2,
  },
  brandTitleRow: {
    flexDirection: rtlRow,
    alignItems: 'center',
    gap: 10,
    marginBottom: 6,
  },
  brandTitleTextWrap: {
    flex: 1,
  },
  brandFullTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    lineHeight: 23,
    color: '#0284C7',
    textAlign: rtlTextAlign,
  },
  brandEnglishSub: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
    lineHeight: 17,
    color: '#64748B',
    textAlign: rtlTextAlign,
    marginTop: 1,
  },
  companionRow: {
    flexDirection: 'column',
    gap: 4,
    marginTop: 4,
  },
  companionLabel: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    lineHeight: 18,
    textAlign: rtlTextAlign,
  },
  companionBadge: {
    flexDirection: rtlRow,
    alignItems: 'center',
    gap: 6,
    flexWrap: 'wrap',
    justifyContent: isRTL ? 'flex-start' : 'flex-end',
  },
  companionBadgeAr: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 12.5,
    lineHeight: 19,
    color: '#0284C7',
    textAlign: rtlTextAlign,
  },
  companionBadgeEn: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 11.5,
    lineHeight: 17,
    color: '#64748B',
  },
  stepCard: {
    flexDirection: rtlRow,
    alignItems: 'flex-start',
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
    gap: 12,
  },
  stepNumberBadge: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: '#0284C7',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 1,
  },
  stepNumberText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    lineHeight: 18,
    textAlign: 'center',
  },
  stepContent: {
    flex: 1,
    gap: 3,
  },
  stepHeaderRow: {
    flexDirection: rtlRow,
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
    marginBottom: 2,
  },
  stepTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
    lineHeight: 22,
    flex: 1,
    textAlign: rtlTextAlign,
  },
  tagBadge: {
    backgroundColor: '#E0F2FE',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
  tagBadgeText: {
    color: '#0369A1',
    fontFamily: 'Tajawal-Bold',
    fontSize: 10.5,
    lineHeight: 15,
  },
  stepDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 22,
    textAlign: rtlTextAlign,
  },
  enTermsRow: {
    flexDirection: rtlRow,
    flexWrap: 'wrap',
    gap: 6,
    marginTop: 6,
    justifyContent: isRTL ? 'flex-start' : 'flex-end',
  },
  enTermPill: {
    paddingHorizontal: 9,
    paddingVertical: 3,
    borderRadius: 8,
    borderWidth: 1,
  },
  enTermPillText: {
    fontSize: 11.5,
    lineHeight: 16,
    fontFamily: 'Tajawal-Bold',
  },
  goldenTipCard: {
    flexDirection: rtlRow,
    alignItems: 'flex-start',
    padding: 12,
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
    marginTop: 6,
  },
  tipIcon: {
    marginTop: 2,
  },
  tipContent: {
    flex: 1,
    gap: 3,
  },
  goldenTipTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    lineHeight: 20,
    textAlign: rtlTextAlign,
  },
  goldenTipText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 12,
    lineHeight: 20,
    textAlign: rtlTextAlign,
  },
  footer: {
    marginTop: 12,
    gap: 8,
  },
  primaryActionBtn: {
    flexDirection: rtlRow,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 14,
    gap: 8,
    minHeight: 48,
  },
  primaryActionBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
    lineHeight: 22,
  },
  secondaryAppBtn: {
    flexDirection: rtlRow,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 11,
    borderRadius: 14,
    borderWidth: 1,
    gap: 8,
    minHeight: 44,
  },
  secondaryAppBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    lineHeight: 20,
  },
  closeTextBtn: {
    alignItems: 'center',
    paddingVertical: 8,
  },
  closeTextBtnText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    lineHeight: 20,
  },
});
