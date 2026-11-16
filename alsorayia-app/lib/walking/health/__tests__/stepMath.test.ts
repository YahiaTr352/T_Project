import {
  aggregateDailyBuckets,
  aggregateHourlyBuckets,
  resolveDayRange,
  resolveRecentRange,
  sanitizeSteps,
} from '../stepMath';

describe('sanitizeSteps', () => {
  it('rejects invalid and negative values', () => {
    expect(sanitizeSteps(-10)).toBe(0);
    expect(sanitizeSteps(Number.NaN)).toBe(0);
    expect(sanitizeSteps(Number.POSITIVE_INFINITY)).toBe(0);
    expect(sanitizeSteps('abc')).toBe(0);
  });

  it('rounds values and clamps to the provided maximum', () => {
    expect(sanitizeSteps(12.4)).toBe(12);
    expect(sanitizeSteps(12.6)).toBe(13);
    expect(sanitizeSteps(999999)).toBe(999999);
    expect(sanitizeSteps(25000, 10000)).toBe(10000);
  });
});

describe('aggregateHourlyBuckets', () => {
  it('groups Riyadh-day buckets by hour and ignores other days', () => {
    const result = aggregateHourlyBuckets(
      [
        { start: '2026-10-20T05:00:00.000Z', steps: 100 },
        { start: '2026-10-20T05:30:00.000Z', steps: 50 },
        { start: '2026-10-20T06:10:00.000Z', steps: 75 },
        { start: '2026-10-19T20:30:00.000Z', steps: 300 },
        { start: '2026-10-21T00:30:00.000Z', steps: 999 },
        { start: 'invalid', steps: 500 },
        { start: '2026-10-20T07:00:00.000Z', steps: -5 },
      ],
      '2026-10-20'
    );

    expect(result).toEqual({
      total: 225,
      hourly: { 8: 150, 9: 75 },
    });
  });

  it('caps hourly chart values without limiting the daily total', () => {
    const buckets = Array.from({ length: 7 }, (_, index) => ({
      start: `2026-10-20T${String(5 + index).padStart(2, '0')}:00:00.000Z`,
      steps: 120000,
    }));

    const result = aggregateHourlyBuckets(buckets, '2026-10-20');

    expect(result.hourly[8]).toBe(100000);
    expect(result.hourly[14]).toBe(100000);
    expect(result.total).toBe(840000);
  });

  it('sums fractional HealthKit hourly buckets before rounding to avoid cumulative rounding drift', () => {
    const result = aggregateHourlyBuckets(
      [
        { start: '2026-10-20T05:00:00.000Z', steps: 120.4 },
        { start: '2026-10-20T06:00:00.000Z', steps: 85.4 },
        { start: '2026-10-20T07:00:00.000Z', steps: 14.4 },
      ],
      '2026-10-20'
    );

    // 120.4 + 85.4 + 14.4 = 220.2 -> 220 (whereas per-hour rounding would give 120 + 85 + 14 = 219)
    expect(result.total).toBe(220);
    expect(result.hourly).toEqual({ 8: 120, 9: 85, 10: 14 });
  });
});

describe('aggregateDailyBuckets', () => {
  it('groups buckets by Riyadh date', () => {
    const result = aggregateDailyBuckets([
      { start: '2026-10-19T20:59:00.000Z', steps: 1000 },
      { start: '2026-10-19T21:00:00.000Z', steps: 2000 },
      { start: '2026-10-20T05:00:00.000Z', steps: 3000 },
      { start: '2026-10-20T21:00:00.000Z', steps: 1000 },
      { start: 'invalid', steps: 4000 },
    ]);

    expect(result).toEqual({
      '2026-10-19': 1000,
      '2026-10-20': 5000,
      '2026-10-21': 1000,
    });
  });
});

describe('resolveDayRange', () => {
  const now = new Date('2026-10-20T15:00:00.000Z');

  it('ends the current day at now', () => {
    const range = resolveDayRange(now, now);

    expect(range.dayKey).toBe('2026-10-20');
    expect(range.start.toISOString()).toBe('2026-10-19T21:00:00.000Z');
    expect(range.end).toBe(now);
  });

  it('uses a full-day range for past dates', () => {
    const range = resolveDayRange(new Date('2026-10-18T12:00:00.000Z'), now);

    expect(range.dayKey).toBe('2026-10-18');
    expect(range.start.toISOString()).toBe('2026-10-17T21:00:00.000Z');
    expect(range.end.toISOString()).toBe('2026-10-18T20:59:59.000Z');
  });
});

describe('resolveRecentRange', () => {
  it('starts at the beginning of the oldest requested Riyadh day', () => {
    const range = resolveRecentRange(7, new Date('2026-10-20T15:00:00.000Z'));

    expect(range.start.toISOString()).toBe('2026-10-13T21:00:00.000Z');
  });
});
