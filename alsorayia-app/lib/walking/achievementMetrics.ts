import type { PersonalStats, WalkingRecord } from '@/lib/api/walkingApi';
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
}: AchievementMetricsInput): { bestDaySteps: number; bestDayDate: string } {
  const rawServerBest = personalStats?.best_day_steps || 0;
  let maxSteps = Number.isFinite(rawServerBest) ? Math.max(0, rawServerBest) : 0;
  let bDate = maxSteps > 0 ? (personalStats?.best_day_date || null) : null;

  if (historyRecords && historyRecords.length > 0) {
    for (const rec of historyRecords) {
      if (rec.steps > maxSteps) {
        maxSteps = rec.steps;
        bDate = rec.date;
      }
    }
  }

  for (const [k, v] of Object.entries(ledger.daily)) {
    if (v > maxSteps) {
      maxSteps = v;
      bDate = k;
    }
  }

  if (todaySteps >= maxSteps && todaySteps > 0) {
    maxSteps = todaySteps;
    bDate = currentDateKey;
  }

  return {
    bestDaySteps: Math.max(maxSteps, todaySteps),
    bestDayDate: bDate || currentDateKey,
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
