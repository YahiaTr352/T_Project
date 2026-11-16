import '@/lib/utils/patchGlobalText';
import { LockScreen } from '@/components/lock-screen';
import { MaintenanceModal } from '@/components/MaintenanceModal';
import { UpdateModal } from '@/components/UpdateModal';
import { AlertProvider } from '@/components/ui/AlertModal';
import { useAppVersionGate } from '@/hooks/useAppVersionGate';
import { Colors } from '@/constants/theme';
import { AuthProvider, useAuth } from '@/contexts/auth-context';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { useGlobalWalkingSync } from '@/hooks/use-global-walking-sync';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { hydrateThemeFromStorage } from '@/lib/themePreference';
import { ReviewService } from '@/lib/review/reviewService';
import { useFonts } from 'expo-font';
import { DarkTheme, DefaultTheme, ThemeProvider, Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import * as SystemUI from 'expo-system-ui';
import * as Updates from 'expo-updates';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Appearance, I18nManager, View } from 'react-native';
import 'react-native-reanimated';
import { SafeAreaView } from 'react-native-safe-area-context';

// منع إخفاء شاشة البداية تلقائياً
SplashScreen.preventAutoHideAsync();

// فرض RTL لتخطيط التطبيق (flex، start/end) بغض النظر عن لغة الجهاز.
I18nManager.allowRTL(true);
const deviceLayoutWasLtr = !I18nManager.isRTL;
I18nManager.forceRTL(true);
// على أجهزة LTR غالبًا لازم إعادة تحميل الجلسة مرة واحدة بعد forceRTL حتى يتوافق العرض مع الـ native
if (deviceLayoutWasLtr && !__DEV__) {
  void Updates.reloadAsync().catch(() => {});
}

// ==================== محتوى التطبيق ====================

function AppContent() {
  const colorScheme = useColorScheme();
  const { isAuthenticated, isLoading } = useAuth();

  // تهيئة إشعارات الـ Push (حتى على شاشة القفل)
  usePushNotifications();

  // المزامنة العالمية الصامتة لخطوات المشي فور فتح التطبيق
  useGlobalWalkingSync();

  // تسجيل جلسة فتح التطبيق لخدمة التقييم الذكي
  useEffect(() => {
    if (isAuthenticated) {
      void ReviewService.recordAppLaunch();
    }
  }, [isAuthenticated]);

  // شاشة التحميل أثناء التحقق من حالة المصادقة
  if (isLoading) {
    const scheme = colorScheme ?? 'light';
    const loadBg = Colors[scheme].background;
    return (
      <View style={{ flex: 1, backgroundColor: loadBg, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color="#10B981" />
      </View>
    );
  }

  // إذا لم يتم التحقق، عرض شاشة القفل
  if (!isAuthenticated) {
    return <LockScreen />;
  }

  // إذا تم التحقق، عرض التطبيق
  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <Stack screenOptions={{ headerShown: false }} initialRouteName="(tabs)">
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="(app)" />
      </Stack>
    </ThemeProvider>
  );
}

function RootChrome() {
  const colorScheme = useColorScheme() ?? 'light';
  const bg = Colors[colorScheme].background;

  useEffect(() => {
    void SystemUI.setBackgroundColorAsync(bg);
  }, [bg]);

  return <StatusBar style={colorScheme === 'dark' ? 'light' : 'dark'} />;
}

function RootShell() {

  const scheme = useColorScheme() ?? 'light';
  const rootBg = Colors[scheme].background;

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: rootBg }} edges={['top', 'left', 'right']}>
      <RootChrome />
      <AlertProvider>
        <AuthProvider>
          <AppContent />
        </AuthProvider>
      </AlertProvider>
    </SafeAreaView>
  );
}


// ==================== التخطيط الرئيسي ====================

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    'Tajawal-Regular': require('../assets/fonts/Tajawal-Regular.ttf'),
    'Tajawal-Medium': require('../assets/fonts/Tajawal-Medium.ttf'),
    'Tajawal-Bold': require('../assets/fonts/Tajawal-Bold.ttf'),
    'Tajawal-ExtraBold': require('../assets/fonts/Tajawal-ExtraBold.ttf'),
    'Tajawal-Black': require('../assets/fonts/Tajawal-Black.ttf'),
  });

  const [themeHydrated, setThemeHydrated] = useState(false);
  const [updateAvailable, setUpdateAvailable] = useState(false);
  const {
    updateState,
    maintenanceState,
    handleUpdate: handleStoreUpdate,
    handleDismiss: handleStoreDismiss,
    recheckMaintenance,
    isCheckingMaintenance,
  } = useAppVersionGate();

  // فحص تحديثات OTA (EAS Update) عند الإقلاع
  useEffect(() => {
    async function checkOTA() {
      if (__DEV__ || !Updates.isEnabled) return;
      try {
        const result = await Updates.checkForUpdateAsync();
        if (result.isAvailable) {
          setUpdateAvailable(true);
        }
      } catch (error) {
        console.log('Error checking OTA update:', error);
      }
    }
    void checkOTA();
  }, []);

  const handleApplyUpdate = async () => {
    if (__DEV__ || !Updates.isEnabled) {
      // محاكاة تنزيل التحديث في بيئة التطوير
      await new Promise((resolve) => setTimeout(resolve, 2500));
      return;
    }
    const fetchResult = await Updates.fetchUpdateAsync();
    if (fetchResult.isNew) {
      await Updates.reloadAsync();
    }
  };

  useEffect(() => {
    if (!(fontsLoaded || fontError)) return;
    let cancelled = false;
    void (async () => {
      await hydrateThemeFromStorage();
      if (!cancelled) {
        setThemeHydrated(true);
        await SplashScreen.hideAsync();
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [fontsLoaded, fontError]);

  const bootScheme = Appearance.getColorScheme() ?? 'light';
  const bootBg = bootScheme === 'dark' ? Colors.dark.background : Colors.light.background;

  if (!(fontsLoaded || fontError) || !themeHydrated) {
    return (
      <View style={{ flex: 1, backgroundColor: bootBg, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color="#10B981" />
      </View>
    );
  }

  return (
    <>
      <RootShell />

      {/* 1️⃣ نافذة وضع الصيانة — معزولة كلياً ولا تطلب أي تحديث برمجي */}
      <MaintenanceModal
        visible={maintenanceState.visible}
        title={maintenanceState.title}
        message={maintenanceState.message}
        onRetry={recheckMaintenance}
        isRetrying={isCheckingMaintenance}
      />

      {/* 2️⃣ نافذة التحديث — تظهر فقط عند وجود تحديث فعلي من المتجر أو OTA */}
      <UpdateModal
        visible={updateState.visible || updateAvailable}
        onUpdate={updateState.visible ? handleStoreUpdate : handleApplyUpdate}
        onDismiss={handleStoreDismiss}
        isMandatory={updateState.visible ? updateState.isMandatory : true}
        title={updateState.visible ? updateState.title : undefined}
        description={updateState.visible ? updateState.description : undefined}
        versionLabel={updateState.visible ? updateState.versionLabel : undefined}
        storeUrl={updateState.visible ? updateState.storeUrl : undefined}
      />
    </>
  );
}
