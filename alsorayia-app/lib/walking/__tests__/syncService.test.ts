import { clearWalkingLedger } from '@/lib/walking/ledger';
import { getHealthProvider } from '@/lib/walking/health';
import { syncTodaySteps } from '@/lib/api/walkingApi';
import { Pedometer } from 'expo-sensors';
import {
  buildSyncStepsPayload,
  performSilentWalkingSync,
  recordWalkingSyncSuccess,
  resetWalkingSyncState,
} from '../syncService';

jest.mock('expo-sensors', () => ({
  Pedometer: {
    isAvailableAsync: jest.fn(async () => false),
    getPermissionsAsync: jest.fn(async () => ({ granted: false })),
    getStepCountAsync: jest.fn(async () => ({ steps: 0 })),
    watchStepCount: jest.fn(() => ({ remove: jest.fn() })),
  },
}));

jest.mock('@/lib/walking/health', () => ({
  getHealthProvider: jest.fn(() => null),
}));

jest.mock('@/lib/api/walkingApi', () => ({
  syncTodaySteps: jest.fn(async () => ({ success: true })),
}));

const mockedGetHealthProvider = getHealthProvider as jest.Mock;
const mockedSyncTodaySteps = syncTodaySteps as jest.Mock;

describe('buildSyncStepsPayload', () => {
  it('derives metrics from sanitized steps', () => {
    const payload = buildSyncStepsPayload(12345, {
      source: 'apple_health',
      isAuthoritative: true,
    });

    expect(payload.steps).toBe(12345);
    expect(payload.distance_km).toBe(9.38);
    expect(payload.duration_minutes).toBe(123);
    expect(payload.calories).toBe(494);
    expect(payload.source).toBe('apple_health');
    expect(payload.is_authoritative).toBe(true);
  });

  it('clamps invalid and oversized step counts', () => {
    expect(buildSyncStepsPayload(-100).steps).toBe(0);
    expect(buildSyncStepsPayload(1_000_000).steps).toBe(1_000_000);
    expect(buildSyncStepsPayload(Number.NaN).steps).toBe(0);
  });

  it('marks payloads as non-authoritative by default', () => {
    const payload = buildSyncStepsPayload(100, { source: 'health_connect' });

    expect(payload.is_authoritative).toBe(false);
    expect(payload.source).toBe('health_connect');
  });
});

function buildHealthProvider(overrides: Record<string, unknown> = {}) {
  return {
    source: 'apple_health',
    isAvailable: async () => true,
    getPermissionState: async () => ({
      status: 'granted',
      canAskAgain: false,
      requiresSettings: false,
    }),
    requestPermissions: async () => ({
      status: 'granted',
      canAskAgain: false,
      requiresSettings: false,
    }),
    readDay: async () => ({ total: 4200, hourly: { 9: 4200 } }),
    readRecentDays: async () => ({}),
    openSettings: () => {},
    ...overrides,
  };
}

describe('performSilentWalkingSync', () => {
  beforeEach(async () => {
    resetWalkingSyncState();
    await clearWalkingLedger();
    jest.clearAllMocks();
    mockedGetHealthProvider.mockReturnValue(null);
  });

  it('prefers health data and marks it authoritative', async () => {
    mockedGetHealthProvider.mockReturnValue(buildHealthProvider());

    const result = await performSilentWalkingSync({ force: true });

    expect(result).toEqual({ synced: true, steps: 4200 });
    expect(mockedSyncTodaySteps).toHaveBeenCalledWith(
      expect.objectContaining({
        steps: 4200,
        source: 'apple_health',
        is_authoritative: true,
      })
    );
  });

  it('does not fall back to the pedometer when health permission is denied', async () => {
    mockedGetHealthProvider.mockReturnValue(
      buildHealthProvider({
        getPermissionState: async () => ({
          status: 'denied',
          canAskAgain: false,
          requiresSettings: true,
        }),
      })
    );

    const result = await performSilentWalkingSync({ force: true });

    expect(result).toEqual({ synced: false, steps: 0 });
    expect(Pedometer.isAvailableAsync).not.toHaveBeenCalled();
    expect(mockedSyncTodaySteps).not.toHaveBeenCalled();
  });

  it('does not fall back to the pedometer when a health read fails', async () => {
    mockedGetHealthProvider.mockReturnValue(buildHealthProvider({ readDay: async () => null }));

    const result = await performSilentWalkingSync({ force: true });

    expect(result).toEqual({ synced: false, steps: 0 });
    expect(Pedometer.isAvailableAsync).not.toHaveBeenCalled();
    expect(mockedSyncTodaySteps).not.toHaveBeenCalled();
  });

  it('falls back to the pedometer when health is unavailable', async () => {
    mockedGetHealthProvider.mockReturnValue(
      buildHealthProvider({
        getPermissionState: async () => ({
          status: 'unavailable',
          canAskAgain: false,
          requiresSettings: false,
        }),
      })
    );
    (Pedometer.isAvailableAsync as jest.Mock).mockResolvedValueOnce(true);
    (Pedometer.getPermissionsAsync as jest.Mock).mockResolvedValueOnce({ granted: true });
    (Pedometer.getStepCountAsync as jest.Mock).mockResolvedValueOnce({ steps: 1500 });

    const result = await performSilentWalkingSync({ force: true });

    expect(result).toEqual({ synced: true, steps: 1500 });
    expect(mockedSyncTodaySteps).toHaveBeenCalledWith(
      expect.objectContaining({ steps: 1500, source: 'device' })
    );
  });

  it('syncs authoritative 0 steps to overwrite previously synced manual steps', async () => {
    recordWalkingSyncSuccess(2000);
    mockedGetHealthProvider.mockReturnValue(
      buildHealthProvider({
        readDay: async () => ({ total: 0, hourly: {} }),
      })
    );

    const result = await performSilentWalkingSync({ force: true });

    expect(result).toEqual({ synced: true, steps: 0 });
    expect(mockedSyncTodaySteps).toHaveBeenCalledWith(
      expect.objectContaining({
        steps: 0,
        source: 'apple_health',
        is_authoritative: true,
      })
    );
  });
});
