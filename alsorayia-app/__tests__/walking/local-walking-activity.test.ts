import { personalHistoryRange, useLocalWalkingHistory, useLocalWalkingToday } from '@/hooks/use-local-walking-activity';
import { appleHealthAdapter } from '@/lib/walking/health/appleHealthAdapter';
import type { HealthDaySteps } from '@/lib/walking/health/types';
import { act, renderHook } from '@testing-library/react-native';

jest.mock('@/lib/walking/health/appleHealthAdapter', () => ({
  appleHealthAdapter: { readPersonalDay: jest.fn(), readPersonalDays: jest.fn() },
}));

const readToday = jest.mocked(appleHealthAdapter.readPersonalDay);
const readHistory = jest.mocked(appleHealthAdapter.readPersonalDays);

afterEach(() => {
  readToday.mockReset();
  readHistory.mockReset();
  jest.useRealTimers();
});

it('rolls over at device midnight even if the previous HealthKit request is still pending', async () => {
  jest.useFakeTimers();
  jest.setSystemTime(new Date(2026, 10, 21, 23, 59, 59));
  let finishYesterday!: (day: HealthDaySteps) => void;
  let finishToday!: (day: HealthDaySteps) => void;
  readToday.mockImplementationOnce(() => new Promise((resolve) => { finishYesterday = resolve; }));
  readToday.mockImplementationOnce(() => new Promise((resolve) => { finishToday = resolve; }));

  const { result, unmount } = renderHook(() => useLocalWalkingToday(true, null));
  expect(result.current.todayKey).toBe('2026-11-21');
  act(() => { jest.advanceTimersByTime(1000); });
  expect(result.current.todayKey).toBe('2026-11-22');
  expect(result.current.today).toBeNull();
  expect(result.current.status).toBe('loading');

  await act(async () => { finishToday({ total: 300, hourly: {} }); });
  await act(async () => { finishYesterday({ total: 8000, hourly: {} }); });
  expect(result.current.today?.total).toBe(300);
  unmount();
});

it('keeps the last successful same-day total when a refresh fails', async () => {
  readToday.mockResolvedValueOnce({ total: 300, hourly: {} }).mockResolvedValueOnce(null);
  const { result, rerender } = renderHook(({ refresh }: { refresh: number }) => useLocalWalkingToday(true, null, refresh), {
    initialProps: { refresh: 0 },
  });
  await act(async () => {});
  expect(result.current.today?.total).toBe(300);
  const lastRead = result.current.lastRead;

  await act(async () => { rerender({ refresh: 1 }); });
  expect(result.current.status).toBe('unavailable');
  expect(result.current.today?.total).toBe(300);
  expect(result.current.lastRead).toBe(lastRead);
});

it('updates the same history dates after a refresh and keeps them on a read failure', async () => {
  readHistory.mockResolvedValueOnce({ '2026-11-21': 20925 })
    .mockResolvedValueOnce({ '2026-11-21': 21172 }).mockResolvedValueOnce(null);
  const { result, rerender } = renderHook(({ refresh }: { refresh: number }) => (
    useLocalWalkingHistory('Asia/Dubai:-240', '2026-11-01', '2026-11-21', false, refresh)
  ), { initialProps: { refresh: 0 } });
  await act(async () => {});
  expect(result.current?.daily['2026-11-21']).toBe(20925);

  await act(async () => { rerender({ refresh: 1 }); });
  expect(result.current?.daily['2026-11-21']).toBe(21172);
  await act(async () => { rerender({ refresh: 2 }); });
  expect(result.current?.failed).toBe(true);
  expect(result.current?.daily['2026-11-21']).toBe(21172);
});

it('includes the previous device day across a month boundary', () => {
  expect(personalHistoryRange('2026-12-01')).toEqual({ from: '2026-11-02', through: '2026-11-30' });
});
