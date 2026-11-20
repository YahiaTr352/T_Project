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

export function useLocalWalkingToday(enabled: boolean, lastSync: number | null) {
  const [snapshot, setSnapshot] = useState<{ key: string; day: HealthDaySteps | null } | null>(null);

  useEffect(() => {
    if (!enabled) {
      setSnapshot(null);
      return;
    }
    let active = true;
    let reading = false;

    const refresh = async () => {
      if (reading || AppState.currentState === 'background') return;
      reading = true;
      const now = new Date();
      const key = `${dateKey(now)}:${deviceTimeZoneKey(now)}`;

      try {
        const day = await appleHealthAdapter.readPersonalDay(now).catch(() => null);
        const current = new Date();
        if (active && key === `${dateKey(current)}:${deviceTimeZoneKey(current)}`) {
          setSnapshot({ key, day });
        }
      } finally {
        reading = false;
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
      subscription.remove();
    };
  }, [enabled, lastSync]);

  const now = new Date();
  const todayKey = dateKey(now);
  const timeZoneKey = enabled ? deviceTimeZoneKey(now) : null;
  const today = enabled && snapshot?.key === `${todayKey}:${timeZoneKey}` ? snapshot.day : null;
  return { todayKey, timeZoneKey, today };
}

type LocalActivityResults = { daily: Record<string, number>; day: HealthDaySteps | null };

export function useLocalWalkingHistory(
  timeZoneKey: string | null,
  from: string | null,
  through: string | null,
  includeHours = false,
) {
  const request = timeZoneKey && from && through ? `${timeZoneKey}:${from}:${through}:${includeHours}` : null;
  const [result, setResult] = useState<(LocalActivityResults & { request: string }) | null>(null);

  useEffect(() => {
    if (!request || !from || !through) return;
    let active = true;
    const load = async () => {
      const first = localDateFromKey(from);
      const last = localDateFromKey(through);
      const day = includeHours ? await appleHealthAdapter.readPersonalDay(first).catch(() => null) : null;
      const daily = includeHours
        ? (day ? { [from]: day.total } : {})
        : await appleHealthAdapter.readPersonalDays(first, last).catch(() => ({}));
      if (active) setResult({ request, day, daily });
    };

    void load();
    return () => { active = false; };
  }, [request, includeHours, from, through]);

  return result?.request === request ? result : null;
}
