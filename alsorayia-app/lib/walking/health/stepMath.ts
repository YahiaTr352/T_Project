import {
  ksaDateKey,
  ksaHour,
  ksaStartOfDay,
  MAX_STEPS_PER_HOUR,
  startOfDay,
} from '@/lib/walking/ledger';

const DAY_MS = 24 * 60 * 60 * 1000;

export interface RawStepBucket {
  start: string;
  steps: number;
}

export function sanitizeSteps(value: unknown, max?: number): number {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric <= 0) return 0;
  const rounded = Math.round(numeric);
  return max != null ? Math.min(max, rounded) : rounded;
}

/**
 * تحويل تاريخ الشريحة الزمني بأمان حتى عند غياب خانة الثواني في محركات JS القديمة
 */
export function parseBucketDate(startStr: string): Date {
  if (!startStr) return new Date(NaN);
  let normalized = startStr;
  const missingSecondsMatch = /^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})([+-]\d{2}:?\d{2}|Z)$/.exec(startStr);
  if (missingSecondsMatch) {
    normalized = `${missingSecondsMatch[1]}:00${missingSecondsMatch[2]}`;
  }
  return new Date(normalized);
}

export function aggregateHourlyBuckets(
  buckets: RawStepBucket[],
  targetDayKey: string,
  authoritativeTotal?: number | null
): { total: number; hourly: Record<number, number> } {
  const rawHourly: Record<number, number> = {};
  let rawTotalFromBuckets = 0;

  for (const bucket of buckets) {
    const date = parseBucketDate(bucket.start);
    if (Number.isNaN(date.getTime())) continue;
    if (ksaDateKey(date) !== targetDayKey) continue;

    const numericSteps = Number(bucket.steps);
    if (!Number.isFinite(numericSteps) || numericSteps <= 0) continue;

    const hour = ksaHour(date);
    rawHourly[hour] = (rawHourly[hour] ?? 0) + numericSteps;
    rawTotalFromBuckets += numericSteps;
  }

  const hourly: Record<number, number> = {};
  for (const [hourStr, rawVal] of Object.entries(rawHourly)) {
    const rounded = Math.min(MAX_STEPS_PER_HOUR, Math.round(rawVal));
    if (rounded > 0) {
      hourly[Number(hourStr)] = rounded;
    }
  }

  const totalFromBuckets = Math.round(rawTotalFromBuckets);
  const effectiveTotal =
    authoritativeTotal != null && Number.isFinite(authoritativeTotal) && authoritativeTotal >= 0
      ? Math.max(Math.round(authoritativeTotal), totalFromBuckets)
      : totalFromBuckets;

  return { total: effectiveTotal, hourly };
}

/**
 * خصم الخطوات اليدوية من شرائح الساعات مع الحفاظ على الكسور العشرية قبل التقريب النهائي،
 * وتوزيع أي باقٍ عابر للساعات على بقية شرائح نفس اليوم لضمان عدم بقاء أي خطوات يدوية في المجموع.
 */
export function subtractManualFromBuckets(
  buckets: RawStepBucket[],
  manualHourly: Record<number, number>,
  manualTotal: number,
  targetDayKey: string
): RawStepBucket[] {
  if (manualTotal <= 0) return buckets;

  const remainingByHour: Record<number, number> = { ...manualHourly };
  let deductedTotal = 0;

  const firstPass = buckets.map((bucket) => {
    const date = parseBucketDate(bucket.start);
    if (Number.isNaN(date.getTime()) || ksaDateKey(date) !== targetDayKey) return bucket;
    const numeric = Number(bucket.steps);
    if (!Number.isFinite(numeric) || numeric <= 0) return bucket;

    const hour = ksaHour(date);
    const hourManual = remainingByHour[hour] ?? 0;
    if (hourManual <= 0) return bucket;

    const deduct = Math.min(numeric, hourManual);
    remainingByHour[hour] = hourManual - deduct;
    deductedTotal += deduct;
    return { ...bucket, steps: Math.max(0, numeric - deduct) };
  });

  let remainingOverall = Math.max(0, manualTotal - deductedTotal);
  if (remainingOverall <= 0) return firstPass;

  return firstPass.map((bucket) => {
    if (remainingOverall <= 0) return bucket;
    const date = parseBucketDate(bucket.start);
    if (Number.isNaN(date.getTime()) || ksaDateKey(date) !== targetDayKey) return bucket;
    const numeric = Number(bucket.steps);
    if (!Number.isFinite(numeric) || numeric <= 0) return bucket;

    const deduct = Math.min(numeric, remainingOverall);
    remainingOverall -= deduct;
    return { ...bucket, steps: Math.max(0, numeric - deduct) };
  });
}

export function aggregateDailyBuckets(buckets: RawStepBucket[]): Record<string, number> {
  const daily: Record<string, number> = {};

  for (const bucket of buckets) {
    const date = parseBucketDate(bucket.start);
    if (Number.isNaN(date.getTime())) continue;

    const steps = sanitizeSteps(bucket.steps);
    if (steps <= 0) continue;

    const key = ksaDateKey(date);
    daily[key] = (daily[key] ?? 0) + steps;
  }

  return daily;
}

export function resolveDayRange(
  day: Date,
  now: Date = new Date(),
  options?: { fullDay?: boolean; useLocalTimezone?: boolean }
): { start: Date; end: Date; dayKey: string } {
  const start = options?.useLocalTimezone ? startOfDay(day) : ksaStartOfDay(day);
  const dayKey = ksaDateKey(day);
  const isToday = dayKey === ksaDateKey(now);
  const endOfDay = new Date(start.getTime() + DAY_MS - 1000);
  const end = options?.fullDay || !isToday ? endOfDay : now;
  return { start, end, dayKey };
}

export function resolveRecentRange(
  days: number,
  now: Date = new Date()
): { start: Date; end: Date } {
  const safeDays = Math.max(1, Math.floor(days));
  const firstDay = new Date(now.getTime() - (safeDays - 1) * DAY_MS);
  return { start: ksaStartOfDay(firstDay), end: now };
}
