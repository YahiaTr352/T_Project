import { appleHealthAdapter } from '@/lib/walking/health/appleHealthAdapter';
import type { HealthDaySteps } from '@/lib/walking/health/types';
import { dateKey } from '@/lib/walking/ledger';
import { useEffect, useState } from 'react';
import { AppState } from 'react-native';

const REFRESH_MS = 30_000;

function deviceTimeZoneKey(date: Date): string {
  const offset = date.getTimezoneOffset();
  try {
    return `${Intl.DateTimeFormat().resolvedOptions().timeZone}:${offset}`;
  } catch {
    return String(offset);
  }
}

function localDateFromKey(key: string): Date {
  const [year, month, day] = key.split('-').map(Number);
  return new Date(year, month - 1, day);
}

export function personalHistoryRange(todayKey: string) {
  const today = localDateFromKey(todayKey);
  const offset = (days: number) => dateKey(new Date(today.getFullYear(), today.getMonth(), today.getDate() - days));
  return { from: offset(29), through: offset(1) };
}

function deviceDay(now = new Date()) {
  const todayKey = dateKey(now);
  const timeZoneKey = deviceTimeZoneKey(now);
  return { todayKey, timeZoneKey, key: `${todayKey}:${timeZoneKey}` };
}

type TodayReading = { key: string; day: HealthDaySteps | null; readAt: Date | null; failed: boolean };

export function useLocalWalkingToday(enabled: boolean, lastSync: number | null, refreshVersion = 0, canRead = true) {
  const [, setClockKey] = useState(() => deviceDay().key);
  const [snapshot, setSnapshot] = useState<TodayReading | null>(null);

  useEffect(() => {
    if (!enabled) {
      setSnapshot(null);
      return;
    }
    let active = true;
    let readingKey: string | null = null;
    let readVersion = 0;
    let midnight: ReturnType<typeof setTimeout> | undefined;

    const refresh = async () => {
      if (AppState.currentState === 'background') return;
      const now = new Date();
      const { key } = deviceDay(now);
      setClockKey(key);
      clearTimeout(midnight);
      const nextMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
      midnight = setTimeout(() => void refresh(), Math.max(1, nextMidnight.getTime() - Date.now()));

      if (!canRead || readingKey === key) return;
      readingKey = key;
      const request = ++readVersion;

      try {
        const day = await appleHealthAdapter.readPersonalDay(now).catch(() => null);
        if (active && request === readVersion && key === deviceDay().key) {
          setSnapshot((previous) => {
            if (day) return { key, day, readAt: now, failed: false };
            return previous?.key === key
              ? { ...previous, failed: true }
              : { key, day: null, readAt: null, failed: true };
          });
        }
      } finally {
        if (request === readVersion) readingKey = null;
      }
    };

    void refresh();
    const interval = setInterval(() => void refresh(), REFRESH_MS);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void refresh();
    });
    return () => {
      active = false;
      clearInterval(interval);
      clearTimeout(midnight);
      subscription.remove();
    };
  }, [enabled, canRead, lastSync, refreshVersion]);

  const clock = deviceDay();
  const current = enabled && canRead && snapshot?.key === clock.key ? snapshot : null;
  const status = !enabled || !canRead || current?.failed ? 'unavailable' : current ? 'ready' : 'loading';
  return {
    todayKey: clock.todayKey,
    timeZoneKey: enabled && canRead ? clock.timeZoneKey : null,
    today: current?.day ?? null,
    lastRead: current?.readAt ?? null,
    status,
  } as const;
}

type LocalActivityResults = { daily: Record<string, number>; day: HealthDaySteps | null; failed: boolean };

export function useLocalWalkingHistory(
  timeZoneKey: string | null,
  from: string | null,
  through: string | null,
  includeHours = false,
  refreshVersion = 0,
) {
  const request = timeZoneKey && from && through ? `${timeZoneKey}:${from}:${through}:${includeHours}` : null;
  const [result, setResult] = useState<(LocalActivityResults & { request: string }) | null>(null);

  useEffect(() => {
    if (!request || !from || !through) return;
    let active = true;
    let readVersion = 0;
    const load = async () => {
      if (AppState.currentState === 'background') return;
      const version = ++readVersion;
      const first = localDateFromKey(from);
      const last = localDateFromKey(through);
      const day = includeHours ? await appleHealthAdapter.readPersonalDay(first).catch(() => null) : null;
      const daily = includeHours
        ? (day ? { [from]: day.total } : null)
        : await appleHealthAdapter.readPersonalDays(first, last).catch(() => null);
      if (active && version === readVersion) {
        setResult((previous) => {
          if (daily) return { request, day, daily, failed: false };
          return previous?.request === request
            ? { ...previous, failed: true }
            : { request, day: null, daily: {}, failed: true };
        });
      }
    };

    void load();
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void load();
    });
    return () => {
      active = false;
      subscription.remove();
    };
  }, [request, includeHours, from, through, refreshVersion]);

  return result?.request === request ? result : null;
}
