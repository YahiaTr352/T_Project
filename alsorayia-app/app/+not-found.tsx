import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { Colors, silaTheme } from '@/constants/theme';

/**
 * شاشة بديلة أنيقة تظهر عند عدم العثور على المسار المطلوب
 * بدل صفحة 404 الافتراضية في expo-router
 */
export default function NotFoundScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[isDark ? 'dark' : 'light'];

  return (
    <View
      style={[
        styles.container,
        {
          backgroundColor: isDark ? '#0B1512' : '#F4F8F6',
          paddingTop: insets.top + 40,
          paddingBottom: insets.bottom + 32,
        },
      ]}>
      <LinearGradient
        colors={['#37a05c', '#145147']}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.badge}>
        <Ionicons name="map-outline" size={40} color="#FFFFFF" />
      </LinearGradient>

      <ThemedText style={styles.title}>الصفحة غير موجودة</ThemedText>

      <ThemedText
        style={[
          styles.subtitle,
          { color: isDark ? '#94A3B8' : '#64748B' },
        ]}>
        يبدو أن الرابط الذي فتحته غير صحيح أو لم يعد متاحاً.
        يمكنك العودة إلى الصفحة الرئيسية ومتابعة ما بدأت.
      </ThemedText>

      <TouchableOpacity
        activeOpacity={0.85}
        onPress={() => router.replace('/(tabs)')}
        accessibilityRole="button"
        accessibilityLabel="العودة للصفحة الرئيسية"
        style={[
          styles.homeBtn,
          {
            backgroundColor: isDark ? '#16221E' : '#FFFFFF',
            borderColor: isDark ? '#233831' : '#E2EBE7',
          },
        ]}>
        <Ionicons
          name="home-outline"
          size={18}
          color={silaTheme.primary}
          style={{ marginHorizontal: 6 }}
        />
        <Text style={[styles.homeBtnText, { color: colors.text }]}>
          العودة للصفحة الرئيسية
        </Text>
      </TouchableOpacity>

      <TouchableOpacity
        activeOpacity={0.85}
        onPress={() => router.back()}
        accessibilityRole="button"
        accessibilityLabel="رجوع"
        style={styles.backBtn}>
        <Ionicons
          name="arrow-back"
          size={16}
          color={isDark ? '#94A3B8' : '#64748B'}
          style={{ marginHorizontal: 6 }}
        />
        <Text style={[styles.backBtnText, { color: isDark ? '#94A3B8' : '#64748B' }]}>
          رجوع
        </Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 28,
  },
  badge: {
    width: 92,
    height: 92,
    borderRadius: 46,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.18,
    shadowRadius: 14,
    elevation: 8,
  },
  title: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 22,
    textAlign: 'center',
    marginBottom: 10,
  },
  subtitle: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    textAlign: 'center',
    lineHeight: 24,
    marginBottom: 28,
  },
  homeBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    paddingHorizontal: 22,
    borderRadius: 16,
    borderWidth: 1,
    minWidth: 230,
  },
  homeBtnText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  backBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 18,
    marginTop: 10,
  },
  backBtnText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
  },
});
