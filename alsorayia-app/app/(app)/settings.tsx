import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { setThemePreference } from '@/lib/themePreference';
import { ReviewService } from '@/lib/review/reviewService';
import Constants from 'expo-constants';
import { useRouter } from 'expo-router';
import { CustomAlert } from '@/components/ui/AlertModal';
import React, { useState } from 'react';
import { Platform, ScrollView, StyleSheet, Switch, TouchableOpacity, View } from 'react-native';



import * as SecureStore from 'expo-secure-store';

import { useAuth } from '@/contexts/auth-context';

// يتم جلب إصدار التطبيق ديناميكياً من package.json عبر app.config.js
const APP_VERSION = Constants.expoConfig?.version ?? '1.0.1';

interface SectionTitleProps {
  title: string;
}

function SectionTitle({ title }: SectionTitleProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  return <ThemedText style={[styles.sectionTitle, { color: colors.primary }]}>{title}</ThemedText>;
}

interface SettingsRowProps {
  icon: any;
  title: string;
  subtitle?: string | null;
  value?: string;
  type?: 'toggle' | 'value' | 'link';
  onPress?: () => void;
  toggleValue?: boolean;
  onToggle?: (val: boolean) => void;
  last?: boolean;
}

function SettingsRow({ icon, title, subtitle, value, type, onPress, toggleValue, onToggle, last }: SettingsRowProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];

  return (
    <TouchableOpacity
      activeOpacity={type === 'toggle' ? 1 : 0.7}
      onPress={type === 'toggle' ? undefined : onPress}
      style={[styles.row, { borderBottomColor: colors.border, borderBottomWidth: last ? 0 : StyleSheet.hairlineWidth }]}>
      <View style={[styles.iconContainer, { backgroundColor: colorScheme === 'dark' ? '#374151' : '#F1F5F9' }]}>
        <IconSymbol name={icon} size={20} color={colors.text} />
      </View>
      <View style={styles.rowTextContainer}>
        <ThemedText style={styles.rowTitle}>{title}</ThemedText>
        {subtitle != null && typeof subtitle === 'string' && subtitle.length > 0 ? (
          <ThemedText style={[styles.rowSubtitle, { color: colors.icon }]}>{subtitle}</ThemedText>
        ) : null}
      </View>

      <View style={styles.actionContainer}>
        {type === 'toggle' ? (
          <Switch
            value={toggleValue}
            onValueChange={onToggle}
            trackColor={{ false: '#CBD5E1', true: colors.primary }}
            thumbColor={Platform.OS === 'ios' ? '#FFFFFF' : toggleValue ? '#FFFFFF' : '#F8FAFC'}
          />
        ) : type === 'value' ? (
          <View style={styles.valueContainer}>
            <ThemedText style={[styles.valueText, { color: colors.icon }]}>{value}</ThemedText>
            <IconSymbol name="chevron.left" size={16} color={colors.icon} />
          </View>
        ) : (
          <IconSymbol name="chevron.left" size={16} color={colors.icon} />
        )}
      </View>
    </TouchableOpacity>
  );
}

export default function SettingsScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const { hasIdentity, lock } = useAuth();

  const [notificationsEnabled, setNotificationsEnabled] = useState(true);

  React.useEffect(() => {
    SecureStore.getItemAsync('notifications_enabled').then((val) => {
      if (val !== null) {
        setNotificationsEnabled(val === 'true');
      }
    });
  }, []);

  const handleToggleNotifications = async (val: boolean) => {
    setNotificationsEnabled(val);
    await SecureStore.setItemAsync('notifications_enabled', val ? 'true' : 'false');
    CustomAlert.alert('الإشعارات', val ? 'تم تفعيل إشعارات التطبيق بنجاح.' : 'تم إيقاف إشعارات التطبيق.');
  };

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={4} minSize={50} maxSize={150} />
      
      <View style={{ paddingTop: Platform.OS === 'ios' ? 40 : 10, paddingHorizontal: 16 }}>
        <HeaderBar
          title="إعدادات التطبيق"
          showBack={true}
          onBackPress={() => router.back()}
        />
      </View>

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
        
        <View style={styles.section}>
          <SectionTitle title="التفضيلات والمظهر" />
          <View style={[styles.card, { backgroundColor: colorScheme === 'dark' ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
            <SettingsRow 
              icon="moon.stars.fill" 
              title="الوضع الليلي" 
              subtitle="تفعيل الواجهة الداكنة للتطبيق" 
              type="toggle" 
              toggleValue={colorScheme === 'dark'} 
              onToggle={(val: boolean) => {
                void setThemePreference(val ? 'dark' : 'light');
              }} 
            />
            <SettingsRow 
              icon="doc.fill" 
              title="لغة التطبيق" 
              value="العربية" 
              type="value" 
              last 
              onPress={() => CustomAlert.alert('لغة التطبيق', 'دعم اللغات الإضافية سيكون متاحاً قريباً')} 
            />
          </View>
        </View>

        <View style={styles.section}>
          <SectionTitle title="الإشعارات" />
          <View style={[styles.card, { backgroundColor: colorScheme === 'dark' ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
            <SettingsRow 
              icon="bell.fill" 
              title="الرسائل التنبيهية" 
              subtitle="تلقي التنبيهات والأخبار المتعلقة بالعائلة" 
              type="toggle" 
              toggleValue={notificationsEnabled} 
              onToggle={handleToggleNotifications} 
              last 
            />
          </View>
        </View>

        <View style={styles.section}>
          <SectionTitle title="الحساب" />
          <View style={[styles.card, { backgroundColor: colorScheme === 'dark' ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
            <SettingsRow
              icon="person.crop.circle.fill"
              title="حسابي"
              subtitle={hasIdentity ? 'تعديل بياناتك الشخصية وتواصل العائلة' : 'يتطلب توثيق الهوية بالوجه أو واتساب'}
              last
              onPress={() => {
                if (hasIdentity) {
                  router.push('/(app)/my-profile');
                } else {
                  CustomAlert.alert(
                    'توثيق الهوية',
                    'للوصول إلى حسابك، يلزم تسجيل الدخول ببصمة الوجه أو واتساب.',
                    [
                      { text: 'إلغاء', style: 'cancel' },
                      { text: 'توثيق الآن', onPress: () => void lock() },
                    ]
                  );
                }
              }}
            />
          </View>
        </View>

        <View style={styles.section}>
          <SectionTitle title="حول التطبيق" />
          <View style={[styles.card, { backgroundColor: colorScheme === 'dark' ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
            <SettingsRow icon="questionmark.circle.fill" title="مركز المساعدة والدعم" onPress={() => router.push('/(app)/help')} />
            <SettingsRow icon="info.circle.fill" title="الشروط والأحكام" onPress={() => router.push('/(app)/terms')} />
            <SettingsRow icon="doc.fill" title="سياسة الخصوصية" onPress={() => router.push('/(app)/privacy')} />
            <SettingsRow
              icon="star.fill"
              title="قيّمنا على المتجر"
              last
              onPress={async () => {
                const opened = await ReviewService.openStorePageManually();
                if (!opened) {
                  CustomAlert.alert('تعذّر الفتح', 'تعذّر فتح صفحة التقييم في المتجر.');
                }
              }}
            />
          </View>
        </View>


        <ThemedText style={[styles.versionText, { color: colors.icon }]}>
          تطبيق عائلة السريّع - الإصدار {APP_VERSION}
        </ThemedText>
      </ScrollView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: Platform.OS === 'ios' ? 50 : 20,
    paddingBottom: 15,
    borderBottomWidth: 1,
    zIndex: 10,
  },
  backButton: { width: 40, height: 40, justifyContent: 'center', alignItems: 'flex-start' },
  headerTitle: { fontFamily: 'Tajawal-Bold', fontSize: 18 },
  scrollContent: { padding: 20, paddingBottom: 60 },
  section: { marginBottom: 24 },
  sectionTitle: { fontFamily: 'Tajawal-Bold', fontSize: 15, marginBottom: 12, marginRight: 8 },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: { shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 8 },
      android: { elevation: 2 },
    }),
  },
  row: { flexDirection: 'row', alignItems: 'center', padding: 16, gap: 12 },
  iconContainer: { width: 36, height: 36, borderRadius: 10, justifyContent: 'center', alignItems: 'center' },
  rowTextContainer: { flex: 1, justifyContent: 'center' },
  rowTitle: { fontFamily: 'Tajawal-Medium', fontSize: 15, marginBottom: 2 },
  rowSubtitle: { fontFamily: 'Tajawal-Regular', fontSize: 12, marginTop: 2 },
  actionContainer: { alignItems: 'flex-end', justifyContent: 'center', minWidth: 40 },
  valueContainer: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  valueText: { fontFamily: 'Tajawal-Regular', fontSize: 14 },
  versionText: { fontFamily: 'Tajawal-Regular', fontSize: 12, textAlign: 'center', marginTop: 10 },
});
