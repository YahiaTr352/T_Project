import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { LeaderboardList } from '@/components/walking/LeaderboardList';
import { StepHeroCard } from '@/components/walking/StepHeroCard';
import { HealthActivityTracker } from '@/components/walking/HealthActivityTracker';
import { WalkingGroupsTab } from '@/components/walking/WalkingGroupsTab';
import { WalkingHistoryList } from '@/components/walking/WalkingHistoryList';
import { WalkingStatsGrid } from '@/components/walking/WalkingStatsGrid';
import { Toast } from '@/components/toast';
import { computeBestDay } from '@/lib/walking/achievementMetrics';
import { cleanDateYMD, formatSteps, formatEnglishNumber } from '@/lib/walking/formatters';
import { ReviewService } from '@/lib/review/reviewService';
import { WalkingPermissionGuideModal } from '@/components/walking/WalkingPermissionGuideModal';
import { SmartwatchGuideModal } from '@/components/walking/SmartwatchGuideModal';
import { useWalkingTrek } from '@/hooks/use-walking-trek';
import { Colors, silaTheme } from '@/constants/theme';
import { storage } from '@/contexts/auth-context';
import { useWalking } from '@/hooks/use-walking';
import { useLocalWalkingHistory, useLocalWalkingToday } from '@/hooks/use-local-walking-activity';
import * as Haptics from 'expo-haptics';
import { useFocusEffect, useIsFocused, useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import React, { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

type TabKey = 'overview' | 'leaderboard' | 'groups';

const MAIN_TABS: { key: TabKey; label: string; icon: string }[] = [
  { key: 'overview', label: 'نشاطي', icon: '⚡' },
  { key: 'leaderboard', label: 'المتصدرين', icon: '🏆' },
  { key: 'groups', label: 'التحديات', icon: '👥' },
];

export default function WalkingScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';

  const router = useRouter();
  const params = useLocalSearchParams<{ tab?: TabKey | 'trek' | 'history'; groupId?: string; openChat?: string }>();
  const [currentTab, setCurrentTab] = useState<TabKey>('overview');

  const { trekData, refresh: refreshTrek } = useWalkingTrek();
  const hasActiveTrek = Boolean(trekData?.trek);

  React.useEffect(() => {
    if (params.tab === 'trek') {
      router.push('/walking-trek');
      setCurrentTab('overview');
    } else if (params.tab && ['overview', 'leaderboard', 'groups'].includes(params.tab as any)) {
      setCurrentTab(params.tab as TabKey);
    } else if (params.tab === 'history') {
      setCurrentTab('overview');
    } else if (params.groupId) {
      setCurrentTab('groups');
    }
  }, [params.tab, params.groupId]);
  const [refreshing, setRefreshing] = useState(false);
  const [requestingPermission, setRequestingPermission] = useState(false);
  const [showGuideModal, setShowGuideModal] = useState(false);
  const [showWatchGuide, setShowWatchGuide] = useState(false);
  const [primerDismissed, setPrimerDismissed] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastVisible, setToastVisible] = useState(false);

  const walking = useWalking();
  const isFocused = useIsFocused();
  const isIOS = Platform.OS === 'ios';
  const { todayKey, timeZoneKey, today: localToday } = useLocalWalkingToday(
    isIOS && isFocused && currentTab === 'overview' && walking.isHealthSource,
    walking.lastSyncTime?.getTime() ?? null,
  );
  const historyDates = React.useMemo(() => walking.historyRecords
    .map((record) => cleanDateYMD(record.date))
    .filter((key) => key && key < todayKey)
    .sort(), [walking.historyRecords, todayKey]);
  const localHistory = useLocalWalkingHistory(timeZoneKey, historyDates[0] ?? null, historyDates.at(-1) ?? null);
  const personalSteps = localToday?.total ?? walking.todaySteps;
  const personalTodayKey = localToday ? todayKey : walking.currentDateKey;
  const personalBestDay = isIOS
    ? computeBestDay({
        personalStats: walking.personalStats,
        historyRecords: walking.historyRecords,
        ledger: walking.ledger,
        todaySteps: personalSteps,
        currentDateKey: walking.currentDateKey,
        personalTodayDateKey: personalTodayKey,
      })
    : walking;
  // null = لم يكتمل أول فحص بعد — نتجاهل الانتقال الأول (false -> true) الناتج عن التحميل الأولي
  // حتى لا يظهر التوست في كل مرة نفتح فيها التطبيق/التاب والصلاحية ممنوحة مسبقاً.
  const prevMotionReadyRef = useRef<boolean | null>(null);
  const celebratedRef = useRef(false);
  // لا نحتفل إلا بمنح ناتج عن فعل المستخدم في هذه الجلسة (زر التفعيل / دليل الإعدادات)،
  // وليس بالفحص التلقائي عند الفتح.
  const grantIntentRef = useRef(false);

  // استرجاع علامة الاحتفال السابق حتى لا يتكرر التوست عبر إعادة فتح التطبيق
  React.useEffect(() => {
    void storage.getItem('walking_permission_toast_shown').then((v) => {
      if (v === '1') celebratedRef.current = true;
    });
  }, []);

  const celebrateGrant = React.useCallback(() => {
    if (celebratedRef.current) return;
    celebratedRef.current = true;
    grantIntentRef.current = false;
    void storage.setItem('walking_permission_toast_shown', '1');
    setShowGuideModal(false);
    setPrimerDismissed(false);
    setToastMessage('تم تفعيل مزامنة الخطوات بنجاح! 🎉 جاري مزامنة نشاطك...');
    setToastVisible(true);
    void ReviewService.recordDelightMilestone('walking_sensors_enabled');
  }, []);

  // الكشف التلقائي الفوري عند تفعيل الحساس بعد الرجوع من الإعدادات — مرة واحدة فقط لكل منح
  React.useEffect(() => {
    const prev = prevMotionReadyRef.current;
    // أول قراءة: سجّل الحالة فقط دون أي توست (يمنع الظهور عند كل فتح للتطبيق/التاب)
    if (prev === null) {
      prevMotionReadyRef.current = walking.motionReady;
      // إن كانت الصلاحية ممنوحة أصلاً من أول فحص، اعتبرها مُحتفى بها مسبقاً
      if (walking.motionReady) celebratedRef.current = true;
      return;
    }
    if (!prev && walking.motionReady) {
      // المنح التلقائي عند الفتح (بلا ضغط زر/دليل) يُسجَّل silently بلا توست
      if (grantIntentRef.current && !celebratedRef.current) {
        celebrateGrant();
      } else if (!celebratedRef.current) {
        celebratedRef.current = true;
        void storage.setItem('walking_permission_toast_shown', '1');
      }
    }
    // عند سحب الصلاحية لاحقاً نسمح بالاحتفال مجدداً عند المنح التالي، ونمسح العلامة المخزنة
    if (prev && !walking.motionReady) {
      celebratedRef.current = false;
      grantIntentRef.current = false;
      void storage.removeItem('walking_permission_toast_shown');
    }
    prevMotionReadyRef.current = walking.motionReady;
  }, [walking.motionReady, celebrateGrant]);

  const refreshTrekRef = useRef(refreshTrek);
  React.useEffect(() => {
    refreshTrekRef.current = refreshTrek;
  }, [refreshTrek]);

  const recheckMotionRef = useRef(walking.recheckMotionPermission);
  React.useEffect(() => {
    recheckMotionRef.current = walking.recheckMotionPermission;
  }, [walking.recheckMotionPermission]);

  useFocusEffect(
    useCallback(() => {
      void refreshTrekRef.current();
      if (Platform.OS === 'web') return;
      void recheckMotionRef.current();
    }, [])
  );

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([walking.refreshAll(), refreshTrek()]);
    setRefreshing(false);
  }, [walking, refreshTrek]);

  // مزامنة فورية صريحة عند ضغط زر «مزامنة مع صحتي / خطواتي» في كارت الخطوات
  const handleManualSync = useCallback(async () => {
    try {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch {}

    const result = await walking.syncNowWithFeedback();

    if (result.isHealthConnectMissing) {
      setShowGuideModal(true);
      return;
    }

    if (result.permissionRequired) {
      setShowGuideModal(true);
      return;
    }

    if (result.success) {
      try {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      } catch {}
      const sourceLabel =
        Platform.OS === 'ios'
          ? 'صحتي'
          : result.source === 'health_connect'
          ? 'Health Connect'
          : 'حساس جهازك';
      const formattedCount = formatSteps(result.steps);
      setToastMessage(`تمت المزامنة بنجاح! تم تحديث ${formattedCount} خطوة من ${sourceLabel} 🎉`);
      setToastVisible(true);
    } else {
      try {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      } catch {}
      setToastMessage(result.message || 'لم يتم العثور على خطوات جديدة للمزامنة حالياً.');
      setToastVisible(true);
    }
  }, [walking]);

  const onRequestMotionPress = useCallback(async () => {
    grantIntentRef.current = true;
    setRequestingPermission(true);
    try {
      const granted = await walking.requestMotionPermission();
      if (!granted) {
        // إذا لم تمنح الصلاحية (مثلاً حظرها أندرويد لرفضها سابقاً)، نظهر فوراً الدليل التوجيهي
        setShowGuideModal(true);
      }
      // عند المنح: الاحتفال يتم عبر مراقب motionReady (يضمن المزامنة)، وهنا احتياط فوري
      // إن كان motionReady أصبح true مباشرة.
      if (granted && walking.motionReady) {
        celebrateGrant();
      }
    } finally {
      setRequestingPermission(false);
    }
  }, [walking, celebrateGrant]);

  const openGuide = useCallback(() => {
    grantIntentRef.current = true;
    setShowGuideModal(true);
  }, []);

  const handleTabChange = (key: TabKey) => {
    try {
      Haptics.selectionAsync();
    } catch { }
    setCurrentTab(key);
    // مزامنة استباقية للخطوات عند الانتقال لتبويب التحديات أو لوحة المتصدرين
    if (key === 'groups' || key === 'leaderboard') {
      void walking.syncNow();
    }
  };

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={8} minSize={28} maxSize={90} />
      <HeaderBar
        title="برنامج المشي والتحديات"
        subtitle="تتبع خطواتك، نافس العائلة في لوحة الشرف، وأنشئ التحديات"
      />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
        onScroll={({ nativeEvent }) => {
          if (currentTab === 'leaderboard') {
            const { layoutMeasurement, contentOffset, contentSize } = nativeEvent;
            // حماية صارمة من ارتداد السكرول في iOS: لا نفحص نهاية القائمة إلا إذا تم التمرير لأسفل فعلياً والمحتوى أطول من الشاشة
            const hasScrolledDown = contentOffset.y > 40;
            const isContentScrollable = contentSize.height > layoutMeasurement.height + 60;
            const isNearBottom =
              hasScrolledDown &&
              isContentScrollable &&
              layoutMeasurement.height + contentOffset.y >= contentSize.height - 120;

            if (isNearBottom) {
              if (walking.leaderboardCategory === 'streak' || walking.leaderboardType === 'streak') {
                if (walking.hasMoreStreakLeaderboard && !walking.loadingMoreStreakLeaderboard) {
                  void walking.loadMoreStreakLeaderboard();
                }
              } else {
                if (walking.hasMoreLeaderboard && !walking.loadingMoreLeaderboard) {
                  void walking.loadMoreLeaderboard();
                }
              }
            }
          }
        }}
        scrollEventThrottle={200}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={silaTheme.primary}
            colors={[silaTheme.primary]}
          />
        }>
        <View style={styles.topWash}>
          {/* Web Demo Notice */}
          {walking.isDemoMode && (
            <View
              style={[
                styles.banner,
                {
                  backgroundColor: isDark ? '#1E3A5F' : '#DBEAFE',
                  borderColor: isDark ? '#3B82F6' : '#93C5FD',
                },
              ]}>
              <IconSymbol name="info.circle.fill" size={18} color="#3B82F6" />
              <ThemedText style={[styles.bannerText, { color: isDark ? '#BFDBFE' : '#1E3A8A' }]}>
                وضع العرض التجريبي (Web Demo). على أجهزة iOS و Android يتم قراءة الخطوات الحية ومزامنتها تلقائياً.
              </ThemedText>
            </View>
          )}

          {/* Smart Permission Flow: Pre-Permission Primer / Retry / Guided Settings / Spectator Banner */}
          {!walking.isDemoMode && walking.isPermissionChecked && !walking.motionReady && (
            primerDismissed ? (
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={() => {
                  setPrimerDismissed(false);
                  void onRequestMotionPress();
                }}
                style={[
                  styles.spectatorBanner,
                  {
                    backgroundColor: isDark ? '#1E293B' : '#EFF6FF',
                    borderColor: isDark ? '#3B82F660' : '#BFDBFE',
                  },
                ]}>
                <View style={styles.spectatorBannerIconWrap}>
                  <IconSymbol name="figure.walk" size={18} color="#2563EB" />
                </View>
                <ThemedText style={[styles.spectatorBannerText, { color: isDark ? '#93C5FD' : '#1D4ED8' }]}>
                  أنت تتصفح حالياً كـ (متفرج). اضغط هنا لتفعيل احتساب خطواتك والمنافسة ⚡
                </ThemedText>
              </TouchableOpacity>
            ) : (
              <View
                style={[
                  styles.permissionCard,
                  {
                    backgroundColor: isDark ? '#18241B' : '#F0FDF4',
                    borderColor: isDark ? '#22C55E88' : '#86EFAC',
                  },
                ]}>
                <View style={styles.permissionHeader}>
                  <View style={styles.permissionIconBadge}>
                    <IconSymbol
                      name={
                        walking.permissionStatus === 'undetermined'
                          ? 'figure.run'
                          : 'arrow.clockwise'
                      }
                      size={20}
                      color="#16A34A"
                    />
                  </View>
                  <ThemedText type="subtitle" style={styles.permissionTitle}>
                    {walking.permissionStatus === 'undetermined'
                      ? 'تفعيل مزامنة الخطوات الصحية'
                      : 'مزامنة الخطوات متوقفة (مطلوب الإذن)'}
                  </ThemedText>
                </View>

                <ThemedText style={[styles.permissionBody, { color: colors.icon }]}>
                  {walking.permissionStatus === 'undetermined'
                    ? 'لحساب خطواتك تلقائياً وبدقة عالية من تطبيق صحتي أو Health Connect، والمنافسة في لوحة الشرف والتحديات العائلية.'
                    : 'تم رفض صلاحية قراءة الخطوات مسبقاً. يمكنك الضغط على «طلب الإذن مجدداً» للسماح بقراءة خطواتك، أو فتح الدليل السريع للإعدادات.'}
                </ThemedText>

                <View style={styles.permissionButtons}>
                  {/* زر رئيسي صريح ومباشر يطلب الإذن فوراً أو يفتح الدليل إن كان محظوراً بالكامل */}
                  <TouchableOpacity
                    activeOpacity={0.85}
                    disabled={requestingPermission}
                    onPress={onRequestMotionPress}
                    style={[styles.primaryBtn, { opacity: requestingPermission ? 0.7 : 1 }]}>
                    {requestingPermission ? (
                      <ActivityIndicator color="#FFFFFF" />
                    ) : (
                      <ThemedText style={styles.primaryBtnText}>
                        {walking.permissionStatus === 'undetermined'
                          ? 'تفعيل مزامنة الخطوات الآن'
                          : 'طلب الإذن مجدداً (السماح بالخطوات)'}
                      </ThemedText>
                    )}
                  </TouchableOpacity>

                  {/* زر فتح الدليل التوجيهي الذكي بالخطوات البسيطة */}
                  <TouchableOpacity
                    activeOpacity={0.85}
                    onPress={openGuide}
                    style={[
                      styles.secondaryBtn,
                      {
                        backgroundColor: isDark ? '#27272A' : '#FFFFFF',
                        borderColor: colors.border,
                      },
                    ]}>
                    <ThemedText style={[styles.secondaryBtnText, { color: colors.text }]}>
                      عرض دليل التفعيل السريع 💡
                    </ThemedText>
                  </TouchableOpacity>

                  {/* زر المتابعة كمتفرج: لا يعطل التطبيق ولا يحرق الصلاحية */}
                  <TouchableOpacity
                    activeOpacity={0.75}
                    onPress={() => setPrimerDismissed(true)}
                    style={styles.spectatorDismissBtn}>
                    <ThemedText style={[styles.spectatorDismissBtnText, { color: colors.icon }]}>
                      المتابعة كمتفرج حالياً
                    </ThemedText>
                  </TouchableOpacity>
                </View>
              </View>
            )
          )}

          {/* Main Navigation - 3 Unified Pillars (No Nested Scroll) */}
          <View
            style={[
              styles.mainTabsWrap,
              { backgroundColor: isDark ? '#1F2937' : '#F3F4F6' },
            ]}>
            <View style={styles.mainTabsContent}>
              {MAIN_TABS.map((tab) => {
                const isSelected = currentTab === tab.key;
                return (
                  <TouchableOpacity
                    key={tab.key}
                    activeOpacity={0.75}
                    onPress={() => handleTabChange(tab.key)}
                    style={[
                      styles.mainTabItem,
                      isSelected && [
                        styles.mainTabItemActive,
                        { backgroundColor: isDark ? '#374151' : '#FFFFFF' },
                      ],
                    ]}>
                    <ThemedText style={styles.tabIcon}>
                      {tab.icon}
                    </ThemedText>
                    <ThemedText
                      numberOfLines={1}
                      style={[
                        styles.tabLabel,
                        { color: isSelected ? silaTheme.primary : colors.icon },
                        isSelected && styles.tabLabelActive,
                      ]}>
                      {tab.label}
                    </ThemedText>
                  </TouchableOpacity>
                );
              })}
            </View>
          </View>

          {/* TAB 1: OVERVIEW & MY STATS (نشاطي وسجلي) */}
          {currentTab === 'overview' && (
            <View style={styles.tabSection}>
              <StepHeroCard
                todaySteps={personalSteps}
                dailyGoal={walking.dailyGoal}
                distanceKm={localToday ? Number((personalSteps * 0.00076).toFixed(2)) : walking.distanceKm}
                calories={localToday ? Math.round(personalSteps * 0.04) : walking.calories}
                activeMinutes={localToday ? Math.round(personalSteps / 100) : walking.activeMinutes}
                stepTimeZoneLabel={isIOS ? (localToday ? 'بتوقيت الجهاز' : 'بتوقيت الرياض') : undefined}
                currentStreak={walking.streak10k ?? walking.currentStreak}
                isSyncing={walking.isSyncing}
                onSyncPress={handleManualSync}
                lastSyncTime={walking.lastSyncTime}
              />

              {/* زر إرشادات مزامنة الساعات الذكية (مخصص لنظام أندرويد فقط - ساعات جلاكسي وهواوي وغيرها) */}
              {Platform.OS === 'android' && (
                <TouchableOpacity
                  activeOpacity={0.8}
                  onPress={() => setShowWatchGuide(true)}
                  style={[
                    styles.smartwatchPill,
                    {
                      backgroundColor: isDark ? '#082F49' : '#F0F9FF',
                      borderColor: isDark ? '#0284C740' : '#BAE6FD',
                    },
                  ]}>
                  <View style={styles.smartwatchPillInner}>
                    <Ionicons name="watch-outline" size={17} color="#0284C7" />
                    <ThemedText style={[styles.smartwatchPillText, { color: isDark ? '#38BDF8' : '#0284C7' }]}>
                      مزامنة خطوات الساعات الذكية (Galaxy, Pixel, Huawei...)
                    </ThemedText>
                  </View>
                  <Ionicons name="chevron-back" size={15} color={isDark ? '#38BDF8' : '#0284C7'} />
                </TouchableOpacity>
              )}

              <ThemedText type="subtitle" style={[styles.sectionTitle, { marginTop: 20 }]}>
                سجل النشاط والخطوات
              </ThemedText>
              <HealthActivityTracker
                ledger={walking.ledger}
                historyRecords={walking.historyRecords}
                todaySteps={walking.todaySteps}
                dailyGoal={walking.dailyGoal}
                onLoadHourlyForDate={walking.loadHourlyForDate}
                localTodayKey={isIOS ? todayKey : undefined}
                localToday={localToday}
                timeZoneKey={timeZoneKey}
              />

              <ThemedText type="subtitle" style={[styles.sectionTitle, { marginTop: 20 }]}>
                إحصائيات الإنجاز
              </ThemedText>
              <WalkingStatsGrid
                todaySteps={personalSteps}
                dailyGoal={walking.dailyGoal}
                familyRankToday={walking.familyRankToday}
                familyTotalToday={walking.familyTotalToday}
                bestDaySteps={personalBestDay.bestDaySteps}
                bestDayDate={personalBestDay.bestDayDate}
                streak10k={walking.streak10k}
                activeDaysCount={walking.activeDaysCount}
                avgDaily30d={walking.avgDaily30d}
                currentStreak={walking.currentStreak}
                avgDailySteps={walking.personalStats?.avg_daily_steps || Math.round(walking.todaySteps)}
                totalDaysActive={walking.personalStats?.total_days_active || 1}
              />

              {/* سجل الأيام السابقة المدمج - لا حاجة لتابة منفصلة */}
              <View style={styles.historySectionHeader}>
                <ThemedText type="subtitle" style={styles.sectionTitle}>
                  سجل الأيام السابقة
                </ThemedText>
                {walking.historyRecords.length > 0 && (
                  <View style={[styles.historyCountBadge, { backgroundColor: isDark ? '#27272A' : '#E5E7EB' }]}>
                    <ThemedText style={[styles.historyCountBadgeText, { color: colors.icon }]}>
                      {walking.historyRecords.length} يوم
                    </ThemedText>
                  </View>
                )}
              </View>
              <WalkingHistoryList
                records={walking.historyRecords}
                isLoading={walking.loadingHistory}
                localSteps={localHistory?.daily}
                today={isIOS ? { dateKey: personalTodayKey, steps: personalSteps, isDeviceTime: !!localToday } : undefined}
                showTimeZone={isIOS}
              />
            </View>
          )}

          {/* TAB 2: LEADERBOARD (لوحة المتصدرين والشرف) */}
          {currentTab === 'leaderboard' && (
            <View style={styles.tabSection}>
              {/* بنر المسابقة التفاعلي - يظهر في لوحة المتصدرين */}
              {hasActiveTrek && (
                <TouchableOpacity
                  activeOpacity={0.85}
                  onPress={() => router.push('/walking-trek')}
                  style={[
                    styles.trekOverviewBanner,
                    styles.trekLeaderboardBanner,
                    {
                      backgroundColor: isDark ? '#142820' : '#ECFDF5',
                      borderColor: isDark ? '#05966960' : '#A7F3D0',
                    },
                  ]}>
                  <View style={[styles.trekOverviewIconWrap, { backgroundColor: isDark ? '#064E3B40' : 'rgba(5, 150, 105, 0.15)' }]}>
                    <MaterialCommunityIcons
                      name="routes"
                      size={24}
                      color={isDark ? '#6EE7B7' : '#059669'}
                    />
                  </View>
                  <View style={{ flex: 1, paddingHorizontal: 12 }}>
                    <ThemedText style={[styles.trekOverviewTitle, { color: isDark ? '#6EE7B7' : '#065F46' }]} numberOfLines={1}>
                      {trekData?.trek?.title || 'مسار المشي التفاعلي'}
                    </ThemedText>
                    <ThemedText style={[styles.trekOverviewSub, { color: isDark ? '#A7F3D0' : '#047857' }]} numberOfLines={1}>
                      {trekData?.trek
                        ? `إجمالي الخطوات: ${formatSteps(Number(trekData.trek.total_walked_steps) || 0)} خطوة • ${formatEnglishNumber(Number(trekData.trek.total_walked_km) || 0, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} كم مقطوعة`
                        : 'شاهد تقدم العائلة عبر محطات المسار على الخريطة التفاعلية'}
                    </ThemedText>
                  </View>
                  <Ionicons name="chevron-back" size={20} color={isDark ? '#6EE7B7' : '#059669'} />
                </TouchableOpacity>
              )}

              <LeaderboardList
                clubs={walking.walkingClubs}
                userClubStreaks={walking.clubStreaks}
                data={walking.leaderboard}
                selectedPeriod={walking.leaderboardPeriod}
                onPeriodChange={walking.setLeaderboardPeriod}
                streakData={walking.streakLeaderboard}
                isLoadingStreak={walking.loadingStreakLeaderboard}
                isLoadingMoreStreak={walking.loadingMoreStreakLeaderboard}
                hasMoreStreak={walking.hasMoreStreakLeaderboard}
                onLoadMoreStreak={walking.loadMoreStreakLeaderboard}
                activeCategory={walking.leaderboardCategory}
                onCategoryChange={walking.setLeaderboardCategory}
                selectedGenderFilter={walking.leaderboardGenderFilter}
                onGenderFilterChange={walking.setLeaderboardGenderFilter}
                isLoading={walking.loadingLeaderboard}
                isLoadingMore={walking.loadingMoreLeaderboard}
                hasMore={walking.hasMoreLeaderboard}
                onLoadMore={walking.loadMoreLeaderboard}
                userStreak5k={walking.streak5k}
                userStreak10k={walking.streak10k ?? walking.currentStreak}
                userStreak20k={walking.streak20k}
                todaySteps={walking.todaySteps}
                userPersonId={walking.userPersonId}
                privacyMode={walking.walkingPrivacyMode}
                onUpdatePrivacyMode={walking.updateLeaderboardPrivacy}
              />
            </View>
          )}

          {/* TAB 3: CHALLENGES & GROUPS (التحديات والمجموعات) */}
          {currentTab === 'groups' && (
            <View style={styles.tabSection}>
              <WalkingGroupsTab
                groups={walking.groups}
                isLoading={walking.loadingGroups}
                activeGroupDetail={walking.activeGroupDetail}
                groupLeaderboard={walking.groupLeaderboard}
                todaySteps={walking.todaySteps}
                initialGroupId={params.groupId ? Number(params.groupId) : null}
                openChat={params.openChat === '1'}
                onSyncNow={walking.syncNow}
                onLoadGroupDetails={walking.loadGroupDetails}
                onLoadGroupLeaderboardPeriod={walking.loadGroupLeaderboardPeriod}
                onCreateGroup={walking.createGroup}
                onJoinGroup={walking.joinGroup}
                onJoinGroupByCode={walking.joinGroupByCode}
                onInviteMember={walking.inviteMember}
                onLeaveGroup={walking.leaveGroup}
                onDeleteGroup={walking.deleteGroup}
                onRefreshGroups={walking.refreshGroups}
              />
            </View>
          )}
        </View>
      </ScrollView>

      {/* مودال الدليل التوجيهي الذكي لغير التقنيين */}
      <WalkingPermissionGuideModal
        visible={showGuideModal}
        onClose={() => setShowGuideModal(false)}
        onOpenWatchGuide={() => setShowWatchGuide(true)}
      />

      {/* مودال دليل ربط ومزامنة الساعات الذكية */}
      <SmartwatchGuideModal
        visible={showWatchGuide}
        onClose={() => setShowWatchGuide(false)}
      />

      {/* توست الاحتفال بالتفعيل الناجح */}
      <Toast
        visible={toastVisible}
        message={toastMessage}
        type="success"
        onHide={() => setToastVisible(false)}
      />
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 18,
    paddingTop: 8,
    paddingBottom: 110,
  },
  topWash: {
    borderRadius: 12,
    paddingBottom: 12,
  },
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 16,
  },
  bannerText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
    lineHeight: 18,
  },
  permissionCard: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 16,
    marginBottom: 16,
    gap: 10,
  },
  permissionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  permissionTitle: {
    fontFamily: 'Tajawal-Bold',
    flex: 1,
    fontSize: 15,
  },
  permissionBody: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 13,
    lineHeight: 20,
  },
  permissionButtons: {
    gap: 8,
    marginTop: 4,
  },
  primaryBtn: {
    backgroundColor: '#16A34A',
    paddingVertical: 12,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 46,
  },
  primaryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  spectatorBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 14,
    borderWidth: 1,
    marginBottom: 14,
  },
  spectatorBannerIconWrap: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: '#DBEAFE',
    alignItems: 'center',
    justifyContent: 'center',
  },
  spectatorBannerText: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 12.5,
    lineHeight: 18,
    textAlign: 'right',
  },
  permissionIconBadge: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: '#DCFCE7',
    alignItems: 'center',
    justifyContent: 'center',
  },
  spectatorDismissBtn: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 8,
    marginTop: 2,
  },
  spectatorDismissBtnText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
  },
  secondaryBtn: {
    paddingVertical: 10,
    borderRadius: 14,
    alignItems: 'center',
    borderWidth: 1,
  },
  secondaryBtnText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
  },
  mainTabsWrap: {
    borderRadius: 18,
    padding: 4,
    marginBottom: 16,
  },
  mainTabsContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  mainTabItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 8,
    borderRadius: 14,
    gap: 6,
    minHeight: 48,
  },
  mainTabItemActive: {
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.12,
        shadowRadius: 4,
      },
      android: { elevation: 3 },
    }),
  },
  tabIcon: {
    fontSize: 16,
    textAlign: 'center',
  },
  tabLabel: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,
    textAlign: 'center',
  },
  tabLabelActive: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
  },
  smartwatchPill: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 14,
    borderWidth: 1,
    marginTop: 10,
    marginBottom: 4,
  },
  smartwatchPillInner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  smartwatchPillText: {
    fontSize: 12.5,
    fontWeight: '700',
  },
  tabSection: {
    marginBottom: 10,
  },
  sectionTitle: {
    fontFamily: 'Tajawal-Bold',
    marginBottom: 12,
    fontSize: 17,
  },
  historySectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 22,
    marginBottom: 4,
  },
  historyCountBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  historyCountBadgeText: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 12,
  },
  trekTabSection: {
    flex: 1,
    paddingBottom: 16,
  },
  trekScrollContent: {
    paddingHorizontal: 0,
    paddingBottom: 0,
  },
  trekOverviewBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 16,
    padding: 14,
    borderWidth: 1.5,
    marginTop: 14,
  },
  trekLeaderboardBanner: {
    marginTop: 0,
    marginBottom: 14,
  },
  trekOverviewIconWrap: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(5, 150, 105, 0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  trekOverviewTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  trekOverviewSub: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11.5,
    marginTop: 2,
  },
  trekStandaloneButton: {
    borderRadius: 16,
    borderWidth: 1.5,
    marginBottom: 14,
    padding: 12,
  },
  trekButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  trekIconBadge: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(5, 150, 105, 0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  trekButtonTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14.5,
  },
  liveBadge: {
    backgroundColor: '#10B981',
    paddingHorizontal: 6,
    paddingVertical: 1.5,
    borderRadius: 6,
  },
  liveBadgeText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 10,
  },
  trekButtonSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11.5,
    marginTop: 2,
  },
  trekArrowBadge: {
    backgroundColor: 'rgba(5, 150, 105, 0.12)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 10,
  },
  trekModalContainer: {
    flex: 1,
  },
  trekModalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    paddingTop: Platform.OS === 'ios' ? 48 : 14,
    borderBottomWidth: 1,
  },
  trekModalCloseBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 10,
  },
  trekModalCloseText: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 13,
  },
  trekModalTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  trekModalSubtitle: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 11,
    marginTop: 1,
  },
});
