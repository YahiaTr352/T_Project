/** شاشة الدعم والمساعدة — تصميم محسّن v3 */

import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Colors } from '@/constants/theme';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { CustomAlert } from '@/components/ui/AlertModal';
import React, { useState } from 'react';
import {
  Linking,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  useColorScheme,
  View,
} from 'react-native';

const WHATSAPP_URL = 'https://wa.me/966539895800';

export default function HelpScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  const [activeFaq, setActiveFaq] = useState<number | null>(null);

  const handleWhatsApp = () => {
    Linking.openURL(WHATSAPP_URL).catch(() => {
      CustomAlert.alert('خطأ', 'تطبيق واتساب غير مثبت على جهازك.');
    });
  };

  const faqs = [
    {
      q: 'كيف يمكنني تعديل بياناتي الشخصية؟',
      a: 'يمكنك إرسال طلب تعديل البيانات أو الصورة الشخصية مباشرة عبر الواتساب أو البريد الإلكتروني مع إرفاق المستندات أو التفاصيل الجديدة وسيقوم المشرف بتحديثها فوراً.',
    },
    {
      q: 'كيف أضيف فرداً جديداً لشجرة العائلة؟',
      a: 'لتسجيل مولود جديد أو إضافة فرد، يرجى مشاركة (الاسم الكامل، اسم الأب، تاريخ الميلاد، وصورة شخصية إن وجدت) عبر قنوات الدعم الفني المتاحة بالأسفل.',
    },
    {
      q: 'هل بياناتي آمنة داخل التطبيق؟',
      a: 'نعم، جميع بيانات شجرة العائلة والتواصل مشفرة ومحمية بكلمة مرور خاصة بالدخول ولا تظهر لغير أفراد عائلة السريّع المعتمدين.',
    },
  ];

  return (
    <ThemedView style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={{ paddingTop: Platform.OS === 'ios' ? 40 : 10, paddingHorizontal: 16 }}>
        <HeaderBar title="الدعم والمساعدة" showBack onBackPress={() => router.back()} />
      </View>

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
        {/* Hero Banner */}
        <LinearGradient
          colors={isDark ? ['#1e293b', '#0f172a'] : [colors.primary + '15', colors.primary + '05']}
          style={[styles.heroCard, { borderColor: colors.border }]}
        >
          <View style={[styles.heroIconWrap, { backgroundColor: colors.primary + '18' }]}>
            <Ionicons name="chatbubbles-outline" size={32} color={colors.primary} />
          </View>
          <ThemedText style={styles.heroTitle}>كيف يمكننا مساعدتك اليوم؟</ThemedText>
          <ThemedText style={[styles.heroDesc, { color: colors.icon }]}>
            فريق الدعم الفني لعائلة السريّع يسعده الإجابة على استفساراتكم وحل مشكلاتكم التقنية على مدار الساعة.
          </ThemedText>
        </LinearGradient>

        {/* Quick Contact Buttons */}
        <ThemedText style={[styles.sectionTitle, { color: colors.primary }]}>قنوات الاتصال المباشر</ThemedText>

        <View style={styles.channelsContainer}>
          {/* Dedicated Ticket / Contact Form */}
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={() => router.push('/(app)/contact' as any)}
            style={[styles.channelBtn, { backgroundColor: colors.primary }]}
          >
            <Ionicons name="document-text-outline" size={24} color="#fff" />
            <View style={styles.channelInfo}>
              <Text style={styles.channelLabel}>صندوق الاقتراحات والملاحظات</Text>
              <Text style={styles.channelValue}>نموذج مخصص لإيصال أفكارك مع إمكانية إرفاق وسائط</Text>
            </View>
            <Ionicons name="chevron-back" size={18} color="#fff" />
          </TouchableOpacity>

          {/* WhatsApp Channel */}
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={handleWhatsApp}
            style={[styles.channelBtn, { backgroundColor: '#25D366' }]}
          >
            <Ionicons name="logo-whatsapp" size={24} color="#fff" />
            <View style={styles.channelInfo}>
              <Text style={styles.channelLabel}>محادثة واتساب مباشرة</Text>
              <Text style={styles.channelValue}>+966 53 989 5800</Text>
            </View>
            <Ionicons name="chevron-back" size={18} color="#fff" />
          </TouchableOpacity>

          {/* Call Channel */}
          {/* <TouchableOpacity
            activeOpacity={0.85}
            onPress={handleCall}
            style={[styles.channelBtn, { backgroundColor: colors.primary }]}
          >
            <Ionicons name="call" size={22} color="#fff" />
            <View style={styles.channelInfo}>
              <Text style={styles.channelLabel}>اتصال هاتفي مباشر</Text>
              <Text style={styles.channelValue}>اضغط لإجراء مكالمة</Text>
            </View>
            <Ionicons name="chevron-back" size={18} color="#fff" />
          </TouchableOpacity> */}

          {/* Email Channel */}
          {/* <TouchableOpacity
            activeOpacity={0.85}
            onPress={handleEmail}
            style={[styles.channelBtn, { backgroundColor: isDark ? '#334155' : '#475569' }]}
          >
            <Ionicons name="mail" size={22} color="#fff" />
            <View style={styles.channelInfo}>
              <Text style={styles.channelLabel}>الدعم عبر البريد الإلكتروني</Text>
              <Text style={styles.channelValue}>{CONTACT_EMAIL}</Text>
            </View>
            <Ionicons name="chevron-back" size={18} color="#fff" />
          </TouchableOpacity> */}
        </View>

        {/* FAQs Section */}
        <ThemedText style={[styles.sectionTitle, { color: colors.primary }]}>الأسئلة الشائعة</ThemedText>
        <View style={styles.faqContainer}>
          {faqs.map((faq, index) => {
            const isOpen = activeFaq === index;
            return (
              <View
                key={index}
                style={[
                  styles.faqItem,
                  {
                    backgroundColor: isDark ? '#1e293b' : '#fff',
                    borderColor: colors.border,
                  },
                ]}
              >
                <TouchableOpacity
                  activeOpacity={0.7}
                  onPress={() => setActiveFaq(isOpen ? null : index)}
                  style={styles.faqHeader}
                >
                  <Ionicons
                    name="help-circle-outline"
                    size={20}
                    color={colors.primary}
                    style={{ marginLeft: 6 }}
                  />
                  <Text style={[styles.faqQuestion, { color: colors.text }]}>{faq.q}</Text>
                  <Ionicons
                    name={isOpen ? 'chevron-up' : 'chevron-down'}
                    size={18}
                    color={colors.icon}
                  />
                </TouchableOpacity>
                {isOpen && (
                  <View style={[styles.faqBody, { borderTopColor: colors.border }]}>
                    <Text style={[styles.faqAnswer, { color: colors.icon }]}>{faq.a}</Text>
                  </View>
                )}
              </View>
            );
          })}
        </View>
      </ScrollView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  scrollContent: { padding: 18, paddingBottom: 60 },

  // Hero Card
  heroCard: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 20,
    alignItems: 'center',
    marginBottom: 24,
  },
  heroIconWrap: {
    width: 64,
    height: 64,
    borderRadius: 32,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
  },
  heroTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 18,
    textAlign: 'center',
    marginBottom: 8,
  },
  heroDesc: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    textAlign: 'center',
    lineHeight: 20,
  },

  // Titles
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
    marginBottom: 12,
    marginRight: 6,
  },

  // Channels
  channelsContainer: { gap: 12, marginBottom: 28 },
  channelBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderRadius: 16,
    gap: 12,
  },
  channelInfo: { flex: 1 },
  channelLabel: {
    color: '#fff',
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    textAlign: 'left',
  },
  channelValue: {
    color: 'rgba(255, 255, 255, 0.85)',
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    marginTop: 2,
    textAlign: 'left',
  },

  // FAQs
  faqContainer: { gap: 10 },
  faqItem: {
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
  },
  faqHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
  },
  faqQuestion: {
    flex: 1,
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
    textAlign: 'left',
  },
  faqBody: {
    padding: 16,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  faqAnswer: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 20,
    textAlign: 'left',
  },
});
