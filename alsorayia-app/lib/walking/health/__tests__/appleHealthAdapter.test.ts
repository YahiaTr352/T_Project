import type { HealthConsentStore } from '../types';
import {
  createAppleHealthAdapter,
  type HealthKitNativeModule,
} from '../appleHealthAdapter';

function createConsentStore(initial: 'granted' | 'denied' | null = null) {
  let consent = initial;
  const store: HealthConsentStore = {
    get: jest.fn(async () => consent),
    set: jest.fn(async (value) => {
      consent = value;
    }),
  };
  return store;
}

function createModule(
  overrides: Partial<HealthKitNativeModule> = {}
): HealthKitNativeModule {
  return {
    isHealthDataAvailable: jest.fn(() => true),
    requestAuthorization: jest.fn(async () => true),
    queryStatisticsCollectionForQuantity: jest.fn(async () => []),
    queryQuantitySamples: jest.fn(async () => []),
    ...overrides,
  };
}

describe('createAppleHealthAdapter', () => {
  it('reports unavailable when the native module cannot be loaded', async () => {
    const adapter = createAppleHealthAdapter({
      loadModule: () => null,
      consentStore: createConsentStore(),
    });

    await expect(adapter.isAvailable()).resolves.toBe(false);
    await expect(adapter.getPermissionState()).resolves.toEqual({
      status: 'unavailable',
      canAskAgain: false,
      requiresSettings: false,
    });
  });

  it('reports unavailable when isHealthDataAvailable returns false', async () => {
    const adapter = createAppleHealthAdapter({
      loadModule: () => createModule({ isHealthDataAvailable: () => false }),
      consentStore: createConsentStore(),
    });

    await expect(adapter.isAvailable()).resolves.toBe(false);
    await expect(adapter.getPermissionState()).resolves.toEqual({
      status: 'unavailable',
      canAskAgain: false,
      requiresSettings: false,
    });
  });

  it('uses the stored consent because HealthKit hides read authorization', async () => {
    const adapter = createAppleHealthAdapter({
      loadModule: createModule,
      consentStore: createConsentStore('granted'),
    });

    await expect(adapter.getPermissionState()).resolves.toEqual({
      status: 'granted',
      canAskAgain: false,
      requiresSettings: false,
    });
  });

  it('requests step read permission and persists consent', async () => {
    const requestAuthorization = jest.fn(async () => true);
    const consentStore = createConsentStore();
    const adapter = createAppleHealthAdapter({
      loadModule: () =>
        createModule({
          requestAuthorization,
        }),
      consentStore,
    });

    await expect(adapter.requestPermissions()).resolves.toEqual({
      status: 'granted',
      canAskAgain: false,
      requiresSettings: false,
    });
    expect(requestAuthorization).toHaveBeenCalledWith({
      toRead: ['HKQuantityTypeIdentifierStepCount'],
      toShare: [],
    });
    await expect(consentStore.get()).resolves.toBe('granted');
  });

  it('persists denial when HealthKit requestAuthorization returns false', async () => {
    const consentStore = createConsentStore();
    const adapter = createAppleHealthAdapter({
      loadModule: () =>
        createModule({
          requestAuthorization: jest.fn(async () => false),
        }),
      consentStore,
    });

    await expect(adapter.requestPermissions()).resolves.toEqual({
      status: 'denied',
      canAskAgain: false,
      requiresSettings: true,
    });
    await expect(consentStore.get()).resolves.toBe('denied');
  });

  it('reads and aggregates hourly step buckets', async () => {
    const queryStatisticsCollectionForQuantity = jest.fn(async () => [
      { startDate: new Date('2026-10-20T05:00:00.000Z'), sumQuantity: { quantity: 120 } },
      { startDate: new Date('2026-10-20T05:30:00.000Z'), sumQuantity: { quantity: 80 } },
      { startDate: new Date('2026-10-20T06:15:00.000Z'), sumQuantity: { quantity: 55 } },
      { startDate: new Date('2026-10-21T00:00:00.000Z'), sumQuantity: { quantity: 999 } },
    ]);
    const adapter = createAppleHealthAdapter({
      loadModule: () => createModule({ queryStatisticsCollectionForQuantity }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T15:00:00.000Z'));

    expect(queryStatisticsCollectionForQuantity).toHaveBeenCalledWith(
      'HKQuantityTypeIdentifierStepCount',
      ['cumulativeSum'],
      expect.any(Date),
      { hour: 1 },
      expect.objectContaining({
        filter: {
          date: expect.objectContaining({
            startDate: new Date('2026-10-19T21:00:00.000Z'),
            endDate: expect.any(Date),
          }),
        },
        unit: 'count',
      })
    );
    expect(result).toEqual({ total: 255, hourly: { 8: 200, 9: 55 } });
  });

  it('combines steps across China local midnight within the same Saudi Arabia day and filters manual entries', async () => {
    // 20:30 KSA on 2026-10-20 is 01:30 AM (next day) in China (UTC+8).
    // Before China midnight (15:00 UTC = 18:00 KSA / 23:00 China): 38,000 steps.
    // After China midnight (17:00 UTC = 20:00 KSA / 01:00 China): 4,000 steps.
    const queryStatisticsForQuantity = jest.fn(async () => ({
      sumQuantity: { quantity: 42000 },
    }));
    const queryStatisticsCollectionForQuantity = jest.fn(async () => [
      { startDate: new Date('2026-10-20T15:00:00.000Z'), sumQuantity: { quantity: 38000 } },
      { startDate: new Date('2026-10-20T17:00:00.000Z'), sumQuantity: { quantity: 4000 } },
    ]);
    const adapter = createAppleHealthAdapter({
      loadModule: () =>
        createModule({
          queryStatisticsForQuantity,
          queryStatisticsCollectionForQuantity,
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T17:30:00.000Z'));

    expect(queryStatisticsForQuantity).toHaveBeenCalledWith(
      'HKQuantityTypeIdentifierStepCount',
      ['cumulativeSum'],
      expect.objectContaining({
        filter: {
          date: expect.objectContaining({
            startDate: new Date('2026-10-19T21:00:00.000Z'),
          }),
        },
        unit: 'count',
      })
    );
    expect(result).toEqual({
      total: 42000,
      hourly: { 18: 38000, 20: 4000 },
    });
  });

  it('deducts manually added steps (+1000 in Apple Health) via queryQuantitySamples even if statistics query did not filter them', async () => {
    const queryStatisticsCollectionForQuantity = jest.fn(async () => [
      { startDate: new Date('2026-10-20T10:00:00.000Z'), sumQuantity: { quantity: 2500 } },
      { startDate: new Date('2026-10-20T14:00:00.000Z'), sumQuantity: { quantity: 2000 } },
    ]);
    const queryQuantitySamples = jest.fn(async () => [
      {
        startDate: new Date('2026-10-20T10:15:00.000Z'),
        endDate: new Date('2026-10-20T10:35:00.000Z'),
        quantity: 2500,
        metadata: {},
        device: { name: 'iPhone' },
        sourceRevision: { source: { bundleIdentifier: 'com.apple.health.1234' } },
      },
      {
        startDate: new Date('2026-10-20T14:20:00.000Z'),
        endDate: new Date('2026-10-20T14:20:00.000Z'),
        quantity: 1000,
        metadata: { HKWasUserEntered: 1 },
        sourceRevision: { source: { bundleIdentifier: 'com.apple.Health' } },
      },
    ]);

    const adapter = createAppleHealthAdapter({
      loadModule: () =>
        createModule({
          queryStatisticsCollectionForQuantity,
          queryQuantitySamples,
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T16:00:00.000Z'));

    expect(result).toEqual({
      total: 3500,
      hourly: { 13: 2500, 17: 1000 },
    });
  });

  it('does NOT deduct legitimate iPhone steps that have bundleId com.apple.Health and null device', async () => {
    const queryStatisticsCollectionForQuantity = jest.fn(async () => [
      { startDate: new Date('2026-10-20T10:00:00.000Z'), sumQuantity: { quantity: 1450 } },
    ]);
    const queryQuantitySamples = jest.fn(async () => [
      {
        startDate: new Date('2026-10-20T10:15:00.000Z'),
        endDate: new Date('2026-10-20T10:45:00.000Z'),
        quantity: 1450,
        metadata: {},
        device: null,
        sourceRevision: { source: { bundleIdentifier: 'com.apple.Health' } },
      },
    ]);

    const adapter = createAppleHealthAdapter({
      loadModule: () =>
        createModule({
          queryStatisticsCollectionForQuantity,
          queryQuantitySamples,
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T16:00:00.000Z'));

    // All 1450 steps must be preserved! Zero false manual deduction!
    expect(result).toEqual({
      total: 1450,
      hourly: { 13: 1450 },
    });
  });

  it('reads recent daily buckets', async () => {
    const adapter = createAppleHealthAdapter({
      loadModule: () =>
        createModule({
          queryStatisticsCollectionForQuantity: jest.fn(async () => [
            { startDate: new Date('2026-10-19T21:00:00.000Z'), sumQuantity: { quantity: 4000 } },
            { startDate: new Date('2026-10-20T21:00:00.000Z'), sumQuantity: { quantity: 5000 } },
          ]),
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readRecentDays(7);

    expect(result).toEqual({
      '2026-10-20': 4000,
      '2026-10-21': 5000,
    });
  });

  it('excludes manual steps from the personal total and local hour where they were entered', async () => {
    const start = new Date(2025, 2, 9);
    const end = new Date(2025, 2, 10);
    const stats = jest.fn(async () => [
      { startDate: new Date(2025, 2, 9, 8), sumQuantity: { quantity: 2000 } },
      { startDate: new Date(2025, 2, 9, 22), sumQuantity: { quantity: 1500 } },
    ]);
    const queryQuantitySamples = jest.fn(async () => [
      { startDate: new Date(2025, 2, 9, 8, 10), quantity: 2000, metadata: { HKWasUserEntered: false } },
      { startDate: new Date(2025, 2, 9, 22, 15), quantity: 500, metadata: { HKWasUserEntered: true } },
      { startDate: end, quantity: 700, metadata: { HKWasUserEntered: true } },
    ]);
    const adapter = createAppleHealthAdapter({
      loadModule: () => createModule({
        queryStatisticsCollectionForQuantity: stats,
        queryStatisticsForQuantity: jest.fn(async () => ({ sumQuantity: { quantity: 3500 } })),
        queryQuantitySamples,
      }),
      consentStore: createConsentStore('granted'),
    });

    const date = { startDate: start, endDate: end, strictStartDate: true, strictEndDate: true };
    await expect(adapter.readPersonalDay(new Date(2025, 2, 9, 12))).resolves.toEqual({
      total: 3000,
      hourly: { 8: 2000, 22: 1000 },
    });
    expect(stats).toHaveBeenCalledWith(
      'HKQuantityTypeIdentifierStepCount', ['cumulativeSum'], start, { hour: 1 },
      { filter: { date }, unit: 'count' },
    );
    expect(queryQuantitySamples).toHaveBeenCalledWith('HKQuantityTypeIdentifierStepCount', {
      filter: {
        date,
        OR: [
          { metadata: { withMetadataKey: 'HKWasUserEntered' } },
          { metadata: { withMetadataKey: 'HKMetadataKeyWasUserEntered' } },
        ],
      },
      limit: 0,
      unit: 'count',
    });
  });

  it('subtracts manual history by local date and preserves zero separately from missing data', async () => {
    const adapter = createAppleHealthAdapter({
      loadModule: () => createModule({
        queryStatisticsCollectionForQuantity: jest.fn(async () => [
          { startDate: new Date(2025, 2, 8) },
          { startDate: new Date(2025, 2, 9), sumQuantity: { quantity: 3500 } },
          { startDate: new Date(2025, 2, 10), sumQuantity: { quantity: 200 } },
          { startDate: new Date(2025, 2, 11), sumQuantity: { quantity: 0 } },
        ]),
        queryQuantitySamples: jest.fn(async () => [
          { startDate: new Date(2025, 2, 9, 23, 55), quantity: 500, metadata: { HKWasUserEntered: 1 } },
          { startDate: new Date(2025, 2, 10, 0, 5), quantity: 200, metadata: { HKMetadataKeyWasUserEntered: 'true' } },
        ]),
      }),
      consentStore: createConsentStore('granted'),
    });

    await expect(adapter.readPersonalDays(new Date(2025, 2, 8), new Date(2025, 2, 11)))
      .resolves.toEqual({ '2025-03-09': 3000, '2025-03-10': 0, '2025-03-11': 0 });
  });

  it('marks personal activity as unavailable if manual records cannot be checked', async () => {
    const day = new Date(2025, 2, 9);
    const adapter = createAppleHealthAdapter({
      loadModule: () => createModule({
        queryStatisticsForQuantity: jest.fn(async () => ({ sumQuantity: { quantity: 3500 } })),
        queryStatisticsCollectionForQuantity: jest.fn(async () => [
          { startDate: day, sumQuantity: { quantity: 3500 } },
        ]),
        queryQuantitySamples: jest.fn().mockRejectedValue(new Error('HealthKit read failed')),
      }),
      consentStore: createConsentStore('granted'),
    });

    await expect(adapter.readPersonalDay(day)).resolves.toBeNull();
    await expect(adapter.readPersonalDays(day, day)).resolves.toBeNull();
  });

  it('starts a new local day at zero when yesterday confirms Health access', async () => {
    const today = new Date(2026, 10, 20, 0, 15);
    jest.useFakeTimers({ now: today });
    try {
      const adapter = createAppleHealthAdapter({
        loadModule: () => createModule({
          queryStatisticsForQuantity: jest.fn().mockResolvedValueOnce({})
            .mockResolvedValueOnce({ sumQuantity: { quantity: 4700 } }),
        }),
        consentStore: createConsentStore('granted'),
      });

      await expect(adapter.readPersonalDay(today)).resolves.toEqual({ total: 0, hourly: {} });
    } finally {
      jest.useRealTimers();
    }
  });
});
