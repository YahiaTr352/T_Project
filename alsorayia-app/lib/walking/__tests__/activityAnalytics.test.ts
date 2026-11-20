import { computeDaySummary, computeMonthSummary, computeWeekSummary, computeYearSummary } from '../activityAnalytics';
import { ksaDateKey, type WalkingLedger } from '../ledger';

const now = new Date(2026, 10, 20, 10);

beforeEach(() => jest.useFakeTimers({ now }));
afterEach(() => jest.useRealTimers());

it('prefers the personal day and its local hours, and falls back to the competition ledger if unavailable', () => {
  const ksa = ksaDateKey(now);
  const ledger: WalkingLedger = { daily: { [ksa]: 500 }, hourly: { [ksa]: { 2: 500 } } };

  const local = computeDaySummary(now, ledger, [], 500, { total: 6500, hourly: { 10: 6500 } });
  expect(local.totalSteps).toBe(6500);
  expect(local.points[10]).toMatchObject({ steps: 6500, isCurrentHour: true });
  expect(computeDaySummary(now, ledger, [], 500, { total: 0, hourly: {} }).totalSteps).toBe(0);
  expect(computeDaySummary(now, ledger, [], 500).totalSteps).toBe(500);
});

it('uses local totals where available and keeps stored totals for missing dates in every period', () => {
  const ledger: WalkingLedger = { daily: { '2026-11-18': 500, '2026-11-19': 900 } };
  const local = { '2026-11-19': 0, '2026-11-20': 1500 };

  expect(computeWeekSummary(now, ledger, [], 700, 10000, local).totalSteps).toBe(2000);
  expect(computeMonthSummary(now, ledger, [], 700, 10000, local).totalSteps).toBe(2000);
  expect(computeYearSummary(now, ledger, [], 700, local).totalSteps).toBe(2000);
});
