import { useAuth } from '@/contexts/auth-context';
import {
  createWalkingGroup,
  deleteWalkingGroup,
  fetchGroupLeaderboard,
  fetchLeaderboard,
  fetchStreakLeaderboard,
  fetchWalkingClubs,
  type WalkingClub,
  fetchMyWalkingStats,
  fetchWalkingGroups,
  fetchWalkingHistory,
  fetchWalkingGroupDetails,
  updateWalkingPrivacySettings,
  type WalkingPrivacyMode,
  inviteGroupMember,
  joinWalkingGroup,
  joinWalkingGroupByCode,
  leaveOrRemoveGroupMember,
  syncTodaySteps,
  type CreateGroupPayload,
  type LeaderboardData,
  type LeaderboardPeriod,
  type PersonalStats,
  type WalkingGroupDetail,
  type WalkingGroupItem,
  type WalkingRecord,
  type WalkingSource,
} from '@/lib/api/walkingApi';
import {
  capToday,
  dateKey,
  invalidateOnNewDay,
  ksaDateKey,
  ksaHour,
  ksaStartOfDay,
  last7DayBars,
  loadWalkingLedger,
  MAX_STEPS_PER_HOUR,
  mergeHourly,
  mergeToday,
  recordHourlyDelta,
  saveWalkingLedger,
  setAuthoritativeDaySteps,
  startOfDay,
  sumMonth,
  totalTracked,
  type WalkingLedger,
} from '@/lib/walking/ledger';
import {
  buildSyncStepsPayload,
  recordWalkingSyncSuccess,
} from '@/lib/walking/syncService';
import {
  getDeviceWalkingSource,
  getHealthProvider,
  getWalkingSourceLabel,
  type HealthPermissionState,
} from '@/lib/walking/health';
import { cleanDateYMD } from '@/lib/walking/formatters';
import {
  buildDemoLeaderboard,
  buildDemoLedger,
  getMaxStepsForCategory,
  getMinStepsForCategory,
  type LeaderboardCategory,
} from '@/lib/walking/demoData';
import {
  computeActiveDaysCount,
  computeAvgDaily30d,
  computeBestDay,
  computeStreak10k,
} from '@/lib/walking/achievementMetrics';
import * as Haptics from 'expo-haptics';
import { Pedometer } from 'expo-sensors';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AppState, type AppStateStatus, Platform } from 'react-native';

export {
  getMaxStepsForCategory,
  getMinStepsForCategory,
  type LeaderboardCategory,
};

const DAILY_GOAL_DEFAULT = 10000;
const IOS_REFRESH_MS = 45_000;
const HEALTH_REFRESH_MS = 45_000;
const SAVE_DEBOUNCE_MS = 500;
const MAX_DELTA_PER_EVENT = 8000;
const MIN_SYNC_DELTA_STEPS = 10;

// Demo ledger/leaderboard helpers and achievement metrics live in @/lib/walking/demoData
// and @/lib/walking/achievementMetrics.

export type GroupModalMode = 'overview' | 'leaderboard' | 'members';

export interface WalkingSyncFeedbackResult {
  success: boolean;
  steps: number;
  source: WalkingSource | 'device' | 'apple_health' | 'health_connect' | null;
  isHealthConnectMissing?: boolean;
  permissionRequired?: boolean;
  message?: string;
}

let cachedPermissionGranted: boolean | null = null;
let cachedTrackingMode: 'none' | 'health' | 'device' = 'none';

export function resetWalkingPermissionCache() {
  cachedPermissionGranted = null;
  cachedTrackingMode = 'none';
}

export function useWalking() {
  const { hasIdentity, user } = useAuth();

  // Local step tracking & state
  const [currentDateKey, setCurrentDateKey] = useState<string>(() => ksaDateKey());
  const [ledger, setLedger] = useState<WalkingLedger>({ daily: {} });
  const [dailyGoal] = useState<number>(DAILY_GOAL_DEFAULT);
  const [motionReady, setMotionReady] = useState<boolean>(() => cachedPermissionGranted === true);
  const [isPermissionChecked, setIsPermissionChecked] = useState<boolean>(() => cachedPermissionGranted !== null);
  const [trackingUnavailable, setTrackingUnavailable] = useState(false);
  const [permissionDenied, setPermissionDenied] = useState<boolean>(() => cachedPermissionGranted === false);
  const [permissionStatus, setPermissionStatus] = useState<'undetermined' | 'granted' | 'denied'>(() =>
    cachedPermissionGranted === true ? 'granted' : cachedPermissionGranted === false ? 'denied' : 'undetermined'
  );
  const [canRequestMotionAgain, setCanRequestMotionAgain] = useState(true);
  const [motionRequiresSettings, setMotionRequiresSettings] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [lastSyncTime, setLastSyncTime] = useState<Date | null>(null);
  const healthProvider = useMemo(() => getHealthProvider(), []);
  const [trackingMode, setTrackingMode] = useState<'none' | 'health' | 'device'>(() => cachedTrackingMode);
  const trackingModeRef = useRef<'none' | 'health' | 'device'>(cachedTrackingMode);
  trackingModeRef.current = trackingMode;
  const activeSource: WalkingSource | null =
    trackingMode === 'health' && healthProvider
      ? healthProvider.source
      : trackingMode === 'device'
        ? getDeviceWalkingSource()
        : null;
  const activeSourceRef = useRef<WalkingSource | null>(activeSource);
  activeSourceRef.current = activeSource;
  const isAuthoritativeSource =
    trackingMode === 'health' || (trackingMode === 'device' && Platform.OS === 'ios');
  const isAuthoritativeSourceRef = useRef(isAuthoritativeSource);
  isAuthoritativeSourceRef.current = isAuthoritativeSource;

  // Leaderboard state
  const [leaderboardPeriod, setLeaderboardPeriod] = useState<LeaderboardPeriod>('today');
  const [leaderboardGenderFilter, setLeaderboardGenderFilter] = useState<'male' | 'female' | undefined>(undefined);
  const [leaderboard, setLeaderboard] = useState<LeaderboardData | null>(null);
  const [loadingLeaderboard, setLoadingLeaderboard] = useState(false);
  const [walkingPrivacyMode, setWalkingPrivacyMode] = useState<WalkingPrivacyMode>('visible');
  const [loadingMoreLeaderboard, setLoadingMoreLeaderboard] = useState(false);
  const [leaderboardPage, setLeaderboardPage] = useState(1);
  const [hasMoreLeaderboard, setHasMoreLeaderboard] = useState(false);

  // Leaderboard Category & Clubs state
  const [walkingClubs, setWalkingClubs] = useState<WalkingClub[]>([]);
  const walkingClubsRef = useRef<WalkingClub[]>([]);
  walkingClubsRef.current = walkingClubs;
  const [leaderboardCategory, setLeaderboardCategory] = useState<LeaderboardCategory>('steps');
  const leaderboardCategoryRef = useRef<LeaderboardCategory>('steps');
  leaderboardCategoryRef.current = leaderboardCategory;

  // Streak Leaderboard state (محفوظ بالكامل لإعادة التفعيل مستقبلاً)
  const [leaderboardType, setLeaderboardType] = useState<'steps' | 'streak'>('steps');
  const [streakLeaderboard, setStreakLeaderboard] = useState<LeaderboardData | null>(null);
  const [loadingStreakLeaderboard, setLoadingStreakLeaderboard] = useState(false);
  const [loadingMoreStreakLeaderboard, setLoadingMoreStreakLeaderboard] = useState(false);
  const [streakLeaderboardPage, setStreakLeaderboardPage] = useState(1);
  const [hasMoreStreakLeaderboard, setHasMoreStreakLeaderboard] = useState(false);
  const loadingMoreLeaderboardLockRef = useRef(false);
  const loadingMoreStreakLockRef = useRef(false);

  // Personal stats state
  const [personalStats, setPersonalStats] = useState<PersonalStats | null>(null);
  const personalStatsRef = useRef<PersonalStats | null>(null);
  personalStatsRef.current = personalStats;
  const [loadingStats, setLoadingStats] = useState(false);

  // Groups state
  const [groups, setGroups] = useState<WalkingGroupItem[]>([]);
  const [loadingGroups, setLoadingGroups] = useState(false);
  const [activeGroupDetail, setActiveGroupDetail] = useState<WalkingGroupDetail | null>(null);
  const [groupLeaderboard, setGroupLeaderboard] = useState<LeaderboardData | null>(null);

  // History state
  const [historyRecords, setHistoryRecords] = useState<WalkingRecord[]>([]);
  const [loadingHistory, setLoadingHistory] = useState(false);

  const androidLastRaw = useRef(0);
  const androidPrimed = useRef(false);
  const iosPullTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const syncDebounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const persist = useRef(ledger);
  persist.current = ledger;

  const loadStreakLeaderboardRef = useRef<(gender?: 'male' | 'female', minSteps?: number, maxSteps?: number, isSilent?: boolean) => Promise<void>>(() => Promise.resolve());
  const loadLeaderboardRef = useRef<(period?: LeaderboardPeriod, gender?: 'male' | 'female', category?: LeaderboardCategory, isSilent?: boolean) => Promise<void>>(() => Promise.resolve());
  const loadPersonalStatsRef = useRef<(isSilent?: boolean) => Promise<void>>(() => Promise.resolve());
  const loadGroupDetailsRef = useRef<(groupId: number, period?: LeaderboardPeriod, options?: { inPlace?: boolean }) => Promise<void>>(() => Promise.resolve());
  const activeGroupDetailRef = useRef<WalkingGroupDetail | null>(null);
  activeGroupDetailRef.current = activeGroupDetail;

  const lastSyncedStepsRef = useRef<number>(0);
  const lastSyncedDateRef = useRef<string | null>(null);
  const isSyncingToServerRef = useRef<boolean>(false);
  const hasHydratedRef = useRef<boolean>(false);

  // آخر قراءة موثوقة من الحساس لليوم الحالي (لتصفير اليوم الجديد حتمياً بدل max()).
  // تُضبط مع أول قراءة حسّاس بعد تغير ksaDateKey، وتُستخدم لمنع رفع اللدجر لرقم السيرفر.
  const lastSensorDayRef = useRef<string | null>(null);
  const lastSensorValueRef = useRef<number>(0);

  // 1. الحفظ المحلي المستمر في SecureStore
  useEffect(() => {
    const t = setTimeout(() => {
      saveWalkingLedger(persist.current).catch(() => {});
    }, SAVE_DEBOUNCE_MS);
    return () => clearTimeout(t);
  }, [ledger]);

  const iosFullHourlySyncedDateRef = useRef<string | null>(null);

  // دالة مزامنة خطوات اليوم مع السيرفر (تعيد حالة النجاح لضمان تسلسل العمليات مع فحص Smart Delta)
  const syncCurrentStepsToServer = useCallback(
    async (
      stepsCount: number,
      options?: {
        isAuthoritative?: boolean;
        source?: WalkingSource;
        allowZero?: boolean;
        force?: boolean;
        targetDate?: string;
      }
    ): Promise<boolean> => {
      const isAuthoritative = options?.isAuthoritative ?? isAuthoritativeSourceRef.current;
      const todayK = ksaDateKey();
      const targetDate = options?.targetDate ?? todayK;

      // حماية صارمة: منع تسرب خطوات الأمس عند استيقاظ المؤقت بعد منتصف الليل
      if (options?.targetDate && options.targetDate !== todayK) {
        console.warn(`[useWalking] Dropped stale sync for ${options.targetDate} as current KSA day is ${todayK}`);
        return false;
      }

      const sanitizedSteps = Number.isFinite(stepsCount) ? Math.max(0, Math.round(stepsCount)) : 0;
      const allowZero = Boolean(options?.allowZero);

      if (
        !hasIdentity ||
        trackingModeRef.current === 'none' ||
        (sanitizedSteps <= 0 && !(isAuthoritative && allowZero))
      ) {
        return false;
      }

      // فحص Smart Delta لمنع إرسال طلبات متطابقة بدون فائدة
      const stepDelta = Math.abs(sanitizedSteps - lastSyncedStepsRef.current);
      const isDateChanged = lastSyncedDateRef.current !== todayK;
      const shouldSync = Boolean(options?.force) || isDateChanged || allowZero || stepDelta >= MIN_SYNC_DELTA_STEPS;

      if (!shouldSync) {
        return true;
      }

      if (isSyncingToServerRef.current) {
        return false;
      }

      isSyncingToServerRef.current = true;
      setIsSyncing(true);
      try {
        const payload = buildSyncStepsPayload(sanitizedSteps, {
          isAuthoritative,
          source: options?.source ?? activeSourceRef.current ?? getDeviceWalkingSource(),
          targetDate,
        });
        const res = await syncTodaySteps(payload);

        const stepErrs = res?.errors?.['steps'];
        if (res && !res.success && stepErrs && stepErrs.length > 0) {
          try {
            const stats = await fetchMyWalkingStats();
            if (stats) {
              const serverDate = stats.date ?? ksaDateKey();
              // عند رفض السيرفر: لا نمسح قراءة الحساس الموثوقة — السقف هو max(السيرفر، الحساس).
              const sensorFloor =
                isAuthoritativeSourceRef.current && lastSensorDayRef.current === serverDate
                  ? lastSensorValueRef.current
                  : 0;
              setPersonalStats(stats);
              setLedger((prev) =>
                capToday(
                  invalidateOnNewDay(prev, serverDate, stats.reset_id ?? null, stats.today_steps ?? 0),
                  serverDate,
                  Math.max(stats.today_steps ?? 0, sensorFloor)
                )
              );
            }
          } catch {}
          return false;
        }

        if (res?.success) {
          if (res.server?.date_today) {
            const srvDate = res.server.date_today;
            const srvReset = res.server.reset_id ?? null;
            const confirmedSteps = res.data?.steps ?? sanitizedSteps;
            // رقم السيرفر لا يرفع اللدجر فوق قراءة الحساس الموثوقة لليوم نفسه.
            setLedger((prev) => {
              const merged = invalidateOnNewDay(prev, srvDate, srvReset, confirmedSteps);
              if (isAuthoritativeSourceRef.current && lastSensorDayRef.current === srvDate) {
                return capToday(merged, srvDate, lastSensorValueRef.current);
              }
              return merged;
            });
          }
          recordWalkingSyncSuccess(sanitizedSteps);
          lastSyncedStepsRef.current = sanitizedSteps;
          lastSyncedDateRef.current = todayK;
          setLastSyncTime(new Date());

          // فور نجاح المزامنة مع السيرفر: تحديث صامت بالكامل في الخلفية (Silent In-Place Update)
          void loadLeaderboardRef.current(undefined, undefined, undefined, true);
          void loadStreakLeaderboardRef.current(undefined, undefined, undefined, true);
          void loadPersonalStatsRef.current(true);
          if (activeGroupDetailRef.current) {
            void loadGroupDetailsRef.current(activeGroupDetailRef.current.id, undefined, { inPlace: true });
          }
          return true;
        }
        return false;
      } catch (e) {
        console.warn('[useWalking] auto sync error:', e);
        return false;
      } finally {
        isSyncingToServerRef.current = false;
        setIsSyncing(false);
      }
    },
    [hasIdentity]
  );

  const applyToday = useCallback(
    (steps: number, options?: { isAuthoritative?: boolean; forceOverwrite?: boolean }) => {
      const key = ksaDateKey();
      const sanitized = Number.isFinite(steps) ? Math.max(0, Math.round(steps)) : 0;
      const isAuthoritative = options?.isAuthoritative ?? isAuthoritativeSourceRef.current;
      const prevLocal = persist.current.daily[key] ?? 0;
      const remoteDateKey = personalStatsRef.current?.date
        ? cleanDateYMD(personalStatsRef.current.date)
        : '';
      const prevRemote =
        !remoteDateKey || remoteDateKey === key
          ? (personalStatsRef.current?.today_steps ?? 0)
          : 0;
      const allowZero = isAuthoritative && sanitized === 0 && (prevLocal > 0 || prevRemote > 0);

      const deltaFromSynced = Math.abs(sanitized - lastSyncedStepsRef.current);
      const isDateChanged = lastSyncedDateRef.current !== key;

      setLedger((prev) => {
        const next = isAuthoritative
          ? setAuthoritativeDaySteps(
              prev,
              key,
              sanitized,
              options?.forceOverwrite ? { forceOverwrite: true } : undefined
            )
          : mergeToday(prev, key, sanitized);
        persist.current = next;
        return next;
      });

      // نجدول المزامنة فقط إذا كان هناك تغيير حقيقي (>= MIN_SYNC_DELTA_STEPS) أو تغير اليوم أو تصفير
      if (deltaFromSynced >= MIN_SYNC_DELTA_STEPS || isDateChanged || allowZero) {
        if (syncDebounceTimer.current) clearTimeout(syncDebounceTimer.current);
        const snapshotDate = key;
        syncDebounceTimer.current = setTimeout(() => {
          syncDebounceTimer.current = null;
          // فحص تاريخ اليوم لحظة انطلاق المؤقت الفعلي لمنع تسرب خطوات الأمس
          if (ksaDateKey() !== snapshotDate) {
            console.warn('[useWalking] Cancelled debounced sync because midnight passed while timer was pending.');
            return;
          }
          void syncCurrentStepsToServer(sanitized, { isAuthoritative, allowZero, targetDate: snapshotDate });
        }, 4000);
      }
    },
    [syncCurrentStepsToServer]
  );

  const pullIosToday = useCallback(
    async (options?: { fullHourlySweep?: boolean }) => {
      if (Platform.OS !== 'ios') return;
      const now = new Date();
      const start = ksaStartOfDay(now);
      let todayTotal = 0;
      try {
        const r = await Pedometer.getStepCountAsync(start, now);
        todayTotal = Number.isFinite(r.steps) ? Math.max(0, Math.round(r.steps)) : 0;
        const todayK = ksaDateKey(now);
        // أول قراءة حسّاس لليوم الجديد تكتب حتمياً (تصفير) بدل max() حتى تُشفى أي قيمة مسممة.
        const isFirstReadOfDay = lastSensorDayRef.current !== todayK;
        lastSensorDayRef.current = todayK;
        lastSensorValueRef.current = todayTotal;
        const curLedgerSteps = persist.current.daily[todayK] ?? 0;
        if (isFirstReadOfDay || Math.abs(todayTotal - curLedgerSteps) >= 1 || todayTotal === 0) {
          applyToday(todayTotal, { isAuthoritative: true, forceOverwrite: isFirstReadOfDay });
        }
      } catch {
        return;
      }

      // استعلام تفصيلي دقيق لساعات اليوم عبر CoreMotion / CMPedometer
      // نستخدم إزاحة المللي ثانية المباشرة (start.getTime() + h * 3600_000) لتجنب أي خلل في المناطق الزمنية
      // ولا نضيف أبداً أي بواقي أو فروق إلى الساعة الحالية لضمان التطابق 100% مع تطبيق "صحتي".
      try {
        const curHour = ksaHour(now);
        const todayK = ksaDateKey(now);
        const hasFullSweepForToday =
          iosFullHourlySyncedDateRef.current === todayK &&
          Boolean(persist.current.hourly?.[todayK]);
        const shouldDoFullSweep = Boolean(options?.fullHourlySweep) || !hasFullSweepForToday;

        const hoursToQuery: number[] = [];
        if (shouldDoFullSweep) {
          // استعلام الساعات النشطة الأخيرة فقط لتفادي تجميد الـ Bridge
          const startHour = Math.max(0, curHour - 5);
          for (let h = startHour; h <= curHour; h++) {
            hoursToQuery.push(h);
          }
        } else {
          if (curHour > 0) hoursToQuery.push(curHour - 1);
          hoursToQuery.push(curHour);
        }

        const hourlyMap: Record<number, number> = {};
        const BATCH_SIZE = 6;

        for (let i = 0; i < hoursToQuery.length; i += BATCH_SIZE) {
          const slice = hoursToQuery.slice(i, i + BATCH_SIZE);
          const batchResults = await Promise.all(
            slice.map((h) => {
              const hStart = new Date(start.getTime() + h * 3600 * 1000);
              const hEnd =
                h === curHour
                  ? now
                  : new Date(start.getTime() + (h + 1) * 3600 * 1000);

              if (hEnd.getTime() <= hStart.getTime()) {
                return Promise.resolve({ hour: h, steps: 0, ok: true });
              }

              return Pedometer.getStepCountAsync(hStart, hEnd)
                .then((res) => ({
                  hour: h,
                  steps: Math.min(MAX_STEPS_PER_HOUR, Math.max(0, Math.round(res.steps))),
                  ok: true,
                }))
                .catch(() => ({ hour: h, steps: 0, ok: false }));
            })
          );

          for (const item of batchResults) {
            if (item.ok) {
              hourlyMap[item.hour] = item.steps;
            }
          }
        }

        if (shouldDoFullSweep) {
          iosFullHourlySyncedDateRef.current = todayK;
        }

        setLedger((prev) => {
          const next = mergeHourly(prev, todayK, hourlyMap, shouldDoFullSweep);
          persist.current = next;
          return next;
        });
      } catch (e) {
        console.warn('[useWalking] pullIosHourly error:', e);
      }
    },
    [applyToday]
  );

  // جلب خطوات الأيام الأخيرة (الماضية) من الحساس لضمان اكتمال شارت الأسبوع وتطابقه مع "صحتي"
  const pullIosRecentDays = useCallback(async () => {
    if (Platform.OS !== 'ios') return;
    const today = new Date();
    const dayPromises: Promise<{ key: string; steps: number; ok: boolean }>[] = [];

    for (let i = 1; i <= 6; i++) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      const dStart = ksaStartOfDay(d);
      const dEnd = new Date(dStart.getTime() + 24 * 3600 * 1000 - 1);
      const k = ksaDateKey(d);

      dayPromises.push(
        Pedometer.getStepCountAsync(dStart, dEnd)
          .then((res) => ({
            key: k,
            steps: Number.isFinite(res.steps) ? Math.max(0, Math.round(res.steps)) : 0,
            ok: true,
          }))
          .catch(() => ({ key: k, steps: 0, ok: false }))
      );
    }

    try {
      const results = await Promise.all(dayPromises);
      setLedger((prev) => {
        const nextDaily = { ...prev.daily };
        let changed = false;
        for (const item of results) {
          if (item.ok && nextDaily[item.key] !== item.steps) {
            nextDaily[item.key] = item.steps;
            changed = true;
          }
        }
        return changed ? { ...prev, daily: nextDaily } : prev;
      });
    } catch {
      // Non-blocking
    }
  }, []);

  const pullHealthToday = useCallback(
    async (options?: { fullHourlySweep?: boolean }) => {
      const provider = healthProvider;
      if (!provider || trackingModeRef.current !== 'health') return;

      const now = new Date();
      const day = await provider.readDay(now).catch(() => null);
      if (!day) return;

      const todayK = ksaDateKey(now);
      // أول قراءة حسّاس لليوم الجديد تكتب حتمياً (تصفير) بدل max() حتى تُشفى أي قيمة مسممة.
      const isFirstReadOfDay = lastSensorDayRef.current !== todayK;
      lastSensorDayRef.current = todayK;
      lastSensorValueRef.current = day.total;
      const curLedgerSteps = persist.current.daily[todayK] ?? 0;
      if (isFirstReadOfDay || Math.abs(day.total - curLedgerSteps) >= 1 || day.total === 0) {
        applyToday(day.total, { isAuthoritative: true, forceOverwrite: isFirstReadOfDay });
      }
      // readDay يعيد لقطة كاملة لساعات اليوم، لذا نستبدل الخريطة بالكامل حتى لا تبقى أعمدة قديمة
      // (مثل خطوات يدوية حُذفت أو استُبعدت) عالقة في الرسم البياني.
      setLedger((prev) => {
        const next = mergeHourly(prev, ksaDateKey(now), day.hourly, true);
        persist.current = next;
        return next;
      });
    },
    [applyToday, healthProvider]
  );

  const pullHealthRecentDays = useCallback(async () => {
    const provider = healthProvider;
    if (!provider || trackingModeRef.current !== 'health') return;

    const days = await provider.readRecentDays(7).catch(() => null);
    if (!days) return;

    const now = new Date();
    const todayKey = ksaDateKey(now);
    setLedger((prev) => {
      const nextDaily = { ...prev.daily };
      const nextHourly = { ...(prev.hourly ?? {}) };
      let changed = false;
      for (let offset = 1; offset <= 6; offset += 1) {
        const date = new Date(now);
        date.setDate(date.getDate() - offset);
        const key = ksaDateKey(date);
        if (key === todayKey) continue;
        const rawSteps = days[key] ?? 0;
        const steps = Number.isFinite(rawSteps) ? Math.max(0, Math.round(rawSteps)) : 0;
        if (nextDaily[key] !== steps) {
          nextDaily[key] = steps;
          changed = true;
        }
        if (nextHourly[key]) {
          delete nextHourly[key];
          changed = true;
        }
      }
      return changed ? { ...prev, daily: nextDaily, hourly: nextHourly } : prev;
    });
  }, [healthProvider]);

  // دالة مزامنة فورية عند الطلب - تجلب أحدث القراءات من Health Connect / الحساس وترسلها للسيرفر
  const syncNow = useCallback(async (): Promise<boolean> => {
    if (syncDebounceTimer.current) {
      clearTimeout(syncDebounceTimer.current);
      syncDebounceTimer.current = null;
    }
    if (trackingModeRef.current === 'health') {
      await pullHealthToday({ fullHourlySweep: true });
      void pullHealthRecentDays();
    } else if (Platform.OS === 'ios') {
      await pullIosToday({ fullHourlySweep: true });
      void pullIosRecentDays();
    }
    const todayKey = ksaDateKey();
    const currentSteps = persist.current.daily[todayKey] ?? 0;
    const remoteTodaySteps = personalStatsRef.current?.today_steps ?? 0;
    const allowZero = isAuthoritativeSourceRef.current && currentSteps === 0 && remoteTodaySteps > 0;
    if (currentSteps > 0 || allowZero) {
      return await syncCurrentStepsToServer(currentSteps, {
        isAuthoritative: isAuthoritativeSourceRef.current,
        source: activeSourceRef.current ?? getDeviceWalkingSource(),
        allowZero,
        force: true,
      });
    }
    return false;
  }, [pullHealthToday, pullHealthRecentDays, pullIosToday, pullIosRecentDays, syncCurrentStepsToServer]);

  // دالة المزامنة الفورية مع التغذية الراجعة التفاعلية (زر "مزامنة مع صحتي / خطواتي")
  const syncNowWithFeedback = useCallback(async (): Promise<WalkingSyncFeedbackResult> => {
    if (Platform.OS === 'web') {
      return { success: false, steps: 0, source: 'manual', message: 'المزامنة الحية غير مدعومة على المتصفح.' };
    }

    if (syncDebounceTimer.current) {
      clearTimeout(syncDebounceTimer.current);
      syncDebounceTimer.current = null;
    }

    // 1. معالجة هواتف أندرويد (فحص توفر Health Connect أو طلب أذوناته)
    if (Platform.OS === 'android') {
      const provider = healthProvider;
      const isHcAvailable = provider ? await provider.isAvailable().catch(() => false) : false;

      if (!isHcAvailable && trackingModeRef.current !== 'health') {
        const todayKey = ksaDateKey();
        const currentSteps = persist.current.daily[todayKey] ?? 0;
        // إذا كان تطبيق Health Connect غير متوفر والخطوات صفر
        if (currentSteps <= 0) {
          return {
            success: false,
            steps: 0,
            source: 'health_connect',
            isHealthConnectMissing: true,
            message: 'تطبيق Health Connect غير مثبت على جهازك، وهو مطلوب لقراءة خطواتك وساعتك بدقة.',
          };
        }
      }

      if (isHcAvailable && provider) {
        const permState = await provider.getPermissionState().catch(() => null);
        if (permState?.status !== 'granted') {
          const reqState = await provider.requestPermissions().catch(() => null);
          if (reqState?.status !== 'granted') {
            return {
              success: false,
              steps: 0,
              source: 'health_connect',
              permissionRequired: true,
              message: 'يرجى منح إذن قراءة الخطوات لمزامنة نشاطك بدقة.',
            };
          }
        }
        // الصلاحية مؤكدة: تفعيل نمط health وجلب الخطوات الكاملة فوراً
        trackingModeRef.current = 'health';
        setTrackingMode('health');
        await pullHealthToday({ fullHourlySweep: true });
        void pullHealthRecentDays();
      }
    } else if (Platform.OS === 'ios') {
      // 2. معالجة آيفون وتطبيق صحتي (Apple HealthKit)
      const provider = healthProvider;
      if (provider) {
        const permState = await provider.getPermissionState().catch(() => null);
        if (permState?.status !== 'granted') {
          const reqState = await provider.requestPermissions().catch(() => null);
          if (reqState?.status !== 'granted') {
            return {
              success: false,
              steps: 0,
              source: 'apple_health',
              permissionRequired: true,
              message: 'يرجى منح إذن قراءة الخطوات من تطبيق صحتي (Apple Health).',
            };
          }
        }
        trackingModeRef.current = 'health';
        setTrackingMode('health');
        await pullHealthToday({ fullHourlySweep: true });
        void pullHealthRecentDays();
      } else {
        await pullIosToday({ fullHourlySweep: true });
        void pullIosRecentDays();
      }
    }

    const todayKey = ksaDateKey();
    const currentSteps = persist.current.daily[todayKey] ?? 0;
    const remoteTodaySteps = personalStatsRef.current?.today_steps ?? 0;
    const allowZero = isAuthoritativeSourceRef.current && currentSteps === 0 && remoteTodaySteps > 0;
    const resolvedSource =
      activeSourceRef.current ?? (Platform.OS === 'ios' ? 'apple_health' : 'health_connect');

    // إذا كانت الخطوات صفر والمستخدم جديد أو لم يمشِ بعد، نؤكد نجاح الفحص
    if (currentSteps <= 0 && !allowZero) {
      return {
        success: true,
        steps: 0,
        source: resolvedSource,
        message: 'تمت قراءة البيانات بنجاح، ورصيد خطواتك الحالي 0 خطوة.',
      };
    }

    const synced = await syncCurrentStepsToServer(currentSteps, {
      isAuthoritative: true,
      source: resolvedSource,
      allowZero,
      force: true,
    });

    return {
      success: synced,
      steps: currentSteps,
      source: resolvedSource,
      message: synced ? undefined : 'تعذر إرسال الخطوات إلى السيرفر، يرجى التحقق من اتصالك بالإنترنت.',
    };
  }, [
    healthProvider,
    pullHealthToday,
    pullHealthRecentDays,
    pullIosToday,
    pullIosRecentDays,
    syncCurrentStepsToServer,
  ]);

  // جلب ساعات أي تاريخ محدد في آخر 7 أيام عند اختياره في الشاشة
  const loadHourlyForDate = useCallback(
    async (targetDate: Date) => {
      const now = new Date();
      const isToday =
        dateKey(targetDate) === dateKey(now) || ksaDateKey(targetDate) === ksaDateKey(now);

      if (trackingModeRef.current === 'health') {
        const provider = healthProvider;
        if (!provider) return;
        if (isToday) {
          await pullHealthToday({ fullHourlySweep: true });
          return;
        }

        const healthKey = ksaDateKey(targetDate);
        if (
          persist.current.hourly?.[healthKey] &&
          Object.keys(persist.current.hourly[healthKey]).length > 0
        ) {
          return;
        }

        const day = await provider.readDay(targetDate).catch(() => null);
        if (!day) return;
        setLedger((prev) =>
          mergeHourly(
            setAuthoritativeDaySteps(prev, healthKey, day.total),
            healthKey,
            day.hourly,
            true
          )
        );
        return;
      }

      if (Platform.OS !== 'ios') return;
      if (isToday) {
        await pullIosToday({ fullHourlySweep: true });
        return;
      }

      const dKey = dateKey(targetDate);
      const diffDays = Math.floor((now.getTime() - targetDate.getTime()) / (1000 * 60 * 60 * 24));
      if (diffDays < 0 || diffDays > 7) return;

      if (persist.current.hourly?.[dKey] && Object.keys(persist.current.hourly[dKey]).length > 0) {
        return;
      }

      const dStart = startOfDay(targetDate);
      const hourlyMap: Record<number, number> = {};
      const BATCH_SIZE = 6;

      try {
        for (let i = 0; i < 24; i += BATCH_SIZE) {
          const hoursSlice = Array.from({ length: Math.min(BATCH_SIZE, 24 - i) }, (_, idx) => i + idx);
          const results = await Promise.all(
            hoursSlice.map((h) => {
              const hStart = new Date(dStart.getTime() + h * 3600 * 1000);
              const hEnd = new Date(dStart.getTime() + (h + 1) * 3600 * 1000);
              return Pedometer.getStepCountAsync(hStart, hEnd)
                .then((res) => ({
                  hour: h,
                  steps: Math.min(MAX_STEPS_PER_HOUR, Math.max(0, Math.round(res.steps))),
                  ok: true,
                }))
                .catch(() => ({ hour: h, steps: 0, ok: false }));
            })
          );

          for (const item of results) {
            if (item.ok && item.steps > 0) {
              hourlyMap[item.hour] = item.steps;
            }
          }
        }

        setLedger((prev) => mergeHourly(prev, dKey, hourlyMap, true));
      } catch {
        // Non-blocking
      }
    },
    [healthProvider, pullHealthToday, pullIosToday]
  );

  const scheduleIosPull = useCallback(() => {
    if (Platform.OS !== 'ios') return;
    if (iosPullTimer.current) clearTimeout(iosPullTimer.current);
    iosPullTimer.current = setTimeout(() => {
      iosPullTimer.current = null;
      pullIosToday({ fullHourlySweep: false }).catch(() => {});
    }, 1600);
  }, [pullIosToday]);

  const applyPermissionResult = useCallback(
    (
      granted: boolean,
      canAskAgain: boolean,
      status?: 'undetermined' | 'granted' | 'denied',
      options?: { mode?: 'health' | 'device'; requiresSettings?: boolean }
    ) => {
      cachedPermissionGranted = granted;
      if (granted) {
        const mode = options?.mode ?? 'device';
        cachedTrackingMode = mode;
        trackingModeRef.current = mode;
        setTrackingMode(mode);
        setPermissionStatus('granted');
        setPermissionDenied(false);
        setTrackingUnavailable(false);
        setCanRequestMotionAgain(true);
        setMotionRequiresSettings(false);
        setMotionReady(true);
        setIsPermissionChecked(true);
        return;
      }

      cachedTrackingMode = 'none';
      trackingModeRef.current = 'none';
      setTrackingMode('none');
      const effectiveStatus = status ?? (canAskAgain ? 'undetermined' : 'denied');
      setPermissionStatus(effectiveStatus);
      setMotionReady(false);
      setCanRequestMotionAgain(canAskAgain);
      setMotionRequiresSettings(options?.requiresSettings ?? !canAskAgain);
      setPermissionDenied(effectiveStatus === 'denied' || !canAskAgain);
      setIsPermissionChecked(true);
    },
    []
  );

  const applyHealthPermissionState = useCallback(
    (state: HealthPermissionState): boolean => {
      if (state.status === 'granted') {
        setTrackingUnavailable(false);
        applyPermissionResult(true, false, 'granted', { mode: 'health' });
        return true;
      }
      if (state.status === 'unavailable') return false;

      setTrackingUnavailable(false);
      applyPermissionResult(
        false,
        state.canAskAgain,
        state.status === 'denied' ? 'denied' : 'undetermined',
        { requiresSettings: state.requiresSettings }
      );
      return false;
    },
    [applyPermissionResult]
  );

  const markPedometerUnavailable = useCallback(() => {
    cachedPermissionGranted = false;
    cachedTrackingMode = 'none';
    trackingModeRef.current = 'none';
    setTrackingMode('none');
    setTrackingUnavailable(true);
    setMotionReady(false);
    setPermissionDenied(false);
    setCanRequestMotionAgain(false);
    setMotionRequiresSettings(false);
    setIsPermissionChecked(true);
  }, []);

  const checkPedometerPermission = useCallback(async (): Promise<boolean> => {
    try {
      const available = await Pedometer.isAvailableAsync();
      if (!available) {
        markPedometerUnavailable();
        return false;
      }
      setTrackingUnavailable(false);

      const perm = await Pedometer.getPermissionsAsync();
      applyPermissionResult(perm.granted, perm.canAskAgain, perm.status as any, {
        mode: 'device',
      });
      return perm.granted;
    } catch (e) {
      console.warn('[useWalking] checkPedometerPermission error:', e);
      return false;
    }
  }, [applyPermissionResult, markPedometerUnavailable]);

  const requestPedometerPermission = useCallback(async (): Promise<boolean> => {
    try {
      const available = await Pedometer.isAvailableAsync();
      if (!available) {
        markPedometerUnavailable();
        return false;
      }
      setTrackingUnavailable(false);

      const perm = await Pedometer.requestPermissionsAsync();
      applyPermissionResult(perm.granted, perm.canAskAgain, perm.status as any, {
        mode: 'device',
      });
      if (perm.granted) {
        try {
          await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch {}
      }
      return perm.granted;
    } catch (e) {
      console.warn('[useWalking] requestPedometerPermission error:', e);
      return false;
    }
  }, [applyPermissionResult, markPedometerUnavailable]);

  // فحص الصلاحية فقط في الخلفية دون إزعاج المستخدم بنافذة منبثقة مفاجئة (Non-intrusive Check)
  const checkMotionPermission = useCallback(async (): Promise<boolean> => {
    if (Platform.OS === 'web') {
      setIsPermissionChecked(true);
      return false;
    }
    try {
      if (healthProvider) {
        const state = await healthProvider.getPermissionState().catch(() => null);
        if (state && state.status !== 'unavailable') {
          const res = applyHealthPermissionState(state);
          setIsPermissionChecked(true);
          return res;
        }
      }
      // على أندرويد فقط، أو في حال غياب HealthKit/HealthConnect تماماً نلجأ إلى Pedometer كبديل
      if (Platform.OS === 'android' || !healthProvider) {
        const res = await checkPedometerPermission();
        setIsPermissionChecked(true);
        return res;
      }
      setIsPermissionChecked(true);
      return false;
    } catch (e) {
      console.warn('[useWalking] checkMotionPermission error:', e);
      setIsPermissionChecked(true);
      return false;
    }
  }, [applyHealthPermissionState, checkPedometerPermission, healthProvider]);

  // طلب الصلاحية صراحة بنافذة النظام فقط عند ضغط المستخدم على زر التفعيل (User-Initiated Request)
  const requestMotionPermission = useCallback(async (): Promise<boolean> => {
    if (Platform.OS === 'web') return false;
    try {
      if (healthProvider) {
        const state = await healthProvider.requestPermissions().catch(() => null);
        if (state && state.status !== 'unavailable') {
          const granted = applyHealthPermissionState(state);
          if (granted) {
            try {
              await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            } catch {}
          }
          return granted;
        }
      }
      if (Platform.OS === 'android' || !healthProvider) {
        return await requestPedometerPermission();
      }
      return false;
    } catch (e) {
      console.warn('[useWalking] requestMotionPermission error:', e);
      return false;
    }
  }, [applyHealthPermissionState, healthProvider, requestPedometerPermission]);

  // إعادة الفحص الهادئ عند العودة للشاشة أو التطبيق من الإعدادات
  const recheckMotionPermission = useCallback(async (): Promise<boolean> => {
    if (Platform.OS === 'web') return false;
    const wasReady = trackingModeRef.current !== 'none';
    const granted = await checkMotionPermission();
    if (granted && !wasReady) {
      try {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      } catch {}
    }
    return granted;
  }, [checkMotionPermission]);

  // جلب قائمة نوادي المشي النشطة ديناميكياً من الخادم
  const loadWalkingClubs = useCallback(async () => {
    try {
      const clubs = await fetchWalkingClubs();
      if (clubs && clubs.length > 0) {
        setWalkingClubs(clubs);
        walkingClubsRef.current = clubs;
      }
    } catch (err) {
      console.warn('[useWalking] loadWalkingClubs error:', err);
    }
  }, []);

  // جلب لوحة المتصدرين (الصفحة الأولى) مع دعم التحديث الصامت
  const loadLeaderboard = useCallback(
    async (
      period: LeaderboardPeriod = leaderboardPeriod,
      gender?: 'male' | 'female',
      category?: LeaderboardCategory,
      isSilent?: boolean
    ) => {
      const activeCat = category ?? leaderboardCategoryRef.current;
      const minSteps = getMinStepsForCategory(activeCat);
      if (!isSilent) {
        setLoadingLeaderboard(true);
      }
      setLeaderboardPage(1);
      try {
        if (!hasIdentity && Platform.OS === 'web') {
          const demo = buildDemoLeaderboard(period, minSteps);
          setLeaderboard(demo);
          setHasMoreLeaderboard(false);
          return;
        }
        const data = await fetchLeaderboard(period, 1, 10, gender ?? leaderboardGenderFilter, minSteps);
        if (data) {
          setLeaderboard(data);
          if (data.viewer_privacy?.leaderboard_mode) {
            setWalkingPrivacyMode(data.viewer_privacy.leaderboard_mode);
          }
          setHasMoreLeaderboard(Boolean(data.pagination?.has_more));
        } else if (!isSilent) {
          // fallback إذا لم يكن هناك رد من السيرفر
          setLeaderboard(buildDemoLeaderboard(period, minSteps));
          setHasMoreLeaderboard(false);
        }
      } catch (err) {
        console.warn('[useWalking] loadLeaderboard error:', err);
        if (!isSilent) {
          setLeaderboard(buildDemoLeaderboard(period, minSteps));
          setHasMoreLeaderboard(false);
        }
      } finally {
        if (!isSilent) {
          setLoadingLeaderboard(false);
        }
      }
    },
    [hasIdentity, leaderboardPeriod, leaderboardGenderFilter]
  );

  const changeLeaderboardGenderFilter = useCallback((gender?: 'male' | 'female') => {
    setLeaderboardGenderFilter(gender);
    void loadLeaderboard(leaderboardPeriod, gender);
    void loadStreakLeaderboard(gender);
  }, [leaderboardPeriod, loadLeaderboard]);

  // تحميل المزيد من المتسابقين بالترتيب (Pagination / Infinite Scroll)
  const loadMoreLeaderboard = useCallback(async () => {
    if (loadingMoreLeaderboardLockRef.current || loadingMoreLeaderboard || loadingLeaderboard || !hasMoreLeaderboard) {
      return;
    }

    // حساب رقم الصفحة التالية بناءً على عدد العناصر الحالية في القائمة لتفادي أي انزلاق أو قفز للصفحات
    const currentItemsCount = leaderboard?.top?.length ?? 0;
    const derivedPage = Math.floor(currentItemsCount / 10) + 1;
    const nextPage = Math.max(leaderboardPage + 1, derivedPage);

    loadingMoreLeaderboardLockRef.current = true;
    setLoadingMoreLeaderboard(true);
    try {
      const minSteps = getMinStepsForCategory(leaderboardCategoryRef.current);
      const maxSteps = getMaxStepsForCategory(leaderboardCategoryRef.current);
      const data = await fetchLeaderboard(leaderboardPeriod, nextPage, 10, leaderboardGenderFilter, minSteps, maxSteps);
      if (data && data.top?.length > 0) {
        setLeaderboard((prev) => {
          if (!prev) return data;
          const existingKeys = new Set(prev.top.map((e) => (e.person_id ? `p_${e.person_id}` : `r_${e.rank}`)));
          const newEntries = data.top.filter((e) => !existingKeys.has(e.person_id ? `p_${e.person_id}` : `r_${e.rank}`));
          return {
            ...prev,
            top: [...prev.top, ...newEntries],
            pagination: data.pagination ?? prev.pagination,
          };
        });
        setLeaderboardPage(nextPage);
        setHasMoreLeaderboard(Boolean(data.pagination?.has_more));
      } else {
        setHasMoreLeaderboard(false);
      }
    } catch (err) {
      console.warn('[useWalking] loadMoreLeaderboard error:', err);
    } finally {
      loadingMoreLeaderboardLockRef.current = false;
      setLoadingMoreLeaderboard(false);
    }
  }, [loadingMoreLeaderboard, loadingLeaderboard, hasMoreLeaderboard, leaderboardPage, leaderboard?.top?.length, leaderboardPeriod, leaderboardGenderFilter]);

  // جلب لوحة شرف سلسلة الأيام النشطة / نوادي الاستمرارية مع دعم التحديث الصامت
  const loadStreakLeaderboard = useCallback(
    async (gender?: 'male' | 'female', minSteps?: number, maxSteps?: number, isSilent?: boolean) => {
      const activeCat = leaderboardCategoryRef.current;
      const effectiveMinSteps = minSteps ?? getMinStepsForCategory(activeCat, walkingClubsRef.current) ?? 10000;
      const effectiveMaxSteps = maxSteps !== undefined ? maxSteps : getMaxStepsForCategory(activeCat, walkingClubsRef.current);
      if (!isSilent) {
        setLoadingStreakLeaderboard(true);
      }
      setStreakLeaderboardPage(1);
      try {
        const data = await fetchStreakLeaderboard(1, 10, gender ?? leaderboardGenderFilter, effectiveMinSteps, effectiveMaxSteps);
        if (data) {
          setStreakLeaderboard(data);
          if (data.viewer_privacy?.leaderboard_mode) {
            setWalkingPrivacyMode(data.viewer_privacy.leaderboard_mode);
          }
          setHasMoreStreakLeaderboard(Boolean(data.pagination?.has_more));
        } else if (!isSilent) {
          setStreakLeaderboard(null);
          setHasMoreStreakLeaderboard(false);
        }
      } catch (err) {
        console.warn('[useWalking] loadStreakLeaderboard error:', err);
        if (!isSilent) {
          setStreakLeaderboard(null);
          setHasMoreStreakLeaderboard(false);
        }
      } finally {
        if (!isSilent) {
          setLoadingStreakLeaderboard(false);
        }
      }
    },
    [leaderboardGenderFilter]
  );

  // تبديل فئة لوحة الصدارة (سباق الخطوات العام أو أحد نوادي الخطوات أو السلسلة)
  const changeLeaderboardCategory = useCallback(
    (cat: LeaderboardCategory) => {
      setLeaderboardCategory(cat);
      leaderboardCategoryRef.current = cat;
      const isStreakOrClub = cat === 'streak' || cat !== 'steps';
      setLeaderboardType(isStreakOrClub ? 'streak' : 'steps');
      if (isStreakOrClub) {
        const minSteps = getMinStepsForCategory(cat, walkingClubsRef.current) ?? 10000;
        const maxSteps = getMaxStepsForCategory(cat, walkingClubsRef.current);
        void loadStreakLeaderboard(leaderboardGenderFilter, minSteps, maxSteps);
      } else {
        void loadLeaderboard(leaderboardPeriod, leaderboardGenderFilter, cat);
      }
    },
    [leaderboardPeriod, leaderboardGenderFilter, loadLeaderboard, loadStreakLeaderboard]
  );

  const loadMoreStreakLeaderboard = useCallback(async () => {
    if (loadingMoreStreakLockRef.current || loadingMoreStreakLeaderboard || loadingStreakLeaderboard || !hasMoreStreakLeaderboard) {
      return;
    }

    const currentItemsCount = streakLeaderboard?.top?.length ?? 0;
    const derivedPage = Math.floor(currentItemsCount / 10) + 1;
    const nextPage = Math.max(streakLeaderboardPage + 1, derivedPage);

    loadingMoreStreakLockRef.current = true;
    setLoadingMoreStreakLeaderboard(true);
    try {
      const activeCat = leaderboardCategoryRef.current;
      const minSteps = getMinStepsForCategory(activeCat, walkingClubsRef.current) ?? 10000;
      const maxSteps = getMaxStepsForCategory(activeCat, walkingClubsRef.current);
      const data = await fetchStreakLeaderboard(nextPage, 10, leaderboardGenderFilter, minSteps, maxSteps);
      if (data && data.top?.length > 0) {
        setStreakLeaderboard((prev) => {
          if (!prev) return data;
          const existingKeys = new Set(prev.top.map((e) => (e.person_id ? `p_${e.person_id}` : `r_${e.rank}`)));
          const newEntries = data.top.filter((e) => !existingKeys.has(e.person_id ? `p_${e.person_id}` : `r_${e.rank}`));
          return {
            ...prev,
            top: [...prev.top, ...newEntries],
            pagination: data.pagination ?? prev.pagination,
          };
        });
        setStreakLeaderboardPage(nextPage);
        setHasMoreStreakLeaderboard(Boolean(data.pagination?.has_more));
      } else {
        setHasMoreStreakLeaderboard(false);
      }
    } catch (err) {
      console.warn('[useWalking] loadMoreStreakLeaderboard error:', err);
    } finally {
      loadingMoreStreakLockRef.current = false;
      setLoadingMoreStreakLeaderboard(false);
    }
  }, [loadingMoreStreakLeaderboard, loadingStreakLeaderboard, hasMoreStreakLeaderboard, streakLeaderboardPage, streakLeaderboard?.top?.length, leaderboardGenderFilter]);

  const updateLeaderboardPrivacy = useCallback(
    async (mode: WalkingPrivacyMode): Promise<boolean> => {
      try {
        const res = await updateWalkingPrivacySettings(mode);
        if (res?.success) {
          setWalkingPrivacyMode(mode);
          void loadLeaderboard(leaderboardPeriod, leaderboardGenderFilter);
          return true;
        }
        return false;
      } catch (err) {
        console.warn('[useWalking] updateLeaderboardPrivacy error:', err);
        return false;
      }
    },
    [leaderboardPeriod, leaderboardGenderFilter, loadLeaderboard]
  );

  // جلب الإحصائيات الشخصية
  const loadPersonalStats = useCallback(
    async (isSilent?: boolean) => {
      if (!hasIdentity) return;
      if (!isSilent) setLoadingStats(true);
      try {
        const stats = await fetchMyWalkingStats();
        if (stats) {
          setPersonalStats(stats);
          const serverDate = stats.date ?? ksaDateKey();
          // رقم السيرفر لا يرفع اللدجر فوق قراءة الحساس الموثوقة لليوم نفسه.
          setLedger((prev) => {
            const merged = invalidateOnNewDay(prev, serverDate, stats.reset_id ?? null, stats.today_steps ?? 0);
            if (isAuthoritativeSourceRef.current && lastSensorDayRef.current === serverDate) {
              return capToday(merged, serverDate, lastSensorValueRef.current);
            }
            return merged;
          });
        }
      } catch (e) {
        console.warn('[useWalking] loadPersonalStats error:', e);
      } finally {
        if (!isSilent) setLoadingStats(false);
      }
    },
    [hasIdentity]
  );

  // جلب المجموعات
  const loadGroups = useCallback(async () => {
    setLoadingGroups(true);
    try {
      const res = await fetchWalkingGroups(1, 'all');
      if (res?.data) {
        setGroups(res.data);
      }
    } catch (e) {
      console.warn('[useWalking] loadGroups error:', e);
    } finally {
      setLoadingGroups(false);
    }
  }, []);

  // جلب السجل التاريخي
  const loadHistory = useCallback(async () => {
    if (!hasIdentity) return;
    setLoadingHistory(true);
    try {
      const res = await fetchWalkingHistory(1);
      if (res?.data) {
        setHistoryRecords(res.data);
      }
    } catch (e) {
      console.warn('[useWalking] loadHistory error:', e);
    } finally {
      setLoadingHistory(false);
    }
  }, [hasIdentity]);

  // تفاصيل مجموعة محددة وصدارتها (مع دعم التحديث الصامت In-Place وافتراض فترة اليوم)
  const loadGroupDetails = useCallback(
    async (
      groupId: number,
      period: LeaderboardPeriod = 'today',
      options?: { inPlace?: boolean }
    ) => {
      const isSameGroup = activeGroupDetailRef.current?.id === groupId;
      const shouldUpdateInPlace = options?.inPlace || isSameGroup;

      if (!shouldUpdateInPlace) {
        setActiveGroupDetail(null);
        setGroupLeaderboard(null);
      }
      try {
        const [detail, groupLd] = await Promise.all([
          fetchWalkingGroupDetails(groupId),
          fetchGroupLeaderboard(groupId, period),
        ]);
        if (detail) setActiveGroupDetail(detail);
        if (groupLd) setGroupLeaderboard(groupLd);
      } catch (e) {
        console.warn('[useWalking] loadGroupDetails error:', e);
      }
    },
    []
  );

  loadLeaderboardRef.current = loadLeaderboard;
  loadStreakLeaderboardRef.current = loadStreakLeaderboard;
  loadPersonalStatsRef.current = loadPersonalStats;
  loadGroupDetailsRef.current = loadGroupDetails;


  // حذف تحدي ناعماً وإزالته من الحالة
  const handleDeleteGroup = useCallback(async (groupId: number) => {
    try {
      const res = await deleteWalkingGroup(groupId);
      if (res?.success) {
        setGroups((prev) => prev.filter((g) => g.id !== groupId));
        if (activeGroupDetail?.id === groupId) {
          setActiveGroupDetail(null);
        }
        return { success: true, message: res.message || 'تم حذف التحدي بنجاح' };
      }
      return { success: false, message: res?.message || 'تعذر حذف التحدي' };
    } catch (e: any) {
      console.warn('[useWalking] handleDeleteGroup error:', e);
      return { success: false, message: 'حدث خطأ أثناء حذف التحدي' };
    }
  }, [activeGroupDetail?.id]);

  // تحديث صدارة التحدي داخل المجموعة لفترة زمنية محددة
  const loadGroupLeaderboardPeriod = useCallback(async (groupId: number, period: LeaderboardPeriod) => {
    try {
      const groupLd = await fetchGroupLeaderboard(groupId, period);
      if (groupLd) {
        setGroupLeaderboard(groupLd);
      }
    } catch (e) {
      console.warn('[useWalking] loadGroupLeaderboardPeriod error:', e);
    }
  }, []);

  // إنشاء مجموعة جديدة
  const handleCreateGroup = useCallback(
    async (payload: CreateGroupPayload) => {
      const res = await createWalkingGroup(payload);
      if (res?.success) {
        try {
          await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch {}
        await loadGroups();
        return { success: true, group: res.data };
      }
      return { success: false, message: res?.message || 'تعذر إنشاء المجموعة' };
    },
    [loadGroups]
  );

  // الانضمام لمجموعة عامة
  const handleJoinGroup = useCallback(
    async (groupId: number) => {
      const res = await joinWalkingGroup(groupId);
      if (res?.success) {
        try {
          await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch {}
        await loadGroups();
        return { success: true, message: res.message || 'تم الانضمام للمجموعة بنجاح!' };
      }
      return { success: false, message: res?.message || 'تعذر الانضمام للمجموعة' };
    },
    [loadGroups]
  );

  // الانضمام لمجموعة عبر رمز الدعوة
  const handleJoinGroupByCode = useCallback(
    async (inviteCode: string) => {
      const res = await joinWalkingGroupByCode(inviteCode);
      if (res?.success) {
        try {
          await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch {}
        await loadGroups();
        return { success: true, message: res.message || 'تم الانضمام للتحدي بنجاح!' };
      }
      return { success: false, message: res?.message || 'رمز الدعوة غير صحيح أو منتهي الصلاحية' };
    },
    [loadGroups]
  );

  // دعوة أو إضافة عضو/أعضاء للمجموعة
  const handleInviteMember = useCallback(
    async (groupId: number, personIds: number | number[]) => {
      const res = await inviteGroupMember(groupId, personIds);
      if (res?.success) {
        try {
          await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch {}
        await Promise.all([loadGroupDetails(groupId), loadGroups()]);
        return { success: true, message: res.message || 'تمت إضافة الأعضاء للتحدي بنجاح!' };
      }
      return { success: false, message: res?.message || 'تعذر إضافة الأعضاء' };
    },
    [loadGroupDetails, loadGroups]
  );

  // مغادرة أو إزالة/طرد عضو من المجموعة
  const handleLeaveGroup = useCallback(
    async (groupId: number, personId: number) => {
      const isSelf = user?.id ? Number(user.id) === Number(personId) : false;
      const res = await leaveOrRemoveGroupMember(groupId, personId);
      if (res?.success) {
        try {
          await Haptics.notificationAsync(
            isSelf
              ? Haptics.NotificationFeedbackType.Warning
              : Haptics.NotificationFeedbackType.Success
          );
        } catch {}

        if (isSelf) {
          await loadGroups();
          setActiveGroupDetail(null);
        } else {
          // استبعاد عضو آخر: لا نغلق المودال بل نحدث تفاصيل المجموعة والبيانات فوراً
          await Promise.all([loadGroupDetails(groupId), loadGroups()]);
        }
        return { success: true, message: res.message || 'تمت العملية بنجاح' };
      }
      return { success: false, message: res?.message || 'تعذر إتمام العملية' };
    },
    [user?.id, loadGroups, loadGroupDetails]
  );

  // تحديث كل البيانات (Pull-to-refresh)
  const refreshAll = useCallback(async () => {
    try {
      await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    } catch {}

    if (Platform.OS === 'web') {
      setLedger(buildDemoLedger());
    } else {
      const granted = await requestMotionPermission();
      if (granted) {
        if (trackingModeRef.current === 'health') {
          await pullHealthToday({ fullHourlySweep: true });
          void pullHealthRecentDays();
        } else if (Platform.OS === 'ios') {
          await pullIosToday({ fullHourlySweep: true });
          void pullIosRecentDays();
        }
      }
    }

    const currentTodaySteps = persist.current.daily[ksaDateKey()] ?? 0;
    const remoteTodaySteps = personalStatsRef.current?.today_steps ?? 0;
    const allowZero =
      isAuthoritativeSourceRef.current && currentTodaySteps === 0 && remoteTodaySteps > 0;
    if ((currentTodaySteps > 0 || allowZero) && hasIdentity) {
      // ننتظر انتهاء المزامنة مع السيرفر أولاً لضمان وجود الخطوات في قاعدة البيانات قبل جلب الصدارة
      await syncCurrentStepsToServer(currentTodaySteps, {
        isAuthoritative: isAuthoritativeSourceRef.current,
        source: activeSourceRef.current ?? getDeviceWalkingSource(),
        allowZero,
      });
    }

    await Promise.all([
      loadWalkingClubs(),
      loadLeaderboard(leaderboardPeriod),
      loadStreakLeaderboard(),
      loadPersonalStats(),
      loadGroups(),
      loadHistory(),
    ]);
  }, [
    requestMotionPermission,
    pullHealthToday,
    pullHealthRecentDays,
    pullIosToday,
    pullIosRecentDays,
    hasIdentity,
    syncCurrentStepsToServer,
    loadLeaderboard,
    loadStreakLeaderboard,
    leaderboardPeriod,
    loadPersonalStats,
    loadGroups,
    loadHistory,
  ]);

  // مزامنة الخطوات فور توفر هوية المستخدم (Auth Session Hydration) - تشغيل هادئ لمرة واحدة فقط
  useEffect(() => {
    if (!hasIdentity || hasHydratedRef.current) return;
    hasHydratedRef.current = true;
    const key = ksaDateKey();
    const steps = persist.current.daily[key] ?? 0;
    if (steps > 0) {
      void syncCurrentStepsToServer(steps, {
        isAuthoritative: isAuthoritativeSourceRef.current,
        source: activeSourceRef.current ?? getDeviceWalkingSource(),
      });
    }
  }, [hasIdentity, syncCurrentStepsToServer]);

  // التهيئة الأولية
  useEffect(() => {
    if (Platform.OS === 'web') {
      setLedger(buildDemoLedger());
      void loadWalkingClubs();
      void loadLeaderboard(leaderboardPeriod);
      void loadGroups();
      return;
    }

    let cancelled = false;
    (async () => {
      const stored = await loadWalkingLedger(user?.id ?? null);
      if (cancelled) return;
      setLedger(stored);

      // فحص هادئ للصلاحيات عند بدء التشغيل دون إظهار نافذة النظام المنبثقة المفاجئة
      await checkMotionPermission();
      if (cancelled) return;

      void loadWalkingClubs();
      void loadLeaderboard(leaderboardPeriod);
      void loadStreakLeaderboard();
      void loadPersonalStats();
      void loadGroups();
      void loadHistory();
    })().catch(() => {});

    return () => {
      cancelled = true;
    };
  }, [checkMotionPermission, user?.id]);

  // مستمع المصدر النشط: HealthKit / Health Connect أو Pedometer كبديل
  useEffect(() => {
    if (trackingMode === 'none' || Platform.OS === 'web') return;

    let cancelled = false;
    let pedometerSub: { remove: () => void } | undefined;
    let intervalId: ReturnType<typeof setInterval> | undefined;

    androidLastRaw.current = 0;
    androidPrimed.current = false;

    (async () => {
      if (trackingMode === 'health') {
        await pullHealthToday({ fullHourlySweep: true });
        void pullHealthRecentDays();
        if (cancelled) return;

        intervalId = setInterval(() => {
          pullHealthToday({ fullHourlySweep: false }).catch(() => {});
        }, HEALTH_REFRESH_MS);
        return;
      }

      if (Platform.OS === 'ios') {
        await pullIosToday({ fullHourlySweep: true });
        void pullIosRecentDays();
      }
      if (cancelled) return;

      const pedSub = Pedometer.watchStepCount((result) => {
        const raw = Math.max(0, Math.round(result.steps));
        if (Platform.OS === 'android') {
          if (!androidPrimed.current) {
            androidPrimed.current = true;
            androidLastRaw.current = raw;
            return;
          }
          const delta = Math.min(MAX_DELTA_PER_EVENT, Math.max(0, raw - androidLastRaw.current));
          androidLastRaw.current = Math.max(androidLastRaw.current, raw);
          if (delta <= 0) return;
          const key = ksaDateKey();
          const curHour = ksaHour();
          setLedger((prev) => {
            const prevToday = prev.daily[key] ?? 0;
            const updated = prevToday + delta;
            applyToday(updated, { isAuthoritative: false });
            return recordHourlyDelta(prev, key, curHour, delta);
          });
        } else {
          scheduleIosPull();
        }
      });

      if (cancelled) {
        pedSub.remove();
        return;
      }
      pedometerSub = pedSub;

      if (Platform.OS === 'ios') {
        intervalId = setInterval(() => {
          pullIosToday({ fullHourlySweep: false }).catch(() => {});
        }, IOS_REFRESH_MS);
      }
    })().catch(() => {});

    return () => {
      cancelled = true;
      pedometerSub?.remove();
      if (intervalId) clearInterval(intervalId);
      if (iosPullTimer.current) clearTimeout(iosPullTimer.current);
      if (syncDebounceTimer.current) clearTimeout(syncDebounceTimer.current);
    };
  }, [
    trackingMode,
    pullHealthToday,
    pullHealthRecentDays,
    pullIosToday,
    pullIosRecentDays,
    scheduleIosPull,
    applyToday,
  ]);

  // 6. مراقبة دقيقة لمنتصف الليل (12:00:01 AM) لتصفير العداد وتحديث اليوم الجديد فوراً دون انتظار إعادة تشغيل التطبيق
  useEffect(() => {
    let midnightTimer: ReturnType<typeof setTimeout> | null = null;

    const scheduleMidnightWatcher = () => {
      const now = new Date();
      const ksaMidnightToday = ksaStartOfDay(now);
      let nextMidnight = new Date(ksaMidnightToday.getTime() + 24 * 3600 * 1000 + 2000);
      if (nextMidnight.getTime() - now.getTime() <= 0) {
        nextMidnight = new Date(nextMidnight.getTime() + 24 * 3600 * 1000);
      }
      const delayMs = Math.max(1000, nextMidnight.getTime() - now.getTime());

      midnightTimer = setTimeout(() => {
        if (syncDebounceTimer.current) {
          clearTimeout(syncDebounceTimer.current);
          syncDebounceTimer.current = null;
        }
        const newTodayKey = ksaDateKey();
        setCurrentDateKey(newTodayKey);
        iosFullHourlySyncedDateRef.current = null;

        // تفريغ الإحصائيات الشخصية السابقة لمنع بقاء أرقام الأمس في الواجهة
        setPersonalStats(null);

        // إعادة تهيئة المصدر لنقطة بداية اليوم الجديد
        if (trackingModeRef.current === 'health') {
          void pullHealthToday({ fullHourlySweep: true });
        } else if (Platform.OS === 'ios') {
          void pullIosToday({ fullHourlySweep: true });
        } else if (Platform.OS === 'android') {
          androidLastRaw.current = 0;
          androidPrimed.current = false;
        }

        // تحديث المتصدرين والسلسلة والإحصائيات وتفاصيل المجموعة لليوم الجديد فوراً
        void loadLeaderboard(leaderboardPeriod, undefined, undefined, true);
        void loadStreakLeaderboard(undefined, undefined, undefined, true);
        void loadPersonalStats(true);
        if (activeGroupDetailRef.current) {
          void loadGroupDetailsRef.current(activeGroupDetailRef.current.id, undefined, { inPlace: true });
        }

        scheduleMidnightWatcher();
      }, delayMs);
    };

    scheduleMidnightWatcher();

    return () => {
      if (midnightTimer) clearTimeout(midnightTimer);
    };
  }, [
    leaderboardPeriod,
    loadLeaderboard,
    loadStreakLeaderboard,
    loadPersonalStats,
    pullHealthToday,
    pullIosToday,
  ]);

  // الاستماع المستمر لعودة التطبيق من الخلفية أو شاشة الإعدادات وفحص تغير اليوم
  const lastActiveDateKeyRef = useRef(ksaDateKey());

  useEffect(() => {
    if (Platform.OS === 'web') return;

    const onAppStateGlobal = (s: AppStateStatus) => {
      // إلغاء أي مؤقت مجدول كان معلقاً في الذاكرة أثناء نوم التطبيق
      if (syncDebounceTimer.current) {
        clearTimeout(syncDebounceTimer.current);
        syncDebounceTimer.current = null;
      }

      if (s === 'active') {
        void (async () => {
          const todayK = ksaDateKey();
          const hasDayChanged = todayK !== lastActiveDateKeyRef.current;
          lastActiveDateKeyRef.current = todayK;

          if (hasDayChanged) {
            setCurrentDateKey(todayK);
            iosFullHourlySyncedDateRef.current = null;
            setPersonalStats(null);
            if (Platform.OS === 'android') {
              androidLastRaw.current = 0;
              androidPrimed.current = false;
            }
          }

          const granted = await recheckMotionPermission();
          if (granted) {
            if (trackingModeRef.current === 'health') {
              await pullHealthToday({ fullHourlySweep: true });
            } else if (Platform.OS === 'ios') {
              await pullIosToday({ fullHourlySweep: true });
            }
          }
          const curSteps = persist.current.daily[todayK] ?? 0;
          const remoteTodaySteps = personalStatsRef.current?.today_steps ?? 0;
          const allowZero =
            isAuthoritativeSourceRef.current && curSteps === 0 && remoteTodaySteps > 0;
          if ((curSteps > 0 || allowZero) && hasIdentity) {
            await syncCurrentStepsToServer(curSteps, {
              isAuthoritative: isAuthoritativeSourceRef.current,
              source: activeSourceRef.current ?? getDeviceWalkingSource(),
              allowZero,
            });
          }
          await Promise.all([
            loadLeaderboard(leaderboardPeriod, undefined, undefined, true),
            loadPersonalStats(true),
          ]);
        })();
      }
    };

    const appStateSub = AppState.addEventListener('change', onAppStateGlobal);
    return () => {
      appStateSub.remove();
    };
  }, [
    recheckMotionPermission,
    pullHealthToday,
    pullIosToday,
    hasIdentity,
    syncCurrentStepsToServer,
    loadLeaderboard,
    leaderboardPeriod,
    loadPersonalStats,
  ]);

  // الحسابات المشتقة للخطوات والمسافة والسعرات (مرتبطة بـ currentDateKey لضمان التصفير اللحظي التلقائي)
  const todaySteps = useMemo(() => {
    const local = Math.max(0, ledger.daily[currentDateKey] ?? 0);
    const remoteDateKey = personalStats?.date ? cleanDateYMD(personalStats.date) : '';
    const isRemoteForToday = remoteDateKey ? remoteDateKey === currentDateKey : true;
    const rawRemote = isRemoteForToday ? (personalStats?.today_steps ?? 0) : 0;
    const remote = Number.isFinite(rawRemote) ? Math.max(0, rawRemote) : 0;

    // المصدر الموثوق (صحتي/الحساس) هو الحكم لرقم اليوم: قراءة الحساس تُعرض كما هي
    // ولا تُرفع لرقم السيرفر أبداً — هذا يمنع التصاق رقم أمس المسمم في العرض.
    if (isAuthoritativeSource && lastSensorDayRef.current === currentDateKey) {
      return local;
    }

    // حماية التصاعد الحتمي (Monotonic Invariant): خطوات اليوم تعكس دائماً أعلى قيمة مؤكدة لليوم
    return Math.max(local, remote);
  }, [ledger, personalStats, currentDateKey, isAuthoritativeSource]);

  const monthlySteps = useMemo(() => {
    const local = sumMonth(ledger, new Date());
    const remote = personalStats?.monthly_steps ?? 0;
    return Math.max(local, remote);
  }, [ledger, personalStats?.monthly_steps]);

  const totalSteps = useMemo(() => {
    const local = totalTracked(ledger);
    const remote = personalStats?.all_time_steps ?? 0;
    return Math.max(local, remote);
  }, [ledger, personalStats?.all_time_steps]);

  const progress = Math.min(1, dailyGoal > 0 ? todaySteps / dailyGoal : 0);
  const last7Days = useMemo(() => {
    const effective = isAuthoritativeSource
      ? setAuthoritativeDaySteps(ledger, currentDateKey, todaySteps)
      : mergeToday(ledger, currentDateKey, todaySteps);
    return last7DayBars(effective, new Date());
  }, [ledger, currentDateKey, todaySteps, isAuthoritativeSource]);

  const distanceKm = useMemo(() => Number((todaySteps * 0.00076).toFixed(2)), [todaySteps]);
  const calories = useMemo(() => Math.round(todaySteps * 0.04), [todaySteps]);
  const activeMinutes = useMemo(() => Math.round(todaySteps / 100), [todaySteps]);

  const currentStreak = personalStats?.streak_10k ?? personalStats?.current_streak ?? (todaySteps >= dailyGoal ? 1 : 0);
  const longestStreak = personalStats?.longest_streak ?? currentStreak;

  // 1️⃣ ترتيبي بين أفراد العائلة اليوم
  const familyRankToday = useMemo(() => {
    if (leaderboard?.viewer_rank?.rank != null && leaderboard.viewer_rank.rank > 0) {
      return leaderboard.viewer_rank.rank;
    }
    if (personalStats?.today_rank != null && personalStats.today_rank > 0) {
      return personalStats.today_rank;
    }
    return null;
  }, [leaderboard?.viewer_rank?.rank, personalStats?.today_rank]);

  // إجمالي أفراد العائلة المشاركين اليوم
  const familyTotalToday = useMemo(() => {
    const total = leaderboard?.pagination?.total ?? personalStats?.today_total_participants;
    if (total != null && total > 0) return total;
    if (leaderboard?.top && leaderboard.top.length > 0) return leaderboard.top.length;
    return 0;
  }, [leaderboard?.pagination?.total, personalStats?.today_total_participants, leaderboard?.top]);

  // 2️⃣ أفضل يوم (أعلى عدد خطوات وتاريخه)
  const { bestDaySteps, bestDayDate } = useMemo(
    () =>
      computeBestDay({
        personalStats,
        historyRecords,
        ledger,
        todaySteps,
        currentDateKey,
      }),
    [personalStats, historyRecords, ledger, todaySteps, currentDateKey]
  );

  // 3️⃣ الأيام النشطة: عدد الأيام التي تجاوز فيها 5,000 خطوة
  const activeDaysCount = useMemo(
    () =>
      computeActiveDaysCount({
        personalStats,
        historyRecords,
        ledger,
        todaySteps,
        currentDateKey,
      }),
    [personalStats, historyRecords, ledger, todaySteps, currentDateKey]
  );

  // 4️⃣ المتوسط اليومي خلال آخر 30 يوم
  const avgDaily30d = useMemo(
    () =>
      computeAvgDaily30d({
        personalStats,
        historyRecords,
        ledger,
        todaySteps,
        currentDateKey,
      }),
    [personalStats, historyRecords, ledger, todaySteps, currentDateKey]
  );

  // 5️⃣ سلسلة الاستمرارية: عدد الأيام المتتالية التي حقق فيها الهدف (+10,000 خطوة يومياً)
  const streak10k = useMemo(
    () =>
      computeStreak10k({
        personalStats,
        historyRecords,
        ledger,
        todaySteps,
        currentDateKey,
      }),
    [personalStats, historyRecords, ledger, todaySteps, currentDateKey]
  );

  return {
    // Ledger & Raw Step Store
    ledger,

    // Basic Steps & Metrics
    dailyGoal,
    todaySteps,
    monthlySteps,
    totalSteps,
    progress,
    distanceKm,
    calories,
    activeMinutes,
    last7Days,
    currentStreak,
    longestStreak,
    isSyncing,
    lastSyncTime,

    // Enriched Achievement Metrics (13, 14, 15, 16, 18 & Family Rank)
    familyRankToday,
    familyTotalToday,
    bestDaySteps,
    bestDayDate,
    activeDaysCount,
    avgDaily30d,
    streak5k: personalStats?.streak_5k ?? 0,
    streak10k,
    streak20k: personalStats?.streak_20k ?? 0,
    clubStreaks: personalStats?.club_streaks ?? {},
    refreshClubs: loadWalkingClubs,

    // Leaderboard & Clubs
    leaderboardType,
    setLeaderboardType,
    walkingClubs,
    leaderboardCategory,
    setLeaderboardCategory: changeLeaderboardCategory,
    streakLeaderboard,
    loadingStreakLeaderboard,
    loadingMoreStreakLeaderboard,
    hasMoreStreakLeaderboard,
    loadMoreStreakLeaderboard,
    loadStreakLeaderboard,
    leaderboard,
    leaderboardPeriod,
    setLeaderboardPeriod: (p: LeaderboardPeriod) => {
      setLeaderboardPeriod(p);
      void loadLeaderboard(p, leaderboardGenderFilter, leaderboardCategory);
    },
    leaderboardGenderFilter,
    setLeaderboardGenderFilter: changeLeaderboardGenderFilter,
    loadingLeaderboard,
    loadingMoreLeaderboard,
    hasMoreLeaderboard,
    loadMoreLeaderboard,
    walkingPrivacyMode,
    updateLeaderboardPrivacy,

    // Personal Stats & History
    personalStats,
    loadingStats,
    historyRecords,
    loadingHistory,

    // Groups & Challenges
    groups,
    loadingGroups,
    activeGroupDetail,
    groupLeaderboard,
    loadGroupDetails,
    loadGroupLeaderboardPeriod,
    createGroup: handleCreateGroup,
    joinGroup: handleJoinGroup,
    joinGroupByCode: handleJoinGroupByCode,
    inviteMember: handleInviteMember,
    leaveGroup: handleLeaveGroup,
    removeMember: handleLeaveGroup,
    deleteGroup: handleDeleteGroup,
    refreshGroups: loadGroups,

    // Permissions & Hardware
    isDemoMode: Platform.OS === 'web',
    activeSource,
    activeSourceLabel: getWalkingSourceLabel(activeSource),
    isHealthSource: trackingMode === 'health',
    isHealthConnect: trackingMode === 'health' && activeSource === 'health_connect',
    isPedometerFallback: Platform.OS === 'android' && trackingMode === 'device',
    motionReady,
    isPermissionChecked,
    trackingUnavailable,
    permissionDenied,
    permissionStatus,
    canRequestMotionAgain,
    motionRequiresSettings,
    checkMotionPermission,
    requestMotionPermission,
    recheckMotionPermission,
    refreshAll,
    syncNow,
    syncNowWithFeedback,
    loadHourlyForDate,
    userPersonId: user?.id,
  };
}

export type UseWalkingResult = ReturnType<typeof useWalking>;
