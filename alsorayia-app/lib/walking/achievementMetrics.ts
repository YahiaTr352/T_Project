import type { PersonalStats, WalkingRecord } from '@/lib/api/walkingApi';
import { cleanDateYMD } from '@/lib/walking/formatters';
import { dateKey, type WalkingLedger } from '@/lib/walking/ledger';

export interface AchievementMetricsInput {
  personalStats: PersonalStats | null;
  historyRecords?: WalkingRecord[] | null;
  ledger: WalkingLedger;
  todaySteps: number;
  currentDateKey: string;
}

/**
 * Computes the user's highest single-day step record and its date across server stats,
 * synced history records, and local ledger entries.
 */
export function computeBestDay({
  personalStats,
  historyRecords,
  ledger,
  todaySteps,
  currentDateKey,
  personalTodayDateKey,
  personalHistorySteps,
}: AchievementMetricsInput & {
  personalTodayDateKey?: string;
  personalHistorySteps?: Readonly<Record<string, number>>;
}): {
  bestDaySteps: number;
  bestDayDate: string | null;
} {
  const todayKey = personalTodayDateKey ?? currentDateKey;
  const usePersonalToday = personalTodayDateKey != null;
  const rawServerBest = personalStats?.best_day_steps || 0;
  const serverBestDate = cleanDateYMD(personalStats?.best_day_date);
  const serverStatsDate = cleanDateYMD(personalStats?.date) || currentDateKey;
  // Older stats responses give the all-time total without its date.
  const serverBestCouldBeToday = serverBestDate
    ? serverBestDate >= todayKey
    : serverStatsDate >= todayKey && rawServerBest === personalStats?.today_steps;

  // Match an undated server best to the saved history before replacing its count.
  const serverBestHasLocalReading = personalHistorySteps?.[serverBestDate] != null && serverBestDate < todayKey;
  const serverBestReplacedByHistory = serverBestHasLocalReading || (personalHistorySteps != null && historyRecords?.some((record) => {
    const date = cleanDateYMD(record.date);
    if (!date || date >= todayKey) return false;
    return serverBestDate
      ? date === serverBestDate
      : record.steps === rawServerBest && personalHistorySteps[date] != null;
  }));
  let maxSteps = Number.isFinite(rawServerBest)
    && !(usePersonalToday && serverBestCouldBeToday)
    && !serverBestReplacedByHistory
    ? Math.max(0, rawServerBest)
    : 0;
  let bestDayDate = maxSteps > 0 ? serverBestDate || null : null;

  const historyDates = personalHistorySteps != null ? new Set<string>() : null;
  for (const record of historyRecords ?? []) {
    const recordDate = cleanDateYMD(record.date);
    if (usePersonalToday && recordDate >= todayKey) continue;
    historyDates?.add(recordDate);
    const steps = personalHistorySteps?.[recordDate] ?? record.steps;
    if (steps > maxSteps || (usePersonalToday && !bestDayDate && steps === maxSteps && maxSteps > 0)) {
      maxSteps = steps;
      bestDayDate = recordDate;
    }
  }

  for (const [date, steps] of Object.entries(ledger.daily)) {
    if (usePersonalToday && date >= todayKey) continue;
    if (historyDates?.has(date) || personalHistorySteps?.[date] != null) continue;
    if (steps > maxSteps || (usePersonalToday && !bestDayDate && steps === maxSteps && maxSteps > 0)) {
      maxSteps = steps;
      bestDayDate = date;
    }
  }

  for (const [date, steps] of Object.entries(personalHistorySteps ?? {})) {
    if (date < todayKey && (steps > maxSteps || (!bestDayDate && steps === maxSteps && maxSteps > 0))) {
      maxSteps = steps;
      bestDayDate = date;
    }
  }

  if (todaySteps >= maxSteps && todaySteps > 0) {
    maxSteps = todaySteps;
    bestDayDate = todayKey;
  }

  return {
    bestDaySteps: maxSteps,
    bestDayDate: bestDayDate || (usePersonalToday && maxSteps > 0 ? null : todayKey),
  };
}

/**
 * Counts distinct active days (>= 5,000 steps) merging server totals with local/history sets.
 */
export function computeActiveDaysCount({
  personalStats,
  historyRecords,
  ledger,
  todaySteps,
  currentDateKey,
}: AchievementMetricsInput): number {
  const activeDates = new Set<string>();
  if (historyRecords && historyRecords.length > 0) {
    for (const rec of historyRecords) {
      if (rec.steps >= 5000) activeDates.add(rec.date);
    }
  }
  for (const [k, v] of Object.entries(ledger.daily)) {
    if (v >= 5000) activeDates.add(k);
  }
  if (todaySteps >= 5000) {
    activeDates.add(currentDateKey);
  }

  if (personalStats?.active_days_count != null && personalStats.active_days_count > 0) {
    return Math.max(personalStats.active_days_count, activeDates.size);
  }
  return Math.max(activeDates.size, personalStats?.total_days_active || (todaySteps >= 5000 ? 1 : 0));
}

/**
 * Computes average daily steps over the trailing 30-day window (falling back to local ledger
 * averages when the server aggregate is not yet populated).
 */
export function computeAvgDaily30d({
  personalStats,
  historyRecords,
  ledger,
  todaySteps,
  currentDateKey,
}: AchievementMetricsInput): number {
  if (personalStats?.avg_daily_steps_30d != null && personalStats.avg_daily_steps_30d > 0) {
    return personalStats.avg_daily_steps_30d;
  }
  const now = new Date();
  const daysMap = new Map<string, number>();

  if (historyRecords) {
    for (const r of historyRecords) {
      daysMap.set(r.date, r.steps);
    }
  }
  for (const [k, v] of Object.entries(ledger.daily)) {
    daysMap.set(k, Math.max(daysMap.get(k) ?? 0, v));
  }
  daysMap.set(currentDateKey, todaySteps);

  let sum = 0;
  let count = 0;
  for (let i = 0; i < 30; i++) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    const k = dateKey(d);
    const val = daysMap.get(k);
    if (val != null && val > 0) {
      sum += val;
      count++;
    }
  }

  if (count > 0) return Math.round(sum / count);
  if (personalStats?.avg_daily_steps != null && personalStats.avg_daily_steps > 0) {
    return Math.round(personalStats.avg_daily_steps);
  }
  return Math.round(todaySteps);
}

/**
 * Computes consecutive days reaching the 10,000-step club threshold, preferring authoritative
 * server streak values when available.
 */
export function computeStreak10k({
  personalStats,
  historyRecords,
  ledger,
  todaySteps,
  currentDateKey,
}: AchievementMetricsInput): number {
  const serverStreak = personalStats?.streak_10k ?? personalStats?.current_streak;
  if (serverStreak != null) {
    return serverStreak;
  }

  const now = new Date();
  const daysMap = new Map<string, number>();
  if (historyRecords) {
    for (const r of historyRecords) daysMap.set(r.date, r.steps);
  }
  for (const [k, v] of Object.entries(ledger.daily)) {
    daysMap.set(k, Math.max(daysMap.get(k) ?? 0, v));
  }
  daysMap.set(currentDateKey, todaySteps);

  let streak = 0;
  const todayAchieved = (daysMap.get(currentDateKey) ?? 0) >= 10000;
  if (todayAchieved) streak++;

  for (let i = 1; i <= 90; i++) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    const k = dateKey(d);
    const steps = daysMap.get(k) ?? 0;
    if (steps >= 10000) {
      streak++;
    } else {
      break;
    }
  }

  return streak;
}
