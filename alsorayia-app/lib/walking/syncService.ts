import {
  syncTodaySteps,
  type SyncStepsPayload,
  type WalkingSource,
} from '@/lib/api/walkingApi';
import {
  invalidateOnNewDay,
  ksaDateKey,
  ksaHour,
  ksaStartOfDay,
  loadWalkingLedger,
  mergeHourly,
  mergeToday,
  saveWalkingLedger,
  setAuthoritativeDaySteps,
} from '@/lib/walking/ledger';
import { getHealthProvider } from '@/lib/walking/health';
import { logger } from '@/lib/utils/logger';
import { Pedometer } from 'expo-sensors';
import { Platform } from 'react-native';

// ==========================================
// حالة المزامنة اللحظية في الذاكرة لتفادي التكرار
// ==========================================
let lastSyncedDateKey: string | null = null;
let lastSyncedSteps: number = 0;
let lastSyncedTimestamp: number = 0;
let isSyncInProgress: boolean = false;

/**
 * تجهيز بيانات حمولة المزامنة بصيغة متوافقة ومعيارية مع الـ API
 */
export function buildSyncStepsPayload(
  stepsCount: number,
  options?: { isAuthoritative?: boolean; source?: WalkingSource; targetDate?: string }
): SyncStepsPayload {
  const numericSteps = Number.isFinite(stepsCount) ? stepsCount : 0;
  const sanitizedSteps = Math.max(0, Math.round(numericSteps));
  const todayStr = options?.targetDate || ksaDateKey();
  const distanceKm = Number((sanitizedSteps * 0.00076).toFixed(2));
  const durationMin = Math.round(sanitizedSteps / 100);
  const calories = Math.round(sanitizedSteps * 0.04);
  const source =
    options?.source ??
    (Platform.OS === 'ios' || Platform.OS === 'android' ? 'device' : 'manual');

  return {
    date: todayStr,
    steps: sanitizedSteps,
    distance_km: distanceKm,
    duration_minutes: durationMin,
    calories,
    source,
    is_authoritative: options?.isAuthoritative ?? false,
  };
}

/**
 * تحديث حالة المزامنة عند نجاح المزامنة من أي مصدر (مثل useWalking)
 */
export function recordWalkingSyncSuccess(stepsCount: number) {
  lastSyncedDateKey = ksaDateKey();
  lastSyncedSteps = stepsCount;
  lastSyncedTimestamp = Date.now();
}

/**
 * إعادة ضبط حالة المزامنة عند تسجيل الخروج
 */
export function resetWalkingSyncState() {
  lastSyncedDateKey = null;
  lastSyncedSteps = 0;
  lastSyncedTimestamp = 0;
  isSyncInProgress = false;
}

/**
 * إجراء مزامنة هادئة تماماً لخطوات اليوم في الخلفية
 * - لا تطلب أذونات بشكل منبثق مزعج للمستخدم إذا لم تكن ممنوحة مسبقاً.
 * - تقرأ من حساس الحركة (CoreMotion / Pedometer) إن وُجد.
 * - على iOS تعتمد قراءة الحساس بشكل حتمي ومباشر (Authoritative) لمطابقة تطبيق "صحتي".
 * - تمنع الاستدعاءات المتكررة غير الضرورية في فترات زمنية قصيرة.
 */
export async function performSilentWalkingSync(options?: {
  force?: boolean;
}): Promise<{ synced: boolean; steps: number }> {
  if (Platform.OS === 'web') {
    return { synced: false, steps: 0 };
  }

  if (isSyncInProgress) {
    return { synced: false, steps: lastSyncedSteps };
  }

  const now = new Date();
  const todayKey = ksaDateKey(now);

  try {
    isSyncInProgress = true;

    let sensorSteps: number | null = null;
    let sensorHourly: Record<number, number> | null = null;
    let source: WalkingSource =
      Platform.OS === 'ios' || Platform.OS === 'android' ? 'device' : 'manual';
    let isAuthoritativeSensorRead = false;

    let healthAvailable = false;
    const health = getHealthProvider();
    if (health) {
      healthAvailable = true;
      const permission = await health.getPermissionState().catch(() => null);
      if (permission?.status === 'unavailable') {
        healthAvailable = false;
      } else if (permission?.status === 'granted') {
        const healthDay = await health.readDay(now).catch(() => null);
        if (healthDay) {
          sensorSteps = healthDay.total;
          sensorHourly = healthDay.hourly;
          source = health.source;
          isAuthoritativeSensorRead = true;
        }
      }
    }

    if (sensorSteps == null && healthAvailable) {
      return { synced: false, steps: 0 };
    }

    if (sensorSteps == null) {
      const isAvailable = await Pedometer.isAvailableAsync().catch(() => false);
      if (isAvailable) {
        const perm = await Pedometer.getPermissionsAsync().catch(() => null);
        if (perm?.granted) {
          try {
            const start = ksaStartOfDay(now);
            const res = await Pedometer.getStepCountAsync(start, now);
            if (typeof res?.steps === 'number' && !isNaN(res.steps)) {
              sensorSteps = Math.max(0, Math.round(res.steps));
              source = 'device';
              isAuthoritativeSensorRead = Platform.OS === 'ios';
            }
          } catch {
            // بعض أجهزة الأندرويد لا توفر قراءة تراكمية عبر getStepCountAsync
          }
        }
      }
    }

    let ledger = await loadWalkingLedger();
    const prevLedger = ledger;
    const prevTodaySteps = prevLedger.daily[todayKey] ?? 0;
    let mergedLocally = false;

    if (sensorSteps != null) {
      if (isAuthoritativeSensorRead) {
        // أول مزامنة لليوم الجديد تكتب قراءة الحساس حتمياً (forceOverwrite) بدل max()،
        // حتى لو اللدجر المحلي مسمم برقم أمس من دمج سابق — القراءة الموثوقة هي الحكم.
        const isFirstSyncForDay = lastSyncedDateKey !== todayKey;
        ledger = setAuthoritativeDaySteps(
          ledger,
          todayKey,
          sensorSteps,
          isFirstSyncForDay ? { forceOverwrite: true } : undefined
        );
      } else if (sensorSteps > 0) {
        ledger = mergeToday(ledger, todayKey, sensorSteps);
      }
      if (sensorHourly && Object.keys(sensorHourly).length > 0) {
        ledger = mergeHourly(ledger, todayKey, sensorHourly, isAuthoritativeSensorRead);
      } else if (isAuthoritativeSensorRead && sensorSteps === 0) {
        ledger = mergeHourly(ledger, todayKey, {}, true);
      }
      mergedLocally = ledger !== prevLedger;
      if (mergedLocally) await saveWalkingLedger(ledger);
    }

    const stepsToSync = ledger.daily[todayKey] ?? 0;
    const needsAuthoritativeZeroOverwrite =
      isAuthoritativeSensorRead &&
      sensorSteps === 0 &&
      (prevTodaySteps > 0 || (lastSyncedDateKey === todayKey && lastSyncedSteps > 0));

    if (stepsToSync <= 0 && !needsAuthoritativeZeroOverwrite) {
      return { synced: false, steps: 0 };
    }

    // 3. منع الإرسال المتكرر إذا كانت نفس الخطوات ونفس اليوم ولم يمر دقيقتان
    const nowTimestamp = Date.now();
    const timeSinceLastSync = nowTimestamp - lastSyncedTimestamp;
    const isSameDay = lastSyncedDateKey === todayKey;
    const isSameSteps = lastSyncedSteps === stepsToSync;

    if (!options?.force && isSameDay && isSameSteps && timeSinceLastSync < 2 * 60 * 1000) {
      return { synced: false, steps: stepsToSync };
    }

    // 4. إرسال المزامنة إلى السيرفر مع ربط تاريخ اليوم صراحة (Target Date Binding)
    const payload = buildSyncStepsPayload(stepsToSync, {
      isAuthoritative: isAuthoritativeSensorRead,
      source,
      targetDate: todayKey,
    });
    const res = await syncTodaySteps(payload);

    if (res?.success) {
      const srv = res.server;
      if (srv?.date_today) {
        const confirmedServerSteps = res.data?.steps ?? stepsToSync;
        ledger = invalidateOnNewDay(ledger, srv.date_today, srv.reset_id ?? null, confirmedServerSteps);
        await saveWalkingLedger(ledger);
      }
      recordWalkingSyncSuccess(stepsToSync);
      return { synced: true, steps: stepsToSync };
    }

    const stepErrs = res?.errors?.['steps'];
    if (res && stepErrs && stepErrs.length > 0 && mergedLocally && ksaHour() < 6) {
      await saveWalkingLedger(prevLedger);
      return { synced: false, steps: prevLedger.daily[todayKey] ?? 0 };
    }

    return { synced: false, steps: stepsToSync };
  } catch (error) {
    logger.warn('[syncService] performSilentWalkingSync error:', error);
    return { synced: false, steps: 0 };
  } finally {
    isSyncInProgress = false;
  }
}

