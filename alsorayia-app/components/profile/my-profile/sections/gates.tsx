import HeaderBar from '@/components/headerBar';
import { ThemedView } from '@/components/themed-view';
import { Colors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { Platform, Text, TouchableOpacity, View } from 'react-native';
import { styles } from '../styles';

export function AuthGate({ lock }: { lock: () => unknown }) {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
      <ThemedView style={styles.container}>
        <View style={{ paddingTop: Platform.OS === 'ios' ? 40 : 10, paddingHorizontal: 16 }}>
          <HeaderBar title="حسابي الشخصي" showBack onBackPress={() => router.back()} />
        </View>
        <View style={styles.centered}>
          <LinearGradient
            colors={isDark ? ['#1E293B', '#0F172A'] : ['#ECFDF5', '#D1FAE5']}
            style={styles.gateIconWrap}>
            <Ionicons name="shield-checkmark" size={48} color={colors.primary} />
          </LinearGradient>
          <Text style={[styles.gateTitle, { color: colors.text }]}>توثيق الهوية مطلوب</Text>
          <Text style={[styles.gateText, { color: colors.icon }]}>
            للوصول إلى ملفك الشخصي وتحديث بياناتك في شجرة العائلة، يرجى إكمال التوثيق عبر بصمة الوجه أو كود الواتساب.
          </Text>
          <TouchableOpacity style={[styles.primaryBtn, { backgroundColor: colors.primary }]} onPress={() => void lock()}>
            <Ionicons name="lock-open-outline" size={20} color="#FFF" />
            <Text style={styles.primaryBtnText}>توثيق الهوية الآن</Text>
          </TouchableOpacity>
        </View>
      </ThemedView>
  );
}

interface ProfileStatusGateProps {
  status: 'forbidden' | 'error';
  errorMessage: string | null;
  lock: () => unknown;
  reload: () => unknown;
}

export function ProfileStatusGate({ status, errorMessage, lock, reload }: ProfileStatusGateProps) {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  return (
        <View style={styles.centered}>
          <LinearGradient
            colors={isDark ? ['#1E293B', '#0F172A'] : ['#FEF2F2', '#FEE2E2']}
            style={styles.gateIconWrap}>
            <Ionicons name="alert-circle" size={48} color="#EF4444" />
          </LinearGradient>
          <Text style={[styles.gateTitle, { color: colors.text }]}>
            {status === 'forbidden' ? 'يلزم توثيق الهوية' : 'تعذّر جلب البيانات'}
          </Text>
          <Text style={[styles.gateText, { color: colors.icon }]}>{errorMessage}</Text>
          {status === 'forbidden' ? (
            <TouchableOpacity style={[styles.primaryBtn, { backgroundColor: colors.primary }]} onPress={() => void lock()}>
              <Text style={styles.primaryBtnText}>توثيق الهوية</Text>
            </TouchableOpacity>
          ) : (
            <TouchableOpacity style={[styles.primaryBtn, { backgroundColor: colors.primary }]} onPress={() => void reload()}>
              <Ionicons name="refresh" size={20} color="#FFF" />
              <Text style={styles.primaryBtnText}>إعادة المحاولة</Text>
            </TouchableOpacity>
          )}
        </View>
  );
}
