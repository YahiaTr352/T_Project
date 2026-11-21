import { WalkingHistoryList } from '@/components/walking/WalkingHistoryList';
import type { PersonalStats, WalkingRecord } from '@/lib/api/walkingApi';
import { computeBestDay } from '@/lib/walking/achievementMetrics';
import { formatSteps } from '@/lib/walking/formatters';
import { render, screen } from '@testing-library/react-native';

jest.mock('@/components/themed-text', () => ({ ThemedText: jest.requireActual('react-native').Text }));
jest.mock('@/components/ui/icon-symbol', () => ({ IconSymbol: () => null }));

const today = '2026-11-21';
const yesterday = '2026-11-20';

const personalStats: PersonalStats = {
  today_steps: 1600,
  weekly_steps: 2400,
  monthly_steps: 2400,
  all_time_steps: 2400,
  current_streak: 0,
  longest_streak: 0,
  best_day_steps: 1600,
  avg_daily_steps: 1200,
  total_days_active: 2,
};

const todayRecord: WalkingRecord = {
  id: 1,
  date: `${today}T00:00:00Z`,
  steps: 1600,
  distance_km: null,
  duration_minutes: null,
  calories: null,
  source: 'apple_health',
  notes: null,
  person_id: 1,
  user_id: null,
};

const input = {
  personalStats,
  historyRecords: [todayRecord, { ...todayRecord, id: 2, date: yesterday, steps: 800 }],
  ledger: { daily: { [today]: 1500, [yesterday]: 800 } },
  todaySteps: 1200,
  currentDateKey: today,
  personalTodayDateKey: today,
};

it('uses one total for today in the history row and the best-day calculation', () => {
  const best = computeBestDay(input);
  expect(best).toEqual({ bestDaySteps: 1200, bestDayDate: today });

  render(
    <WalkingHistoryList
      records={input.historyRecords}
      isLoading={false}
      localSteps={{ [yesterday]: 900 }}
      today={{ dateKey: today, steps: input.todaySteps, isDeviceTime: true }}
    />,
  );

  expect(screen.getByText(`${formatSteps(best.bestDaySteps)} خطوة`)).toBeOnTheScreen();
  expect(screen.queryByText(`${formatSteps(todayRecord.steps)} خطوة`)).toBeNull();
  expect(screen.getByText(`${formatSteps(900)} خطوة`)).toBeOnTheScreen();
});

it('keeps a larger result from a prior day when the server omitted its date', () => {
  expect(computeBestDay({
    ...input,
    personalStats: { ...personalStats, today_steps: 1000, best_day_steps: 2000 },
    ledger: { daily: { [yesterday]: 2000 } },
  })).toEqual({ bestDaySteps: 2000, bestDayDate: yesterday });
});

it('does not label an undated all-time result as today', () => {
  expect(computeBestDay({
    ...input,
    personalStats: { ...personalStats, today_steps: 1000, best_day_steps: 2000 },
  })).toEqual({ bestDaySteps: 2000, bestDayDate: null });
});

it('uses the device date when it differs from the Riyadh date', () => {
  expect(computeBestDay({
    ...input,
    currentDateKey: yesterday,
    personalStats: { ...personalStats, best_day_date: yesterday },
    todaySteps: 1700,
  })).toEqual({ bestDaySteps: 1700, bestDayDate: today });
});

it('lets a verified zero replace the saved count for today', () => {
  expect(computeBestDay({ ...input, todaySteps: 0 })).toEqual({ bestDaySteps: 800, bestDayDate: yesterday });
});

it('uses the iOS history count for a past best day in both places', () => {
  const date = '2026-11-17';
  const historyRecords = [
    { ...todayRecord, id: 2, date: `${date}T00:00:00Z`, steps: 14924 },
    { ...todayRecord, id: 3, date: '2026-11-05', steps: 14269 },
  ];
  const personalHistorySteps = { [date]: 14831 };
  const best = computeBestDay({
    ...input,
    personalStats: { ...personalStats, best_day_steps: 14924 },
    historyRecords,
    ledger: { daily: { [date]: 14924 } },
    personalHistorySteps,
  });

  expect(best).toEqual({ bestDaySteps: 14831, bestDayDate: date });
  render(<WalkingHistoryList records={historyRecords} isLoading={false} localSteps={personalHistorySteps} />);
  expect(screen.getByText(`${formatSteps(best.bestDaySteps)} خطوة`)).toBeOnTheScreen();
  expect(screen.queryByText(`${formatSteps(14924)} خطوة`)).toBeNull();
});

it('chooses the next best displayed day when a past best has zero iOS steps', () => {
  expect(computeBestDay({
    ...input,
    personalStats: { ...personalStats, best_day_steps: 2000, best_day_date: yesterday },
    historyRecords: [
      { ...todayRecord, id: 2, date: yesterday, steps: 2000 },
      { ...todayRecord, id: 3, date: '2026-11-19', steps: 1500 },
    ],
    ledger: { daily: { [yesterday]: 2000, '2026-11-19': 1900 } },
    personalHistorySteps: { [yesterday]: 0 },
  })).toEqual({ bestDaySteps: 1500, bestDayDate: '2026-11-19' });
});

it('keeps a dated server best from before the iOS history window', () => {
  expect(computeBestDay({
    ...input,
    personalStats: { ...personalStats, best_day_steps: 2000, best_day_date: '2026-10-01' },
    personalHistorySteps: { [yesterday]: 900 },
  })).toEqual({ bestDaySteps: 2000, bestDayDate: '2026-10-01' });
});
