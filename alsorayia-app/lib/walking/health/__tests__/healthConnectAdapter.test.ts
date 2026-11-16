import type { HealthConsentStore } from '../types';
import {
  createHealthConnectAdapter,
  type HealthConnectNativeModule,
} from '../healthConnectAdapter';

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
  overrides: Partial<HealthConnectNativeModule> = {}
): HealthConnectNativeModule {
  return {
    getSdkStatus: async () => 3,
    initialize: async () => true,
    getGrantedPermissions: async () => [],
    requestPermission: async () => [],
    aggregateGroupByDuration: async () => [],
    openHealthConnectSettings: () => {},
    SdkAvailabilityStatus: { SDK_AVAILABLE: 3 },
    ...overrides,
  };
}

describe('createHealthConnectAdapter', () => {
  it('reports unavailable when the SDK is not available', async () => {
    const adapter = createHealthConnectAdapter({
      loadModule: () => createModule({ getSdkStatus: async () => 2 }),
      consentStore: createConsentStore(),
    });

    await expect(adapter.getPermissionState()).resolves.toEqual({
      status: 'unavailable',
      canAskAgain: false,
      requiresSettings: false,
    });
  });

  it('reports unavailable when initialization fails', async () => {
    const adapter = createHealthConnectAdapter({
      loadModule: () => createModule({ initialize: async () => false }),
      consentStore: createConsentStore(),
    });

    await expect(adapter.isAvailable()).resolves.toBe(false);
  });

  it('reports granted when Steps read permission exists', async () => {
    const consentStore = createConsentStore();
    const adapter = createHealthConnectAdapter({
      loadModule: () =>
        createModule({
          getGrantedPermissions: async () => [
            { accessType: 'read', recordType: 'Steps' },
          ],
        }),
      consentStore,
    });

    await expect(adapter.getPermissionState()).resolves.toEqual({
      status: 'granted',
      canAskAgain: false,
      requiresSettings: false,
    });
    await expect(consentStore.get()).resolves.toBe('granted');
  });

  it('reports denied after an earlier granted consent loses access', async () => {
    const consentStore = createConsentStore('granted');
    const adapter = createHealthConnectAdapter({
      loadModule: () => createModule({ getGrantedPermissions: async () => [] }),
      consentStore,
    });

    await expect(adapter.getPermissionState()).resolves.toEqual({
      status: 'denied',
      canAskAgain: false,
      requiresSettings: true,
    });
    await expect(consentStore.get()).resolves.toBe('denied');
  });

  it('requests Steps read permission', async () => {
    const requestPermission = jest.fn(async () => [
      { accessType: 'read' as const, recordType: 'Steps' },
    ]);
    const consentStore = createConsentStore();
    const adapter = createHealthConnectAdapter({
      loadModule: () => createModule({ requestPermission }),
      consentStore,
    });

    await expect(adapter.requestPermissions()).resolves.toEqual({
      status: 'granted',
      canAskAgain: false,
      requiresSettings: false,
    });
    expect(requestPermission).toHaveBeenCalledWith([
      { accessType: 'read', recordType: 'Steps' },
    ]);
    await expect(consentStore.get()).resolves.toBe('granted');
  });

  it('persists denial when the permission request is rejected', async () => {
    const consentStore = createConsentStore();
    const adapter = createHealthConnectAdapter({
      loadModule: () =>
        createModule({
          requestPermission: async () => {
            throw new Error('cancelled');
          },
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

  it('reads and aggregates hourly step groups', async () => {
    const aggregateGroupByDuration = jest.fn(async () => [
      {
        startTime: '2026-10-20T05:00:00.000Z',
        endTime: '2026-10-20T06:00:00.000Z',
        result: { COUNT_TOTAL: 120 },
      },
      {
        startTime: '2026-10-20T06:00:00.000Z',
        endTime: '2026-10-20T07:00:00.000Z',
        result: { COUNT_TOTAL: 80 },
      },
      {
        startTime: '2026-10-20T07:00:00.000Z',
        endTime: '2026-10-20T08:00:00.000Z',
        result: {},
      },
    ]);
    const adapter = createHealthConnectAdapter({
      loadModule: () => createModule({ aggregateGroupByDuration }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T15:00:00.000Z'));

    expect(aggregateGroupByDuration).toHaveBeenCalledWith({
      recordType: 'Steps',
      timeRangeFilter: {
        operator: 'between',
        startTime: '2026-10-19T21:00:00.000Z',
        endTime: '2026-10-20T20:59:59.000Z',
      },
      timeRangeSlicer: { duration: 'HOURS', length: 1 },
    });
    expect(result).toEqual({ total: 200, hourly: { 8: 120, 9: 80 } });
  });

  it('uses the returned zone offset for local group times', async () => {
    const adapter = createHealthConnectAdapter({
      loadModule: () =>
        createModule({
          aggregateGroupByDuration: async () => [
            {
              startTime: '2026-10-20T08:00',
              endTime: '2026-10-20T09:00',
              zoneOffset: '+03:00',
              result: { COUNT_TOTAL: 300 },
            },
          ],
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T15:00:00.000Z'));

    expect(result).toEqual({ total: 300, hourly: { 8: 300 } });
  });

  it('reads recent daily groups', async () => {
    const adapter = createHealthConnectAdapter({
      loadModule: () =>
        createModule({
          aggregateGroupByDuration: async () => [
            {
              startTime: '2026-10-19T21:00:00.000Z',
              endTime: '2026-10-20T21:00:00.000Z',
              result: { COUNT_TOTAL: 4000 },
            },
            {
              startTime: '2026-10-20T21:00:00.000Z',
              endTime: '2026-10-21T21:00:00.000Z',
              result: { COUNT_TOTAL: 5000 },
            },
          ],
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readRecentDays(7);

    expect(result).toEqual({
      '2026-10-20': 4000,
      '2026-10-21': 5000,
    });
  });

  it('uses authoritative total from aggregateRecord when present', async () => {
    const aggregateRecord = jest.fn(async () => ({
      COUNT_TOTAL: 450,
    }));
    const aggregateGroupByDuration = jest.fn(async () => [
      {
        startTime: '2026-10-20T05:00:00.000Z',
        endTime: '2026-10-20T06:00:00.000Z',
        result: { COUNT_TOTAL: 300 },
      },
    ]);
    const adapter = createHealthConnectAdapter({
      loadModule: () =>
        createModule({
          aggregateRecord,
          aggregateGroupByDuration,
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T15:00:00.000Z'));

    expect(aggregateRecord).toHaveBeenCalledWith({
      recordType: 'Steps',
      timeRangeFilter: {
        operator: 'between',
        startTime: '2026-10-19T21:00:00.000Z',
        endTime: '2026-10-20T20:59:59.000Z',
      },
    });
    expect(result).toEqual({ total: 450, hourly: { 8: 300 } });
  });

  it('falls back to authoritativeTotal even if aggregateGroupByDuration fails', async () => {
    const aggregateRecord = jest.fn(async () => ({
      COUNT_TOTAL: 1250,
    }));
    const aggregateGroupByDuration = jest.fn(async () => {
      throw new Error('Duration slicing failed');
    });
    const adapter = createHealthConnectAdapter({
      loadModule: () =>
        createModule({
          aggregateRecord,
          aggregateGroupByDuration,
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T15:00:00.000Z'));

    expect(result).toEqual({ total: 1250, hourly: {} });
  });

  it('excludes manual step entries (recordingMethod === 3) from total and hourly buckets', async () => {
    const aggregateRecord = jest.fn(async () => ({
      COUNT_TOTAL: 5000,
    }));
    const aggregateGroupByDuration = jest.fn(async () => [
      {
        startTime: '2026-10-20T05:00:00.000Z',
        endTime: '2026-10-20T06:00:00.000Z',
        result: { COUNT_TOTAL: 2500 },
      },
      {
        startTime: '2026-10-20T06:00:00.000Z',
        endTime: '2026-10-20T07:00:00.000Z',
        result: { COUNT_TOTAL: 2500 },
      },
    ]);
    const readRecords = jest.fn(async () => ({
      records: [
        {
          startTime: '2026-10-20T05:15:00.000Z',
          endTime: '2026-10-20T05:30:00.000Z',
          count: 2000,
          metadata: { recordingMethod: 3 },
        },
        {
          startTime: '2026-10-20T06:10:00.000Z',
          endTime: '2026-10-20T06:40:00.000Z',
          count: 2500,
          metadata: { recordingMethod: 2 },
        },
      ],
    }));

    const adapter = createHealthConnectAdapter({
      loadModule: () =>
        createModule({
          aggregateRecord,
          aggregateGroupByDuration,
          readRecords,
        }),
      consentStore: createConsentStore('granted'),
    });

    const result = await adapter.readDay(new Date('2026-10-20T15:00:00.000Z'));

    expect(result).toEqual({
      total: 3000,
      hourly: { 8: 500, 9: 2500 },
    });
  });
});
