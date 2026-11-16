import * as SecureStore from 'expo-secure-store';
import { appStorage } from '@/lib/storage/appStorage';

export const LEGACY_WALKING_LEDGER_KEY = 'walking_step_ledger_v1';
export const WALKING_LEDGER_KEY = 'walking_step_ledger_v2';

/**
 * سقف عرض/توزيع الخطوات في الساعة الواحدة داخل الرسم البياني.
 * رفع السقف ليتسع للعدائين ومشاركة الهواتف في سباقات المشي والجري.
 */
export const MAX_STEPS_PER_HOUR = 100000;

export type WalkingLedger = {
  daily: Record<string, number>;
  hourly?: Record<string, Record<number, number>>;
  lastServerDate?: string;
  resetId?: number | null;
  ownerPersonId?: number | null;
};

export function dateKey(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export function startOfDay(d: Date): Date {
  const x = new Date(d);
  x.setHours(0, 0, 0, 0);
  return x;
}

function sanitizeDailyMap(rawDaily: Record<string, unknown>): Record<string, number> {
  const clean: Record<string, number> = {};
  for (const [k, v] of Object.entries(rawDaily)) {
    const n = Number(v);
    if (Number.isFinite(n) && n >= 0) {
      clean[k] = Math.round(n);
    }
  }
  return clean;
}

function sanitizeHourlyRecord(
  rawHourly?: Record<string, Record<number, number>>
): Record<string, Record<number, number>> {
  if (!rawHourly || typeof rawHourly !== 'object') return {};
  const clean: Record<string, Record<number, number>> = {};
  for (const [dayStr, hoursObj] of Object.entries(rawHourly)) {
    if (!hoursObj || typeof hoursObj !== 'object') continue;
    const dayHours: Record<number, number> = {};
    for (const [hStr, stepsVal] of Object.entries(hoursObj)) {
      const h = Number(hStr);
      const s = Number(stepsVal);
      if (!Number.isNaN(h) && h >= 0 && h < 24 && !Number.isNaN(s) && s > 0) {
        dayHours[h] = Math.min(MAX_STEPS_PER_HOUR, Math.round(s));
      }
    }
    clean[dayStr] = dayHours;
  }
  return clean;
}

export async function clearWalkingLedger(): Promise<void> {
  try {
    await Promise.all([
      SecureStore.deleteItemAsync(LEGACY_WALKING_LEDGER_KEY).catch(() => {}),
      appStorage.removeItem(WALKING_LEDGER_KEY),
    ]);
  } catch {
    // Ignore storage errors
  }
}

export async function loadWalkingLedger(currentPersonId?: number | null): Promise<WalkingLedger> {
  try {
    // مسح الكاش القديم (v1) نهائياً لضمان إزالة أي أرقام متراكمة أو مضخمة من الإصدار السابق
    await SecureStore.deleteItemAsync(LEGACY_WALKING_LEDGER_KEY).catch(() => {});

    const raw = await appStorage.getItem(WALKING_LEDGER_KEY);
    if (!raw) {
      return { daily: {}, hourly: {}, ownerPersonId: currentPersonId ?? null };
    }
    const parsed = JSON.parse(raw) as WalkingLedger;
    if (!parsed || typeof parsed !== 'object' || typeof parsed.daily !== 'object') {
      return { daily: {}, hourly: {}, ownerPersonId: currentPersonId ?? null };
    }

    const storedOwner = typeof parsed.ownerPersonId === 'number' ? parsed.ownerPersonId : null;
    if (currentPersonId != null && storedOwner != null && storedOwner !== currentPersonId) {
      // المستخدم تغير على نفس الجهاز: نصفر السجل المحلي فوراً ونربطه بالمستخدم الجديد
      const fresh: WalkingLedger = { daily: {}, hourly: {}, ownerPersonId: currentPersonId };
      await saveWalkingLedger(fresh);
      return fresh;
    }

    return {
      daily: sanitizeDailyMap(parsed.daily as Record<string, unknown>),
      lastServerDate: typeof parsed.lastServerDate === 'string' ? parsed.lastServerDate : undefined,
      resetId: typeof parsed.resetId === 'number' ? parsed.resetId : null,
      ownerPersonId: currentPersonId ?? storedOwner,
      hourly: sanitizeHourlyRecord(parsed.hourly),
    };
  } catch {
    return { daily: {}, hourly: {}, ownerPersonId: currentPersonId ?? null };
  }
}

export async function saveWalkingLedger(ledger: WalkingLedger): Promise<void> {
  try {
    await appStorage.setItem(WALKING_LEDGER_KEY, JSON.stringify(ledger));
  } catch {
    // SecureStore size / availability — skip silently
  }
}

export function sumMonth(ledger: WalkingLedger, ref: Date): number {
  const y = ref.getFullYear();
  const m = String(ref.getMonth() + 1).padStart(2, '0');
  const prefix = `${y}-${m}-`;
  let sum = 0;
  for (const [k, v] of Object.entries(ledger.daily)) {
    if (k.startsWith(prefix)) sum += v;
  }
  return sum;
}

export function totalTracked(ledger: WalkingLedger): number {
  return Object.values(ledger.daily).reduce((a, b) => a + b, 0);
}

export type DayBarPoint = {
  key: string;
  label: string;
  value: number;
  isToday: boolean;
  dayName: string;
};

const ARABIC_DAYS_SHORT = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

/**
 * آخر 7 أيام تقويمية حتى تاريخ `ref` (شامل اليوم).
 * تبدأ بالمصفوفة من اليوم (الفهرس 0) نزولاً للأيام السابقة لترتيب RTL العربي الطبيعي (اليوم على اليمين).
 */
export function last7DayBars(ledger: WalkingLedger, ref: Date): DayBarPoint[] {
  const out: DayBarPoint[] = [];
  for (let i = 0; i <= 6; i += 1) {
    const d = new Date(ref);
    d.setDate(d.getDate() - i);
    const isToday = i === 0;
    const key = isToday ? ksaDateKey(d) : dateKey(d);
    const dayName = isToday ? 'اليوم' : i === 1 ? 'أمس' : ARABIC_DAYS_SHORT[d.getDay()];
    const label = `${d.getDate()}`;
    const value = ledger.daily[key] ?? (isToday ? (ledger.daily[dateKey(d)] ?? 0) : 0);
    out.push({
      key,
      label,
      value,
      isToday,
      dayName,
    });
  }
  return out;
}

/**
 * تسجيل قراءة الحساس/صحتي المعتمدة (Authoritative Sensor Reading).
 * تضمن استقرار القراءة المعتمدة بمبدأ التصاعد الحتمي (Monotonic Non-Decreasing):
 * خطوات اليوم الواحد تتصاعد فقط (Math.max) ولا تتراجع إطلاقاً بسبب أي خلل مؤقت
 * في الحساس أو الشبكة، ما لم يُطلب فرض الاستبدال صراحة (forceOverwrite)
 * عند التصفير الإداري أو الانتقال ليوم جديد.
 */
export function setAuthoritativeDaySteps(
  ledger: WalkingLedger,
  dayKey: string,
  sensorSteps: number,
  options?: { forceOverwrite?: boolean }
): WalkingLedger {
  const sanitized = Number.isFinite(sensorSteps) ? Math.max(0, Math.round(sensorSteps)) : 0;
  const prev = ledger.daily[dayKey];
  const target = options?.forceOverwrite ? sanitized : Math.max(prev ?? 0, sanitized);
  if (prev === target && prev !== undefined) return ledger;
  return {
    ...ledger,
    daily: { ...ledger.daily, [dayKey]: target },
  };
}

export function mergeToday(ledger: WalkingLedger, todayKey: string, steps: number): WalkingLedger {
  const prev = ledger.daily[todayKey] ?? 0;
  const clampedIncoming = Number.isFinite(steps) ? Math.max(0, Math.round(steps)) : 0;
  const next = Math.max(prev, clampedIncoming);
  if (next === prev) return ledger;
  return {
    ...ledger,
    daily: { ...ledger.daily, [todayKey]: next },
  };
}

export function recordHourlyDelta(
  ledger: WalkingLedger,
  todayKey: string,
  hour: number,
  deltaSteps: number
): WalkingLedger {
  if (deltaSteps <= 0) return ledger;
  const currentDaily = ledger.daily[todayKey] ?? 0;
  const currentHourlyMap = ledger.hourly?.[todayKey] ? { ...ledger.hourly[todayKey] } : {};
  const currentHourSteps = currentHourlyMap[hour] ?? 0;

  currentHourlyMap[hour] = Math.min(MAX_STEPS_PER_HOUR, currentHourSteps + deltaSteps);

  return {
    ...ledger,
    daily: {
      ...ledger.daily,
      [todayKey]: currentDaily + deltaSteps,
    },
    hourly: {
      ...(ledger.hourly ?? {}),
      [todayKey]: currentHourlyMap,
    },
  };
}

/**
 * تحديث خريطة توزيع الساعات ليوم محدد دون المساس بإجمالي خطوات اليوم (`ledger.daily`).
 * إجمالي اليوم يُؤخذ حصراً من استعلام اليوم الكامل للحساس وليس من جمع الساعات.
 */
export function mergeHourly(
  ledger: WalkingLedger,
  dateKeyStr: string,
  hourlyMap: Record<number, number>,
  replaceAll: boolean = true
): WalkingLedger {
  const baseHourly = replaceAll ? {} : (ledger.hourly?.[dateKeyStr] ?? {});
  const nextHourly: Record<number, number> = { ...baseHourly };

  for (const [hStr, val] of Object.entries(hourlyMap)) {
    const h = Number(hStr);
    if (Number.isNaN(h) || h < 0 || h >= 24) continue;
    const sanitized = Math.min(MAX_STEPS_PER_HOUR, Math.max(0, Math.round(val)));
    if (sanitized > 0) {
      nextHourly[h] = sanitized;
    } else {
      delete nextHourly[h];
    }
  }

  return {
    ...ledger,
    hourly: {
      ...(ledger.hourly ?? {}),
      [dateKeyStr]: nextHourly,
    },
  };
}

export function ksaDateKey(d: Date = new Date()): string {
  try {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Riyadh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(d);
    const get = (t: string) => parts.find((x) => x.type === t)?.value;
    const y = get('year');
    const m = get('month');
    const day = get('day');
    if (y && m && day) return `${y}-${m}-${day}`;
  } catch {
  }
  return dateKey(d);
}

export function ksaHour(now: Date = new Date()): number {
  try {
    const h = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Riyadh',
      hour: 'numeric',
      hour12: false,
    }).format(now);
    const n = Number(h);
    if (!isNaN(n)) return n % 24;
  } catch {
  }
  return now.getHours();
}

export function ksaStartOfDay(now: Date = new Date()): Date {
  try {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Riyadh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(now);
    const get = (t: string) => parts.find((x) => x.type === t)?.value;
    const y = Number(get('year'));
    const m = Number(get('month'));
    const d = Number(get('day'));
    if (y && m && d) return new Date(Date.UTC(y, m - 1, d, 0, 0, 0, 0) - 3 * 3600 * 1000);
  } catch {
  }
  return startOfDay(now);
}

export function invalidateOnNewDay(
  ledger: WalkingLedger,
  serverDate: string | undefined,
  resetId: number | null | undefined,
  remoteTodaySteps: number
): WalkingLedger {
  const prevServerDate = ledger.lastServerDate;
  const prevResetId = ledger.resetId ?? null;
  const nextResetId = resetId ?? null;

  const dayChanged = !!serverDate && prevServerDate !== undefined && prevServerDate !== serverDate;
  const resetChanged = nextResetId !== null && prevResetId !== null && nextResetId !== prevResetId;

  const markersUpdated: WalkingLedger = {
    ...ledger,
    lastServerDate: serverDate ?? prevServerDate,
    resetId: nextResetId,
  };

  if (!serverDate) return markersUpdated;

  const local = ledger.daily[serverDate] ?? 0;
  // التصفير والتحديث الحتمي عند تغير اليوم التقويمي فعلياً أو صدور أمر تصفير إداري جديد
  const needsWipe = dayChanged || resetChanged;

  if (!needsWipe) return markersUpdated;

  const clampedRemote = Number.isFinite(remoteTodaySteps)
    ? Math.max(0, Math.round(remoteTodaySteps))
    : 0;
  const daily = { ...ledger.daily, [serverDate]: clampedRemote };
  const hourly = ledger.hourly ? { ...ledger.hourly } : undefined;
  if (hourly) delete hourly[serverDate];

  return { ...markersUpdated, daily, hourly };
}

export function capToday(ledger: WalkingLedger, todayKey: string, maxAllowed: number): WalkingLedger {
  const cur = ledger.daily[todayKey] ?? 0;
  const target = Number.isFinite(maxAllowed) ? Math.max(0, Math.round(maxAllowed)) : 0;
  if (cur <= target) return ledger;
  const hourly = ledger.hourly ? { ...ledger.hourly } : undefined;
  if (hourly) delete hourly[todayKey];
  return {
    ...ledger,
    daily: { ...ledger.daily, [todayKey]: target },
    hourly,
  };
}